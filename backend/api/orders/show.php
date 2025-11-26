<?php
/**
 * Show Order Details API Endpoint
 * GET /backend/api/orders/show.php?id=123
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Check authentication
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

try {
    if (!isset($_GET['id'])) {
        Response::error('Order ID is required');
    }

    $orderId = intval($_GET['id']);
    $db = Database::getInstance()->getConnection();

    // Get order details
    $stmt = $db->prepare("
        SELECT o.*,
               sa.address_line_1 as shipping_address_1, sa.address_line_2 as shipping_address_2,
               sa.city as shipping_city, sa.state as shipping_state, sa.postal_code as shipping_postal,
               sa.country as shipping_country, sa.phone as shipping_phone,
               ba.address_line_1 as billing_address_1, ba.address_line_2 as billing_address_2,
               ba.city as billing_city, ba.state as billing_state, ba.postal_code as billing_postal,
               ba.country as billing_country,
               c.first_name, c.last_name, c.email, c.phone as customer_phone
        FROM customer_orders o
        LEFT JOIN customer_addresses sa ON o.shipping_address_id = sa.id
        LEFT JOIN customer_addresses ba ON o.billing_address_id = ba.id
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = :id
    ");
    $stmt->bindParam(':id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        Response::error('Order not found', 404);
    }

    // Get order items
    $stmt = $db->prepare("
        SELECT oi.*, p.name, p.sku, p.image_url
        FROM customer_order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = :order_id
    ");
    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $order['items'] = $items;

    Response::success($order, 'Order retrieved successfully');

} catch (Exception $e) {
    error_log("Show Order Error: " . $e->getMessage());
    Response::error('An error occurred while fetching order: ' . $e->getMessage(), 500);
}
