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
    error_log('=== STARTING ORDER APPROVAL PROCESS ===');

    // Require staff authentication (ONLY staff members can approve/reject orders)
    Auth::requireRole(['staff']);
    $currentUser = Auth::user();
    error_log('User authenticated: ' . json_encode($currentUser));

    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    error_log('Input received: ' . json_encode($input));

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

    error_log("Order ID: {$orderId}, Action: {$action}, Reason: {$reason}");

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
            error_log('Order not found: ' . $orderId);
            throw new Exception('Order not found');
        }

        error_log('Processing order: ' . json_encode($order));

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
            $newStatus = 'confirmed';
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
                $email->sendOrderStatusUpdate($order, $customerData, 'pending', 'confirmed');
            } else {
                // Send rejection email
                sendRejectionEmail($email, $customerData, $order, $reason);
            }
        } catch (Throwable $e) {
            // Email sending is optional - log error but don't fail the request
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
    error_log('Stack trace: ' . $e->getTraceAsString());
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
    // 1. Update order status to 'confirmed'
    $updateQuery = "UPDATE customer_orders
                    SET status = 'confirmed',
                        updated_at = NOW()
                    WHERE id = :order_id";

    $stmt = $db->prepare($updateQuery);
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    // 2. Reduce stock for each item
    foreach ($orderItems as $item) {
        $productId = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];

        error_log("Processing inventory update - Product ID: {$productId}, Quantity: {$quantity}");

        // Update inventory: reduce quantity_on_hand and quantity_reserved
        $updateInvQuery = "UPDATE inventory
                          SET quantity_on_hand = quantity_on_hand - :quantity_hand,
                              quantity_reserved = GREATEST(0, quantity_reserved - :quantity_reserved)
                          WHERE product_id = :product_id";

        try {
            $invUpdateStmt = $db->prepare($updateInvQuery);
            $invUpdateStmt->bindValue(':quantity_hand', $quantity, PDO::PARAM_INT);
            $invUpdateStmt->bindValue(':quantity_reserved', $quantity, PDO::PARAM_INT);
            $invUpdateStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $invUpdateResult = $invUpdateStmt->execute();
            error_log("Inventory update executed successfully. Result: " . (int)$invUpdateResult);
        } catch (PDOException $e) {
            error_log("Inventory update failed: " . $e->getMessage());
            error_log("Query: " . $updateInvQuery);
            error_log("Params - Product ID: {$productId}, Quantity: {$quantity}");
            throw $e;
        }
    }

    // 3. Create sales entry for Sales History
    // Temporarily removed try-catch to see the actual error
    $saleId = createSalesEntry($db, $order, $orderItems, $currentUser);
    error_log('Sales entry created successfully. Sale ID: ' . $saleId);
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
    $stmt->bindValue(':reason', $reason ?: 'No reason provided');
    $stmt->execute();

    // 2. Release reserved stock for each item
    foreach ($orderItems as $item) {
        $productId = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];

        // Release reservation: decrease quantity_reserved
        $updateInvQuery = "UPDATE inventory
                          SET quantity_reserved = GREATEST(0, quantity_reserved - :quantity_reserved)
                          WHERE product_id = :product_id";

        $invUpdateStmt = $db->prepare($updateInvQuery);
        $invUpdateStmt->bindValue(':quantity_reserved', $quantity, PDO::PARAM_INT);
        $invUpdateStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $invUpdateStmt->execute();
    }

    // 3. Log activity - DISABLED for now
    // TODO: Re-enable once activity_logs table schema is verified
    /*
    try {
        $logMessage = "Order #{$order['order_number']} rejected by {$currentUser['full_name']}";
        if ($reason) {
            $logMessage .= " - Reason: $reason";
        }
        logActivity($db, $currentUser['id'], 'order_rejected', 'customer_orders', $orderId, $logMessage);
    } catch (Throwable $e) {
        error_log('Failed to log activity (non-critical): ' . $e->getMessage());
    }
    */
}

/**
 * Create sales entry for approved order - integrates with Sales History
 */
function createSalesEntry($db, $order, $orderItems, $currentUser) {
    // Generate invoice number
    $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

    // Calculate amounts
    $subtotal = $order['subtotal'];
    $taxAmount = $order['tax_amount'];
    $totalAmount = $order['total_amount'];
    $taxRate = $subtotal > 0 ? ($taxAmount / $subtotal) : 0.12;

    // Map payment method to sales table enum values
    $paymentMethodMap = [
        'cash_on_delivery' => 'cash',
        'credit_card' => 'card',
        'debit_card' => 'card',
        'bank_transfer' => 'bank_transfer',
        'gcash' => 'digital_wallet',
        'paymaya' => 'digital_wallet',
        'paypal' => 'digital_wallet'
    ];
    $orderPaymentMethod = strtolower($order['payment_method'] ?? 'cash');
    $paymentMethod = $paymentMethodMap[$orderPaymentMethod] ?? 'cash';

    // Customer info
    $customerName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
    $customerEmail = $order['email'] ?? null;
    $customerPhone = $order['phone'] ?? null;

    // Create sale record - EXACTLY like create.php
    $saleQuery = "INSERT INTO sales (invoice_number, cashier_id, customer_name, customer_email, customer_phone, subtotal, tax_amount, tax_rate, discount_amount, total_amount, payment_method, payment_status, notes) VALUES (:invoice_number, :cashier_id, :customer_name, :customer_email, :customer_phone, :subtotal, :tax_amount, :tax_rate, :discount_amount, :total_amount, :payment_method, :payment_status, :notes)";

    $params = [
        ':invoice_number' => $invoiceNumber,
        ':cashier_id' => $currentUser['id'],
        ':customer_name' => $customerName,
        ':customer_email' => $customerEmail,
        ':customer_phone' => $customerPhone,
        ':subtotal' => $subtotal,
        ':tax_amount' => $taxAmount,
        ':tax_rate' => $taxRate,
        ':discount_amount' => 0,
        ':total_amount' => $totalAmount,
        ':payment_method' => $paymentMethod,
        ':payment_status' => 'paid',
        ':notes' => 'Customer Order #' . ($order['order_number'] ?? $order['id'])
    ];

    try {
        error_log('About to execute sales INSERT with params: ' . json_encode($params));
        $stmt = $db->prepare($saleQuery);

        // Bind parameters individually to handle NULL values properly
        $stmt->bindValue(':invoice_number', $invoiceNumber);
        $stmt->bindValue(':cashier_id', $currentUser['id'], PDO::PARAM_INT);
        $stmt->bindValue(':customer_name', $customerName);
        $stmt->bindValue(':customer_email', $customerEmail);
        $stmt->bindValue(':customer_phone', $customerPhone);
        $stmt->bindValue(':subtotal', $subtotal);
        $stmt->bindValue(':tax_amount', $taxAmount);
        $stmt->bindValue(':tax_rate', $taxRate);
        $stmt->bindValue(':discount_amount', 0);
        $stmt->bindValue(':total_amount', $totalAmount);
        $stmt->bindValue(':payment_method', $paymentMethod);
        $stmt->bindValue(':payment_status', 'paid');
        $stmt->bindValue(':notes', 'Customer Order #' . ($order['order_number'] ?? $order['id']));

        $stmt->execute();
        $saleId = $db->lastInsertId();
        error_log('Sales INSERT successful. Sale ID: ' . $saleId);
    } catch (PDOException $e) {
        error_log('Sales INSERT failed: ' . $e->getMessage());
        error_log('Query: ' . $saleQuery);
        error_log('Params: ' . json_encode($params));
        throw $e;
    }

    // Create sale items
    foreach ($orderItems as $item) {
        $itemQuery = "
            INSERT INTO sale_items (sale_id, product_id, quantity, unit_price)
            VALUES (:sale_id, :product_id, :quantity, :unit_price)
        ";

        $itemStmt = $db->prepare($itemQuery);
        $itemStmt->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
        $itemStmt->bindValue(':product_id', $item['product_id'], PDO::PARAM_INT);
        $itemStmt->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
        $itemStmt->bindValue(':unit_price', $item['unit_price']);
        $itemStmt->execute();
    }

    error_log('Sales entry created successfully. Sale ID: ' . $saleId . ', Invoice: ' . $invoiceNumber);

    return $saleId;
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

