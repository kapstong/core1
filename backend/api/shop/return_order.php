<?php
/**
 * Shop Order Return Processing API Endpoint
 * POST /backend/api/shop/return_order.php - Initiate order return request
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

function processReturn($customerId): void {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    if (!$customerId) {
        Response::error('Customer authentication required', 401);
    }

    if (empty($input['order_id'])) {
        Response::error('Order ID is required', 400);
    }

    if (empty($input['items']) || !is_array($input['items'])) {
        Response::error('Return items are required', 400);
    }

    if (empty($input['reason'])) {
        Response::error('Return reason is required', 400);
    }

    $orderId = (int)$input['order_id'];
    $returnItems = $input['items'];
    $returnReason = trim((string)$input['reason']);

    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    try {
        $order = getOrderForReturn($db, $orderId, $customerId);
        if (!$order) {
            throw new Exception('Order not found or you do not have permission to return it');
        }

        $validationResult = validateReturn($order);
        if (!$validationResult['can_return']) {
            throw new Exception($validationResult['reason']);
        }

        $orderItems = getOrderItems($db, $orderId);
        $previousReturnQuantities = getExistingReturnQuantities($db, $orderId);
        $validatedItems = validateReturnItems($returnItems, $orderItems, $previousReturnQuantities);

        if (!$validatedItems['valid']) {
            throw new Exception($validatedItems['error']);
        }

        $returnAmount = calculateReturnAmount($validatedItems['items']);
        $returnId = createReturnRecord($db, $orderId, $customerId, $returnReason, $returnAmount);
        createReturnItems($db, $returnId, $validatedItems['items']);

        $db->commit();

        Response::success([
            'message' => 'Return request submitted successfully',
            'return_id' => $returnId,
            'order_id' => $orderId,
            'return_amount' => $returnAmount,
            'items_requested' => count($validatedItems['items']),
            'status' => 'requested'
        ], 'Return request submitted successfully', 201);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function getOrderForReturn(PDO $db, int $orderId, int $customerId) {
    $query = "SELECT * FROM customer_orders
              WHERE id = :order_id AND customer_id = :customer_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function validateReturn(array $order): array {
    $returnableStatuses = ['delivered', 'completed'];

    if (!in_array($order['status'], $returnableStatuses, true)) {
        return [
            'can_return' => false,
            'reason' => "Orders with status '{$order['status']}' cannot be returned. Order must be delivered first."
        ];
    }

    if (($order['status'] ?? null) === 'refunded' || ($order['payment_status'] ?? null) === 'refunded') {
        return [
            'can_return' => false,
            'reason' => 'Order has already been refunded'
        ];
    }

    $returnWindowDays = 30;
    if (!empty($order['delivered_date'])) {
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

function getOrderItems(PDO $db, int $orderId): array {
    $query = "SELECT * FROM customer_order_items WHERE order_id = :order_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getExistingReturnQuantities(PDO $db, int $orderId): array {
    $query = "
        SELECT cri.product_id, COALESCE(SUM(cri.quantity), 0) AS returned_quantity
        FROM customer_returns cr
        INNER JOIN customer_return_items cri ON cr.id = cri.return_id
        WHERE cr.order_id = :order_id
          AND cr.status != 'rejected'
        GROUP BY cri.product_id
    ";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();

    $quantities = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $quantities[(int)$row['product_id']] = (int)$row['returned_quantity'];
    }

    return $quantities;
}

function validateReturnItems(array $returnItems, array $orderItems, array $existingReturnQuantities): array {
    $orderItemsMap = [];
    foreach ($orderItems as $item) {
        $orderItemsMap[(int)$item['product_id']] = $item;
    }

    $aggregatedItems = [];
    foreach ($returnItems as $returnItem) {
        if (!isset($returnItem['product_id'], $returnItem['quantity'])) {
            return ['valid' => false, 'error' => 'Each return item must have product_id and quantity'];
        }

        $productId = (int)$returnItem['product_id'];
        $quantity = (int)$returnItem['quantity'];

        if ($quantity <= 0) {
            return ['valid' => false, 'error' => 'Return quantity must be greater than 0'];
        }

        if (!isset($aggregatedItems[$productId])) {
            $aggregatedItems[$productId] = [
                'quantity' => 0,
                'reason' => $returnItem['reason'] ?? 'No specific reason provided'
            ];
        }

        $aggregatedItems[$productId]['quantity'] += $quantity;
        if (!empty($returnItem['reason'])) {
            $aggregatedItems[$productId]['reason'] = $returnItem['reason'];
        }
    }

    $validatedItems = [];

    foreach ($aggregatedItems as $productId => $aggregatedItem) {
        if (!isset($orderItemsMap[$productId])) {
            return ['valid' => false, 'error' => "Product ID {$productId} was not in the original order"];
        }

        $orderItem = $orderItemsMap[$productId];
        $quantity = (int)$aggregatedItem['quantity'];
        $alreadyReturned = (int)($existingReturnQuantities[$productId] ?? 0);
        $remainingReturnable = (int)$orderItem['quantity'] - $alreadyReturned;

        if ($remainingReturnable <= 0) {
            return [
                'valid' => false,
                'error' => "All units of {$orderItem['product_name']} already have return requests."
            ];
        }

        if ($quantity > $remainingReturnable) {
            return [
                'valid' => false,
                'error' => "Cannot return {$quantity} units of {$orderItem['product_name']}. Only {$remainingReturnable} remain returnable."
            ];
        }

        $validatedItems[] = [
            'product_id' => $productId,
            'product_name' => $orderItem['product_name'],
            'product_sku' => $orderItem['product_sku'],
            'quantity' => $quantity,
            'unit_price' => $orderItem['unit_price'],
            'total_price' => $quantity * $orderItem['unit_price'],
            'reason' => $aggregatedItem['reason']
        ];
    }

    return ['valid' => true, 'items' => $validatedItems];
}

function calculateReturnAmount(array $returnItems): float {
    $total = 0.00;
    foreach ($returnItems as $item) {
        $total += (float)$item['total_price'];
    }

    return round($total, 2);
}

function createReturnRecord(PDO $db, int $orderId, int $customerId, string $reason, float $returnAmount): int {
    $returnNumber = 'RET-' . date('Y') . '-' . str_pad((string)rand(1, 99999), 5, '0', STR_PAD_LEFT);

    $query = "INSERT INTO customer_returns
              (return_number, order_id, customer_id, return_reason, return_amount, status)
              VALUES
              (:return_number, :order_id, :customer_id, :reason, :amount, 'requested')";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':return_number', $returnNumber);
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->bindParam(':reason', $reason);
    $stmt->bindParam(':amount', $returnAmount);
    $stmt->execute();

    return (int)$db->lastInsertId();
}

function createReturnItems(PDO $db, int $returnId, array $returnItems): void {
    $query = "INSERT INTO customer_return_items
              (return_id, product_id, product_name, product_sku, quantity, unit_price, return_reason)
              VALUES
              (:return_id, :product_id, :product_name, :product_sku, :quantity, :unit_price, :reason)";

    $stmt = $db->prepare($query);

    foreach ($returnItems as $item) {
        $stmt->bindValue(':return_id', $returnId, PDO::PARAM_INT);
        $stmt->bindValue(':product_id', $item['product_id'], PDO::PARAM_INT);
        $stmt->bindValue(':product_name', $item['product_name']);
        $stmt->bindValue(':product_sku', $item['product_sku']);
        $stmt->bindValue(':quantity', $item['quantity'], PDO::PARAM_INT);
        $stmt->bindValue(':unit_price', $item['unit_price']);
        $stmt->bindValue(':reason', $item['reason']);
        $stmt->execute();
    }
}
