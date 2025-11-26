<?php
/**
 * Customer Delete API Endpoint
 * DELETE /backend/api/customers/delete.php - Deactivate customer account
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';

CORS::handle();

// Require authentication
Auth::requireAuth();

// Check permissions - admin or inventory_manager can manage customers
$user = Auth::user();
if (!in_array($user['role'], ['admin', 'inventory_manager'])) {
    Response::error('Access denied. Admin or inventory manager role required', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'DELETE') {
        deleteCustomer();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Failed to delete customer: ' . $e->getMessage());
}

function deleteCustomer() {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        Response::error('Customer ID is required', 400);
    }

    $customerId = (int)$_GET['id'];
    $db = Database::getInstance()->getConnection();

    // Verify customer exists
    $checkQuery = "SELECT id, email, first_name, last_name FROM customers WHERE id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $customerId, PDO::PARAM_INT);
    $checkStmt->execute();

    $customer = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        Response::error('Customer not found', 404);
    }

    // Check if customer has orders
    $ordersQuery = "SELECT COUNT(*) as order_count FROM customer_orders WHERE customer_id = :id";
    $ordersStmt = $db->prepare($ordersQuery);
    $ordersStmt->bindParam(':id', $customerId, PDO::PARAM_INT);
    $ordersStmt->execute();

    $orderCheck = $ordersStmt->fetch(PDO::FETCH_ASSOC);

    // Instead of hard delete, deactivate the account if they have orders
    if ($orderCheck['order_count'] > 0) {
        $query = "UPDATE customers SET is_active = 0 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $customerId, PDO::PARAM_INT);
        $stmt->execute();

        Response::success([
            'message' => 'Customer account deactivated successfully (has existing orders)',
            'customer_id' => $customerId,
            'action' => 'deactivated',
            'order_count' => $orderCheck['order_count']
        ]);
    } else {
        // Can safely delete if no orders
        $query = "DELETE FROM customers WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $customerId, PDO::PARAM_INT);
        $stmt->execute();

        Response::success([
            'message' => 'Customer deleted successfully',
            'customer_id' => $customerId,
            'action' => 'deleted'
        ]);
    }
}
