<?php
/**
 * Test Pending Orders API
 */
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

// Check auth
if (!Auth::check()) {
    die('Not authenticated');
}

$user = Auth::user();
echo "User: " . json_encode($user) . "\n";
echo "Role: " . $user['role'] . "\n";

// Check allowed roles
$allowedRoles = ['staff', 'inventory_manager', 'purchasing_officer', 'admin'];
echo "Allowed: " . (in_array($user['role'], $allowedRoles) ? 'YES' : 'NO') . "\n";

// Try the query
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    SELECT COUNT(*) as count FROM customer_orders WHERE status = 'pending'
");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Pending orders count: " . $result['count'] . "\n";

// Get sample data
$stmt = $db->prepare("
    SELECT
        co.id,
        co.order_number,
        co.status,
        co.created_at,
        CONCAT(c.first_name, ' ', c.last_name) as customer_name
    FROM customer_orders co
    INNER JOIN customers c ON co.customer_id = c.id
    WHERE co.status = 'pending'
    LIMIT 5
");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Sample orders: " . json_encode($orders) . "\n";
?>
