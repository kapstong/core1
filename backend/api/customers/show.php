<?php
/**
 * Customer Show API Endpoint
 * GET /backend/api/customers/show.php?id=123 - Get single customer details
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

CORS::handle();

// Check authentication first
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Check if user has permission to view customers (admin or inventory_manager)
if (!in_array($user['role'], ['admin', 'inventory_manager'])) {
    Response::error('Access denied. Admin or inventory manager role required', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        getCustomer();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Failed to retrieve customer: ' . $e->getMessage());
}

function getCustomer() {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        Response::error('Customer ID is required', 400);
    }

    $customerId = (int)$_GET['id'];
    $db = Database::getInstance()->getConnection();

    // Get customer details
    $query = "SELECT id, email, first_name, last_name, phone, date_of_birth, gender,
                     is_active, email_verified, last_login, created_at, updated_at
              FROM customers
              WHERE id = :id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $customerId, PDO::PARAM_INT);
    $stmt->execute();

    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        Response::error('Customer not found', 404);
    }

    // Get customer addresses
    $addressQuery = "SELECT * FROM customer_addresses WHERE customer_id = :customer_id ORDER BY is_default DESC, id DESC";
    $addressStmt = $db->prepare($addressQuery);
    $addressStmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $addressStmt->execute();
    $customer['addresses'] = $addressStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get order statistics
    $statsQuery = "SELECT
                      COUNT(*) as total_orders,
                      SUM(total_amount) as total_spent,
                      SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders
                   FROM customer_orders
                   WHERE customer_id = :customer_id";

    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $statsStmt->execute();
    $customer['statistics'] = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Get recent orders
    $ordersQuery = "SELECT id, order_number, order_date, total_amount, status, payment_status
                    FROM customer_orders
                    WHERE customer_id = :customer_id
                    ORDER BY order_date DESC
                    LIMIT 10";

    $ordersStmt = $db->prepare($ordersQuery);
    $ordersStmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $ordersStmt->execute();
    $customer['recent_orders'] = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['customer' => $customer]);
}
