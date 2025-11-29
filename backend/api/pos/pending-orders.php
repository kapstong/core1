<?php
/**
 * POS - Pending Orders API
 * GET /backend/api/pos/pending-orders.php - Retrieve all pending customer orders
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

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check authentication
    if (!Auth::check()) {
        Response::error('Authentication required', 401);
        exit;
    }

    // Check authorization - only staff can view pending orders
    $user = Auth::user();
    $allowedRoles = ['staff', 'inventory_manager', 'purchasing_officer', 'admin'];
    
    if (!in_array($user['role'], $allowedRoles)) {
        Response::error('Unauthorized: Staff access required', 403);
        exit;
    }

    $db = Database::getInstance()->getConnection();

    // Query all pending orders with customer info
    $query = "
        SELECT
            co.id,
            co.order_number,
            co.status,
            co.created_at,
            co.order_date,
            co.subtotal,
            co.tax_amount,
            co.shipping_amount,
            co.total_amount,
            co.payment_method,
            co.payment_status,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.email,
            c.phone
        FROM customer_orders co
        INNER JOIN customers c ON co.customer_id = c.id
        WHERE co.status = 'pending'
        ORDER BY co.created_at DESC
        LIMIT 100
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get item count for each order
    foreach ($orders as &$order) {
        $itemStmt = $db->prepare("
            SELECT COUNT(*) as items_count FROM customer_order_items WHERE order_id = ?
        ");
        $itemStmt->execute([$order['id']]);
        $itemCount = $itemStmt->fetch(PDO::FETCH_ASSOC);
        $order['items_count'] = $itemCount['items_count'] ?? 0;
    }

    Response::success([
        'orders' => $orders,
        'total_count' => count($orders)
    ]);

} catch (Exception $e) {
    error_log('Pending Orders API Error: ' . $e->getMessage());
    Response::serverError('Failed to retrieve pending orders: ' . $e->getMessage());
}
?>
