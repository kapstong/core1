<?php
/**
 * Profile Debug Endpoint
 * Temporary debug file to verify customer stats queries
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../models/Customer.php';

CORS::handle();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    if (!isset($_SESSION['customer_id'])) {
        throw new Exception('Not authenticated');
    }

    $customerId = $_SESSION['customer_id'];
    $db = Database::getInstance()->getConnection();
    
    // Get customer basic info
    $customerModel = new Customer();
    $customer = $customerModel->findById($customerId);
    
    // Debug: Get all orders for this customer
    $allOrdersQuery = "SELECT id, customer_id, total_amount, status FROM customer_orders WHERE customer_id = :customer_id";
    $allOrdersStmt = $db->prepare($allOrdersQuery);
    $allOrdersStmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $allOrdersStmt->execute();
    $allOrders = $allOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Get stats
    $statsQuery = "SELECT 
                       COUNT(id) as total_orders,
                       COALESCE(SUM(total_amount), 0) as total_spent
                   FROM customer_orders
                   WHERE customer_id = :customer_id";
    
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'customer_id' => $customerId,
        'customer_name' => $customer['first_name'] . ' ' . $customer['last_name'],
        'all_orders_count' => count($allOrders),
        'all_orders' => $allOrders,
        'stats' => $stats,
        'total_orders_int' => (int)($stats['total_orders'] ?? 0),
        'total_spent_float' => (float)($stats['total_spent'] ?? 0)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
