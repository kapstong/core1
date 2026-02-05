<?php
/**
 * Shop Order Return Processing API Endpoint
 * POST /backend/api/shop/return_order.php - Initiate order return/refund
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
        processReturn($customerId);
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Return processing failed: ' . $e->getMessage());
}

function processReturn($customerId) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate customer authentication
    if (!$customerId) {
        Response::error('Customer authentication required', 401);
    }

    // Validate required fields
    if (!isset($input['order_id']) || empty($input['order_id'])) {
        Response::error('Order ID is required', 400);
    }

    if (!isset($input['items']) || empty($input['items'])) {
        Response::error('Return items are required', 400);
    }

    if (!isset($input['reason']) || empty($input['reason'])) {
        Response::error('Return reason is required', 400);
    }

    $orderId = (int)$input['order_id'];
    $returnItems = $input['items']; // Array of {product_id, quantity, reason}
    $returnReason = trim($input['reason']);

    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    try {
        // Get order details and verify ownership
        $order = getOrderForReturn($db, $orderId, $customerId);

        if (!$order) {
            throw new Exception('Order not found or you do not have permission to return it');
        }

        // Validate order can be returned
        $validationResult = validateReturn($order);
        if (!$validationResult['can_return']) {
            throw new Exception($validationResult['reason']);
        }

        // Validate return items
        $orderItems = getOrderItems($db, $orderId);
        $validatedItems = validateReturnItems($returnItems, $orderItems);

        if (!$validatedItems['valid']) {
            throw new Exception($validatedItems['error']);
        }

        // Calculate return amount
        $returnAmount = calculateReturnAmount($validatedItems['items']);

        // Create return record
        $returnId = createReturnRecord($db, $orderId, $customerId, $returnReason, $returnAmount);

        // Create return items
        createReturnItems($db, $returnId, $validatedItems['items']);

        // Restore inventory for returned items
        restoreInventoryForReturn($db, $validatedItems['items'], $returnId, $customerId);

        // Process refund
        $refundResult = processRefund($db, $order, $returnAmount, $returnId);

        // Update order status if full return
        $isFullReturn = checkIfFullReturn($validatedItems['items'], $orderItems);
        if ($isFullReturn) {
            updateOrderStatus($db, $orderId, 'refunded');
        }

        $db->commit();

        Response::success([
            'message' => 'Return processed successfully',
            'return_id' => $returnId,
            'order_id' => $orderId,
            'return_amount' => $returnAmount,
            'items_returned' => count($validatedItems['items']),
            'refund' => $refundResult,
            'is_full_return' => $isFullReturn
        ], 201);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function getOrderForReturn($db, $orderId, $customerId) {
    $query = "SELECT * FROM customer_orders
              WHERE id = :order_id AND customer_id = :customer_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function validateReturn($order) {
    // Orders can only be returned if delivered or completed
    $returnableStatuses = ['delivered', 'completed'];

    if (!in_array($order['status'], $returnableStatuses)) {
        return [
            'can_return' => false,
            'reason' => "Orders with status '{$order['status']}' cannot be returned. Order must be delivered first."
        ];
    }

    // Already refunded
    if ($order['status'] === 'refunded' || $order['payment_status'] === 'refunded') {
        return [
            'can_return' => false,
            'reason' => 'Order has already been refunded'
        ];
    }

    // Check if return window has expired (e.g., 30 days from delivery)
    $returnWindowDays = 30;
    if ($order['delivered_date']) {
        $deliveryDate = new DateTime($order['delivered_date']);
        $now = new DateTime();
        $daysSinceDelivery = $deliveryDate->diff($now)->days;

        if ($daysSinceDelivery > $returnWindowDays) {
            return [
                'can_return' => false,
                'reason' => "Return window expired. Items must be returned within {$returnWindowDays} days of delivery."
            ];
        }
    }

    return ['can_return' => true];
}

function getOrderItems($db, $orderId) {
    $query = "SELECT * FROM customer_order_items WHERE order_id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function validateReturnItems($returnItems, $orderItems) {
    $orderItemsMap = [];
    foreach ($orderItems as $item) {
        $orderItemsMap[$item['product_id']] = $item;
    }

    $validatedItems = [];

    foreach ($returnItems as $returnItem) {
        if (!isset($returnItem['product_id']) || !isset($returnItem['quantity'])) {
            return ['valid' => false, 'error' => 'Each return item must have product_id and quantity'];
        }

        $productId = (int)$returnItem['product_id'];
        $quantity = (int)$returnItem['quantity'];
        $itemReason = $returnItem['reason'] ?? 'No specific reason provided';

        if (!isset($orderItemsMap[$productId])) {
            return ['valid' => false, 'error' => "Product ID {$productId} was not in the original order"];
        }

        $orderItem = $orderItemsMap[$productId];

        if ($quantity > $orderItem['quantity']) {
            return [
                'valid' => false,
                'error' => "Cannot return {$quantity} units of {$orderItem['product_name']}. Only {$orderItem['quantity']} were ordered."
            ];
        }

        if ($quantity <= 0) {
            return ['valid' => false, 'error' => 'Return quantity must be greater than 0'];
        }

        $validatedItems[] = [
            'product_id' => $productId,
            'product_name' => $orderItem['product_name'],
            'product_sku' => $orderItem['product_sku'],
            'quantity' => $quantity,
            'unit_price' => $orderItem['unit_price'],
            'total_price' => $quantity * $orderItem['unit_price'],
            'reason' => $itemReason
        ];
    }

    return ['valid' => true, 'items' => $validatedItems];
}

function calculateReturnAmount($returnItems) {
    $total = 0.00;
    foreach ($returnItems as $item) {
        $total += $item['total_price'];
    }
    return round($total, 2);
}

function createReturnRecord($db, $orderId, $customerId, $reason, $returnAmount) {
    // Create returns table entry
    $returnNumber = 'RET-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);

    $query = "INSERT INTO customer_returns
              (return_number, order_id, customer_id, return_reason, return_amount, status)
              VALUES
              (:return_number, :order_id, :customer_id, :reason, :amount, 'pending')";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':return_number', $returnNumber);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->bindParam(':reason', $reason);
    $stmt->bindParam(':amount', $returnAmount);
    $stmt->execute();

    return $db->lastInsertId();
}

function createReturnItems($db, $returnId, $returnItems) {
    $query = "INSERT INTO customer_return_items
              (return_id, product_id, product_name, product_sku, quantity, unit_price, return_reason)
              VALUES
              (:return_id, :product_id, :product_name, :product_sku, :quantity, :unit_price, :reason)";

    $stmt = $db->prepare($query);

    foreach ($returnItems as $item) {
        $stmt->bindParam(':return_id', $returnId, PDO::PARAM_INT);
        $stmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
        $stmt->bindParam(':product_name', $item['product_name']);
        $stmt->bindParam(':product_sku', $item['product_sku']);
        $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
        $stmt->bindParam(':unit_price', $item['unit_price']);
        $stmt->bindParam(':reason', $item['reason']);
        $stmt->execute();
    }
}

function restoreInventoryForReturn($db, $returnItems, $returnId, $customerId) {
    // Restore inventory quantity
    $updateQuery = "UPDATE inventory SET quantity_on_hand = quantity_on_hand + :quantity
                    WHERE product_id = :product_id";
    $updateStmt = $db->prepare($updateQuery);

    foreach ($returnItems as $item) {
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
                i.quantity_on_hand, 'CUSTOMER_RETURN', :return_id, :customer_id, :notes
            FROM inventory i
            WHERE i.product_id = :product_id
        ";

        $movementStmt = $db->prepare($movementQuery);
        $movementStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
        $movementStmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
        $movementStmt->bindParam(':return_id', $returnId, PDO::PARAM_INT);
        $movementStmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
        $notes = "Customer Return - {$item['product_name']} - {$item['reason']}";
        $movementStmt->bindParam(':notes', $notes);
        $movementStmt->execute();
    }
}

function processRefund($db, $order, $returnAmount, $returnId) {
    // Update return record with refund status
    $query = "UPDATE customer_returns
              SET status = 'approved',
                  refund_amount = :amount,
                  refund_status = 'refund_initiated',
                  processed_at = NOW()
              WHERE id = :return_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':amount', $returnAmount);
    $stmt->bindParam(':return_id', $returnId, PDO::PARAM_INT);
    $stmt->execute();

    // In a real system, integrate with payment gateway to process actual refund
    return [
        'status' => 'refund_initiated',
        'amount' => $returnAmount,
        'payment_method' => $order['payment_method'],
        'message' => 'Refund will be processed within 5-7 business days',
        'refund_id' => 'REFUND_' . time() . '_' . rand(1000, 9999)
    ];
}

function checkIfFullReturn($returnItems, $orderItems) {
    // Check if all order items are being returned with full quantities
    $orderItemsMap = [];
    foreach ($orderItems as $item) {
        $orderItemsMap[$item['product_id']] = $item['quantity'];
    }

    $returnItemsMap = [];
    foreach ($returnItems as $item) {
        $returnItemsMap[$item['product_id']] = $item['quantity'];
    }

    // Full return if same number of products and all quantities match
    if (count($orderItemsMap) !== count($returnItemsMap)) {
        return false;
    }

    foreach ($orderItemsMap as $productId => $orderQty) {
        if (!isset($returnItemsMap[$productId]) || $returnItemsMap[$productId] !== $orderQty) {
            return false;
        }
    }

    return true;
}

function updateOrderStatus($db, $orderId, $status) {
    $query = "UPDATE customer_orders
              SET status = :status, payment_status = 'refunded'
              WHERE id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
}

