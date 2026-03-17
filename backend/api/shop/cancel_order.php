<?php
/**
 * Shop Order Cancellation API Endpoint
 * POST /backend/api/shop/cancel_order.php - Cancel customer order and restore inventory
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get customer ID
$customerId = $_SESSION['customer_id'] ?? null;

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        cancelOrder($customerId);
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Order cancellation failed: ' . $e->getMessage());
}

function cancelOrder($customerId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate customer authentication
    if (!$customerId) {
        Response::error('Customer authentication required', 401);
    }

    // Validate order ID
    if (!isset($input['order_id']) || empty($input['order_id'])) {
        Response::error('Order ID is required', 400);
    }

    $orderId = (int)$input['order_id'];
    $cancellationReason = $input['reason'] ?? 'Customer requested cancellation';

    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    try {
        // Get order details and verify ownership
        $order = getOrderForCancellation($db, $orderId, $customerId);

        if (!$order) {
            throw new Exception('Order not found or you do not have permission to cancel it');
        }

        // Validate order can be cancelled
        $validationResult = validateCancellation($order);
        if (!$validationResult['can_cancel']) {
            throw new Exception($validationResult['reason']);
        }

        // Get order items
        $orderItems = getOrderItems($db, $orderId);

        if (empty($orderItems)) {
            throw new Exception('Order has no items');
        }

        // Restore inventory for each item
        restoreInventory($db, $orderItems, $orderId, $customerId, $cancellationReason);

        // Update order status to cancelled
        updateOrderStatus($db, $orderId, 'cancelled', $cancellationReason);

        // Process refund if payment was made
        $refundResult = null;
        if ($order['payment_status'] === 'paid') {
            $refundResult = processRefund($db, $order, $orderId);
        }

        $db->commit();

        Response::success([
            'message' => 'Order cancelled successfully',
            'order_id' => $orderId,
            'refund' => $refundResult,
            'inventory_restored' => count($orderItems)
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function getOrderForCancellation($db, $orderId, $customerId) {
    $query = "SELECT * FROM customer_orders
              WHERE id = :order_id AND customer_id = :customer_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function validateCancellation($order) {
    // Orders can only be cancelled if they are in certain statuses
    $cancellableStatuses = ['pending', 'confirmed', 'processing'];

    if (!in_array($order['status'], $cancellableStatuses)) {
        return [
            'can_cancel' => false,
            'reason' => "Orders with status '{$order['status']}' cannot be cancelled. Please contact support."
        ];
    }

    // Already cancelled
    if ($order['status'] === 'cancelled') {
        return [
            'can_cancel' => false,
            'reason' => 'Order is already cancelled'
        ];
    }

    // If order is shipped or delivered, cannot cancel
    if (in_array($order['status'], ['shipped', 'delivered', 'completed'])) {
        return [
            'can_cancel' => false,
            'reason' => 'Order has already been shipped or delivered. Please contact support for returns.'
        ];
    }

    return ['can_cancel' => true];
}

function getOrderItems($db, $orderId) {
    $query = "SELECT * FROM customer_order_items WHERE order_id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function restoreInventory($db, $orderItems, $orderId, $customerId, $reason) {
    foreach ($orderItems as $item) {
        $productId = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];

        $inventoryQuery = "SELECT quantity_on_hand, quantity_reserved FROM inventory WHERE product_id = :product_id LIMIT 1";
        $inventoryStmt = $db->prepare($inventoryQuery);
        $inventoryStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $inventoryStmt->execute();
        $inventory = $inventoryStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0
        ];

        $quantityOnHand = (int)$inventory['quantity_on_hand'];
        $quantityReserved = (int)$inventory['quantity_reserved'];
        $reservedRelease = min($quantityReserved, $quantity);
        $onHandRestore = $quantity - $reservedRelease;

        $updateQuery = "
            INSERT INTO inventory (product_id, quantity_on_hand, quantity_reserved)
            VALUES (:product_id, :quantity_on_hand, 0)
            ON DUPLICATE KEY UPDATE
                quantity_on_hand = quantity_on_hand + VALUES(quantity_on_hand),
                quantity_reserved = GREATEST(0, quantity_reserved - :quantity_reserved)
        ";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([
            ':product_id' => $productId,
            ':quantity_on_hand' => $onHandRestore,
            ':quantity_reserved' => $reservedRelease
        ]);

        if ($onHandRestore > 0) {
            $movementQuery = "
                INSERT INTO stock_movements (
                    product_id, movement_type, quantity, quantity_before, quantity_after,
                    reference_type, reference_id, performed_by, notes
                ) VALUES (
                    :product_id, 'return', :quantity, :quantity_before, :quantity_after,
                    'CUSTOMER_ORDER', :order_id, :customer_id, :notes
                )
            ";

            $movementStmt = $db->prepare($movementQuery);
            $movementStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $movementStmt->bindValue(':quantity', $onHandRestore, PDO::PARAM_INT);
            $movementStmt->bindValue(':quantity_before', $quantityOnHand, PDO::PARAM_INT);
            $movementStmt->bindValue(':quantity_after', $quantityOnHand + $onHandRestore, PDO::PARAM_INT);
            $movementStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
            $movementStmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            $notes = "Order Cancelled - {$item['product_name']} - Reason: {$reason}";
            $movementStmt->bindValue(':notes', $notes);
            $movementStmt->execute();
        }
    }
}

function updateOrderStatus($db, $orderId, $status, $reason) {
    $query = "UPDATE customer_orders
              SET status = :status,
                  cancellation_reason = :reason,
                  cancelled_at = NOW()
              WHERE id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':reason', $reason);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
}

function processRefund($db, $order, $orderId) {
    // Update payment status to refunded
    $query = "UPDATE customer_orders
              SET payment_status = 'refunded'
              WHERE id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    // In a real system, you would integrate with payment gateway to process actual refund
    // For now, we'll return a simulated refund result
    return [
        'status' => 'refund_initiated',
        'amount' => $order['total_amount'],
        'payment_method' => $order['payment_method'],
        'message' => 'Refund will be processed within 5-7 business days',
        'refund_id' => 'REFUND_' . time() . '_' . rand(1000, 9999)
    ];
}

