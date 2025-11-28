<?php
/**
 * Order Approval API Endpoint
 * POST /backend/api/orders/approve.php - Approve or reject customer orders
 *
 * This endpoint allows staff to approve or reject pending customer orders.
 * - On approval: reduces stock, changes status to 'success', creates sales entry
 * - On rejection: releases reserved stock, changes status to 'cancelled'
 */

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Email.php';

CORS::handle();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    // Require staff authentication
    Auth::requireRole(['admin', 'inventory_manager', 'staff']);
    $currentUser = Auth::user();

    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    if (!isset($input['order_id']) || empty($input['order_id'])) {
        Response::error('Order ID is required', 400);
    }

    if (!isset($input['action']) || empty($input['action'])) {
        Response::error('Action is required (approve or reject)', 400);
    }

    $orderId = (int)$input['order_id'];
    $action = strtolower(trim($input['action']));
    $reason = isset($input['reason']) ? trim($input['reason']) : null;

    // Validate action
    if (!in_array($action, ['approve', 'reject'])) {
        Response::error('Invalid action. Must be "approve" or "reject"', 400);
    }

    // Begin transaction
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    try {
        // Get order details
        $order = getOrderDetails($db, $orderId);

        if (!$order) {
            throw new Exception('Order not found');
        }

        // Check if order is pending
        if ($order['status'] !== 'pending') {
            throw new Exception('Order is not pending. Current status: ' . $order['status']);
        }

        // Get order items
        $orderItems = getOrderItems($db, $orderId);

        if (empty($orderItems)) {
            throw new Exception('Order has no items');
        }

        if ($action === 'approve') {
            // APPROVE ORDER
            approveOrder($db, $orderId, $order, $orderItems, $currentUser);
            $message = 'Order approved successfully';
            $newStatus = 'success';
        } else {
            // REJECT ORDER
            rejectOrder($db, $orderId, $order, $orderItems, $currentUser, $reason);
            $message = 'Order rejected successfully';
            $newStatus = 'cancelled';
        }

        // Commit transaction
        $db->commit();

        // Send email notification (outside transaction to prevent rollback on email failure)
        try {
            $email = new Email();
            $customerData = [
                'first_name' => $order['first_name'],
                'last_name' => $order['last_name'],
                'email' => $order['email']
            ];

            if ($action === 'approve') {
                @$email->sendOrderStatusUpdate($order, $customerData, 'pending', 'success');
            } else {
                // Send rejection email
                @sendRejectionEmail($email, $customerData, $order, $reason);
            }
        } catch (Exception $e) {
            error_log('Failed to send email notification: ' . $e->getMessage());
        }

        Response::success([
            'order_id' => $orderId,
            'action' => $action,
            'new_status' => $newStatus,
            'message' => $message
        ], $message);

    } catch (Exception $e) {
        $db->rollBack();
        error_log('Order approval error: ' . $e->getMessage());
        throw $e;
    }

} catch (Exception $e) {
    error_log('Order approval API error: ' . $e->getMessage());
    Response::error($e->getMessage(), 500);
}

/**
 * Get order details with customer information
 */
function getOrderDetails($db, $orderId) {
    $query = "SELECT
                co.*,
                c.first_name, c.last_name, c.email
              FROM customer_orders co
              INNER JOIN customers c ON co.customer_id = c.id
              WHERE co.id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get order items
 */
function getOrderItems($db, $orderId) {
    $query = "SELECT * FROM customer_order_items WHERE order_id = :order_id ORDER BY id";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Approve order - Reduce stock, update status to success, create sales entry
 */
function approveOrder($db, $orderId, $order, $orderItems, $currentUser) {
    // 1. Update order status to 'success'
    $updateQuery = "UPDATE customer_orders
                    SET status = 'success',
                        updated_at = NOW()
                    WHERE id = :order_id";

    $stmt = $db->prepare($updateQuery);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    // 2. Reduce stock for each item
    foreach ($orderItems as $item) {
        $productId = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];

        // Get current inventory levels before update
        $invQuery = "SELECT quantity_on_hand, quantity_reserved FROM inventory WHERE product_id = :product_id";
        $invStmt = $db->prepare($invQuery);
        $invStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $invStmt->execute();
        $invData = $invStmt->fetch(PDO::FETCH_ASSOC);

        $quantityBefore = $invData ? $invData['quantity_on_hand'] : 0;
        $quantityAfter = $quantityBefore - $quantity;

        // Update inventory: reduce quantity_on_hand and quantity_reserved
        $updateInvQuery = "UPDATE inventory
                          SET quantity_on_hand = quantity_on_hand - :quantity,
                              quantity_reserved = GREATEST(0, quantity_reserved - :quantity)
                          WHERE product_id = :product_id";

        $invUpdateStmt = $db->prepare($updateInvQuery);
        $invUpdateStmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $invUpdateStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $invUpdateStmt->execute();

        // Create stock movement record for audit trail
        $movementQuery = "INSERT INTO stock_movements (
                            product_id, movement_type, quantity, quantity_before, quantity_after,
                            reference_type, reference_id, performed_by, notes
                         ) VALUES (
                            :product_id, 'sale', :quantity_neg, :quantity_before, :quantity_after,
                            'CUSTOMER_ORDER', :order_id, :performed_by, :notes
                         )";

        $movementStmt = $db->prepare($movementQuery);
        $movementStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $movementStmt->bindValue(':quantity_neg', -$quantity, PDO::PARAM_INT);
        $movementStmt->bindValue(':quantity_before', $quantityBefore, PDO::PARAM_INT);
        $movementStmt->bindValue(':quantity_after', $quantityAfter, PDO::PARAM_INT);
        $movementStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $movementStmt->bindValue(':performed_by', $currentUser['id'], PDO::PARAM_INT);
        $notes = 'Customer Order Approved - ' . $item['product_name'];
        $movementStmt->bindValue(':notes', $notes);
        $movementStmt->execute();
    }

    // 3. Create sales entry (integrate with Sales History)
    createSalesEntry($db, $orderId, $order, $orderItems, $currentUser);

    // 4. Log activity
    logActivity($db, $currentUser['id'], 'order_approved', 'customer_orders', $orderId,
                "Order #{$order['order_number']} approved by {$currentUser['full_name']}");
}

/**
 * Reject order - Release reserved stock, update status to cancelled
 */
function rejectOrder($db, $orderId, $order, $orderItems, $currentUser, $reason) {
    // 1. Update order status to 'cancelled'
    $updateQuery = "UPDATE customer_orders
                    SET status = 'cancelled',
                        notes = CONCAT(COALESCE(notes, ''), '\n\nRejection Reason: ', :reason),
                        updated_at = NOW()
                    WHERE id = :order_id";

    $stmt = $db->prepare($updateQuery);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->bindValue(':reason', $reason ?? 'No reason provided');
    $stmt->execute();

    // 2. Release reserved stock for each item
    foreach ($orderItems as $item) {
        $productId = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];

        // Release reservation: decrease quantity_reserved
        $updateInvQuery = "UPDATE inventory
                          SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity)
                          WHERE product_id = :product_id";

        $invUpdateStmt = $db->prepare($updateInvQuery);
        $invUpdateStmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
        $invUpdateStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $invUpdateStmt->execute();
    }

    // 3. Log activity
    $logMessage = "Order #{$order['order_number']} rejected by {$currentUser['full_name']}";
    if ($reason) {
        $logMessage .= " - Reason: $reason";
    }
    logActivity($db, $currentUser['id'], 'order_rejected', 'customer_orders', $orderId, $logMessage);
}

/**
 * Create sales entry for approved order
 */
function createSalesEntry($db, $orderId, $order, $orderItems, $currentUser) {
    // Check if sales table exists and create entry
    // This integrates approved online orders with the POS sales history

    $saleQuery = "INSERT INTO sales (
                    order_id, sale_date, customer_id, total_amount,
                    payment_method, payment_status, created_by, notes
                 ) VALUES (
                    :order_id, NOW(), :customer_id, :total_amount,
                    :payment_method, :payment_status, :created_by, :notes
                 )";

    try {
        $stmt = $db->prepare($saleQuery);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':customer_id', $order['customer_id'], PDO::PARAM_INT);
        $stmt->bindValue(':total_amount', $order['total_amount']);
        $stmt->bindValue(':payment_method', $order['payment_method']);
        $stmt->bindValue(':payment_status', $order['payment_status']);
        $stmt->bindValue(':created_by', $currentUser['id'], PDO::PARAM_INT);
        $notes = 'Online Customer Order - Approved by ' . $currentUser['full_name'];
        $stmt->bindValue(':notes', $notes);
        $stmt->execute();
    } catch (PDOException $e) {
        // If sales table doesn't have order_id column or doesn't exist, skip
        error_log('Failed to create sales entry: ' . $e->getMessage());
    }
}

/**
 * Log activity for audit trail
 */
function logActivity($db, $userId, $action, $entityType, $entityId, $description) {
    try {
        $query = "INSERT INTO activity_logs (
                    user_id, action, entity_type, entity_id, description, created_at
                 ) VALUES (
                    :user_id, :action, :entity_type, :entity_id, :description, NOW()
                 )";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':entity_type', $entityType);
        $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $stmt->bindValue(':description', $description);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

/**
 * Send rejection email to customer
 */
function sendRejectionEmail($email, $customerData, $order, $reason) {
    $subject = 'Order Update - Order #' . $order['order_number'] . ' - PC Parts Central';

    $reasonText = $reason ?? 'We are unable to fulfill your order at this time';

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Order Update</title>
    </head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h1 style='color: #2c3e50;'>Order Update</h1>

            <p>Dear {$customerData['first_name']} {$customerData['last_name']},</p>

            <p>We regret to inform you that we are unable to process your order at this time.</p>

            <div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; border-left: 4px solid #e74c3c;'>
                <strong>Order Number:</strong> {$order['order_number']}<br>
                <strong>Status:</strong> Cancelled<br>
                <strong>Reason:</strong> {$reasonText}
            </div>

            <p>If payment was processed, a refund will be issued to your original payment method within 5-7 business days.</p>

            <p>If you have any questions or concerns, please don't hesitate to contact our customer support team.</p>

            <p>We apologize for any inconvenience this may have caused.</p>

            <p>Best regards,<br>PC Parts Central Team</p>
        </div>
    </body>
    </html>
    ";

    return $email->send($customerData['email'], $subject, $message, $customerData['first_name'] . ' ' . $customerData['last_name']);
}
