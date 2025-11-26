<?php
/**
 * Shop Order Cancellation API Endpoint
 * POST /backend/api/shop/cancel_order.php - Cancel customer order and restore inventory
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
    // Restore inventory quantity
    $updateQuery = "UPDATE inventory SET quantity_on_hand = quantity_on_hand + :quantity
                    WHERE product_id = :product_id";
    $updateStmt = $db->prepare($updateQuery);

    foreach ($orderItems as $item) {
        // Restore stock
        $updateStmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
        $updateStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
        $updateStmt->execute();

        // Create stock movement record for audit trail
        $movementQuery = "
            INSERT INTO stock_movements (
                product_id, movement_type, quantity, quantity_before, quantity_after,
                reference_type, reference_id, performed_by, notes
            )
            SELECT
                :product_id, 'return', :quantity, i.quantity_on_hand - :quantity,
                i.quantity_on_hand, 'CUSTOMER_ORDER_CANCELLED', :order_id, :customer_id, :notes
            FROM inventory i
            WHERE i.product_id = :product_id
        ";

        $movementStmt = $db->prepare($movementQuery);
        $movementStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
        $movementStmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
        $movementStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
        $movementStmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
        $notes = "Order Cancelled - {$item['product_name']} - Reason: {$reason}";
        $movementStmt->bindParam(':notes', $notes);
        $movementStmt->execute();
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
