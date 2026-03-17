<?php
/**
 * Online Orders Management API Endpoint
 * GET/POST/PUT /backend/api/orders/online.php - Manage online customer orders
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication first
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

Auth::requireRole(['admin', 'inventory_manager', 'purchasing_officer', 'staff']);

// Get user data
$user = Auth::user();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getOnlineOrders();
            break;

        case 'PUT':
            updateOnlineOrder();
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Order operation failed: ' . $e->getMessage());
}

function getOnlineOrders() {
    $db = Database::getInstance()->getConnection();

    // Build query
    $query = "
        SELECT
            co.*,
            c.first_name,
            c.last_name,
            c.email,
            sa.first_name as shipping_first_name,
            sa.last_name as shipping_last_name,
            sa.address_line_1 as shipping_address_1,
            sa.city as shipping_city,
            sa.postal_code as shipping_postal,
            sa.country as shipping_country,
            ba.first_name as billing_first_name,
            ba.last_name as billing_last_name,
            ba.address_line_1 as billing_address_1,
            ba.city as billing_city,
            ba.postal_code as billing_postal,
            ba.country as billing_country,
            COALESCE(order_items.item_count, 0) as item_count,
            COALESCE(order_items.total_quantity, 0) as total_quantity
        FROM customer_orders co
        INNER JOIN customers c ON co.customer_id = c.id
        LEFT JOIN customer_addresses sa ON co.shipping_address_id = sa.id
        LEFT JOIN customer_addresses ba ON co.billing_address_id = ba.id
        LEFT JOIN (
            SELECT
                order_id,
                COUNT(*) as item_count,
                SUM(quantity) as total_quantity
            FROM customer_order_items
            GROUP BY order_id
        ) order_items ON co.id = order_items.order_id
    ";

    // Optional filters
    $conditions = [];
    $params = [];

    if (isset($_GET['status'])) {
        $conditions[] = "co.status = :status";
        $params[':status'] = $_GET['status'];
    }

    if (isset($_GET['payment_status'])) {
        $conditions[] = "co.payment_status = :payment_status";
        $params[':payment_status'] = $_GET['payment_status'];
    }

    if (isset($_GET['start_date'])) {
        $conditions[] = "DATE(co.created_at) >= :start_date";
        $params[':start_date'] = $_GET['start_date'];
    }

    if (isset($_GET['end_date'])) {
        $conditions[] = "DATE(co.created_at) <= :end_date";
        $params[':end_date'] = $_GET['end_date'];
    }

    if (isset($_GET['search'])) {
        $conditions[] = "(co.order_number LIKE :search OR c.first_name LIKE :search OR c.last_name LIKE :search OR c.email LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY co.created_at DESC LIMIT 100";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get order items for each order if requested
    if (isset($_GET['include_items']) && $_GET['include_items'] == '1') {
        foreach ($orders as &$order) {
            $itemsQuery = "
                SELECT coi.*, p.name as product_name, p.sku, p.image_url
                FROM customer_order_items coi
                INNER JOIN products p ON coi.product_id = p.id
                WHERE coi.order_id = :order_id
                ORDER BY coi.id
            ";
            $itemsStmt = $db->prepare($itemsQuery);
            $itemsStmt->bindValue(':order_id', $order['id'], PDO::PARAM_INT);
            $itemsStmt->execute();
            $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Calculate statistics
    $stats = [
        'total_orders' => count($orders),
        'pending_orders' => 0,
        'processing_orders' => 0,
        'shipped_orders' => 0,
        'delivered_orders' => 0,
        'cancelled_orders' => 0,
        'total_revenue' => 0
    ];

    foreach ($orders as $order) {
        switch ($order['status']) {
            case 'pending': $stats['pending_orders']++; break;
            case 'confirmed': $stats['pending_orders']++; break;
            case 'processing': $stats['processing_orders']++; break;
            case 'shipped': $stats['shipped_orders']++; break;
            case 'delivered': $stats['delivered_orders']++; break;
            case 'cancelled': $stats['cancelled_orders']++; break;
            case 'returned': $stats['cancelled_orders']++; break;
        }

        if (in_array($order['status'], ['confirmed', 'processing', 'shipped', 'delivered'])) {
            $stats['total_revenue'] += $order['total_amount'];
        }
    }

    $stats['total_revenue'] = round($stats['total_revenue'], 2);

    Response::success([
        'orders' => $orders,
        'stats' => $stats
    ]);
}

function updateOnlineOrder() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    if (!isset($input['order_id']) || !isset($input['status'])) {
        Response::error('order_id and status are required', 400);
    }

    $orderId = (int)$input['order_id'];
    $newStatus = $input['status'];
    $notes = $input['notes'] ?? null;

    $validStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'];
    if (!in_array($newStatus, $validStatuses)) {
        Response::error('Invalid status', 400);
    }

    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    try {
        // Get current order status
        $currentQuery = "SELECT status, customer_id, order_number, payment_status FROM customer_orders WHERE id = :order_id";
        $currentStmt = $db->prepare($currentQuery);
        $currentStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $currentStmt->execute();
        $currentOrder = $currentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentOrder) {
            throw new Exception('Order not found');
        }

        if ($currentOrder['status'] === 'pending' && $newStatus === 'confirmed') {
            throw new Exception('Pending orders must be processed via the approval workflow');
        }

        // Update order status
        $updateQuery = "UPDATE customer_orders SET status = :status, updated_at = NOW() WHERE id = :order_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindValue(':status', $newStatus);
        $updateStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $updateStmt->execute();

        // Log the status change
        $logQuery = "
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details)
            VALUES (:user_id, 'order_status_update', 'customer_order', :order_id, :details)
        ";
        $logStmt = $db->prepare($logQuery);
        $logStmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $logStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $details = json_encode([
            'old_status' => $currentOrder['status'],
            'new_status' => $newStatus,
            'notes' => $notes
        ]);
        $logStmt->bindValue(':details', $details);
        $logStmt->execute();

        // If order is cancelled, restore inventory
        if ($newStatus === 'cancelled' && $currentOrder['status'] !== 'cancelled') {
            restoreOrderInventory($db, $orderId);

            if (($currentOrder['payment_status'] ?? null) === 'paid') {
                $refundStmt = $db->prepare("
                    UPDATE customer_orders
                    SET payment_status = 'refunded',
                        updated_at = NOW()
                    WHERE id = :order_id
                ");
                $refundStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
                $refundStmt->execute();
            }
        }

        // Send email notification to customer (if email functionality exists)
        // This would integrate with the Email utility

        $db->commit();

        Response::success([
            'message' => 'Order status updated successfully',
            'order_id' => $orderId,
            'new_status' => $newStatus
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function restoreOrderInventory($db, $orderId) {
    // Get order items
    $itemsQuery = "SELECT product_id, quantity FROM customer_order_items WHERE order_id = :order_id";
    $itemsStmt = $db->prepare($itemsQuery);
    $itemsStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $itemsStmt->execute();
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($orderItems as $item) {
        $inventoryQuery = "SELECT quantity_on_hand, quantity_reserved FROM inventory WHERE product_id = :product_id LIMIT 1";
        $inventoryStmt = $db->prepare($inventoryQuery);
        $inventoryStmt->bindValue(':product_id', $item['product_id'], PDO::PARAM_INT);
        $inventoryStmt->execute();
        $inventory = $inventoryStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0
        ];

        $quantity = (int)$item['quantity'];
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
            ':product_id' => $item['product_id'],
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
                    'CUSTOMER_ORDER', :order_id, :performed_by, :notes
                )
            ";
            $movementStmt = $db->prepare($movementQuery);
            $movementStmt->execute([
                ':product_id' => $item['product_id'],
                ':quantity' => $onHandRestore,
                ':quantity_before' => $quantityOnHand,
                ':quantity_after' => $quantityOnHand + $onHandRestore,
                ':order_id' => $orderId,
                ':performed_by' => $_SESSION['user_id'],
                ':notes' => 'Order cancellation - inventory restored'
            ]);
        }
    }
}
?>

