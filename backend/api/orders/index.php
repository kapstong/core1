<?php
/**
 * Staff Orders Management API Endpoint
 * GET /backend/api/orders/index.php - List all customer orders (staff access)
 * GET /backend/api/orders/index.php?id=X - Get specific order details
 * PUT /backend/api/orders/index.php - Update order status (staff access)
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication (any authenticated user can view orders for now)
Auth::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getOrderDetails($_GET['id']);
        } else {
            getOrders();
        }
        break;
    case 'PUT':
        updateOrderStatus();
        break;
    default:
        Response::error('Method not allowed', 405);
}

function getOrders() {
    try {
        $db = Database::getInstance()->getConnection();

        // Build query with filters
        $query = "
            SELECT
                co.*,
                c.first_name, c.last_name, c.email,
                COUNT(coi.id) as total_items
            FROM customer_orders co
            INNER JOIN customers c ON co.customer_id = c.id
            LEFT JOIN customer_order_items coi ON co.id = coi.order_id
            WHERE 1=1
        ";

        $params = [];

        // Add filters
        if (!empty($_GET['status'])) {
            $query .= " AND co.status = :status";
            $params['status'] = $_GET['status'];
        }

        if (!empty($_GET['payment_status'])) {
            $query .= " AND co.payment_status = :payment_status";
            $params['payment_status'] = $_GET['payment_status'];
        }

        if (!empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $query .= " AND (co.order_number LIKE :search OR c.first_name LIKE :search OR c.last_name LIKE :search OR c.email LIKE :search)";
            $params['search'] = $search;
        }

        $query .= " GROUP BY co.id ORDER BY co.created_at DESC";

        // Add pagination
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $query .= " LIMIT :limit OFFSET :offset";
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $stmt = $db->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($orders);

    } catch (Exception $e) {
        error_log('Error fetching orders: ' . $e->getMessage());
        Response::error('Failed to fetch orders', 500);
    }
}

function getOrderDetails($orderId) {
    try {
        $db = Database::getInstance()->getConnection();

        $query = "
            SELECT
                co.*,
                c.first_name, c.last_name, c.email, c.phone,
                sa.first_name as shipping_first_name, sa.last_name as shipping_last_name,
                sa.address_line_1 as shipping_address_line1, sa.address_line_2 as shipping_address_line2,
                sa.city as shipping_city, sa.state as shipping_state, sa.postal_code as shipping_postal_code,
                sa.country as shipping_country,
                ba.first_name as billing_first_name, ba.last_name as billing_last_name,
                ba.address_line_1 as billing_address_line1, ba.address_line_2 as billing_address_line2,
                ba.city as billing_city, ba.state as billing_state, ba.postal_code as billing_postal_code,
                ba.country as billing_country
            FROM customer_orders co
            INNER JOIN customers c ON co.customer_id = c.id
            LEFT JOIN customer_addresses sa ON co.shipping_address_id = sa.id
            LEFT JOIN customer_addresses ba ON co.billing_address_id = ba.id
            WHERE co.id = :order_id
        ";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();

        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            Response::error('Order not found', 404);
        }

        // Get order items
        $itemsQuery = "
            SELECT coi.*, p.name as product_name, p.sku as product_sku
            FROM customer_order_items coi
            LEFT JOIN products p ON coi.product_id = p.id
            WHERE coi.order_id = :order_id
            ORDER BY coi.id
        ";

        $itemsStmt = $db->prepare($itemsQuery);
        $itemsStmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $itemsStmt->execute();
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($order);

    } catch (Exception $e) {
        error_log('Error fetching order details: ' . $e->getMessage());
        Response::error('Failed to fetch order details', 500);
    }
}

function updateOrderStatus() {
    // Check staff authentication for updates (admin, inventory_manager, staff, or purchasing_officer)
    if (!Auth::isInventoryManager() && !Auth::hasRole('staff') && !Auth::hasRole('purchasing_officer')) {
        Response::error('Staff access required for order updates', 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['id']) || !isset($input['status'])) {
        Response::error('Order ID and status are required', 400);
    }

    $orderId = (int)$input['id'];
    $newStatus = $input['status'];
    $trackingNumber = $input['tracking_number'] ?? null;
    $notes = $input['notes'] ?? null;

    // Validate status
    $validStatuses = ['confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'];
    if (!in_array($newStatus, $validStatuses)) {
        Response::error('Invalid status', 400);
    }

    try {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        // Update order status
        $query = "UPDATE customer_orders SET status = :status";
        $params = ['status' => $newStatus, 'id' => $orderId];

        if ($trackingNumber) {
            $query .= ", tracking_number = :tracking_number";
            $params['tracking_number'] = $trackingNumber;
        }

        if ($newStatus === 'shipped' && $trackingNumber) {
            $query .= ", shipped_date = NOW()";
        } elseif ($newStatus === 'delivered') {
            $query .= ", delivered_date = NOW()";
        }

        $query .= " WHERE id = :id";

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        // Log the status change
        $logQuery = "
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
            VALUES (:user_id, 'update', 'order', :order_id, :details, :ip)
        ";

        $logStmt = $db->prepare($logQuery);
        $logStmt->execute([
            'user_id' => $_SESSION['user_id'] ?? 1,
            'order_id' => $orderId,
            'details' => "Status changed to {$newStatus}" . ($trackingNumber ? " (Tracking: {$trackingNumber})" : ""),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ]);

        $db->commit();

        Response::success(['message' => 'Order status updated successfully']);

    } catch (Exception $e) {
        $db->rollBack();
        error_log('Error updating order status: ' . $e->getMessage());
        Response::error('Failed to update order status', 500);
    }
}
?>
