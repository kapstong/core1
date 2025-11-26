<?php

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';

// Verify authentication
$auth = new Auth();
if (!Auth::check()) {
    Response::error('Unauthorized access', 401);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get recent sales (last 30 days)
    $query = "SELECT 
                s.id as order_id,
                s.created_at,
                s.total,
                s.status,
                c.name as customer_name
              FROM sales s
              LEFT JOIN customers c ON s.customer_id = c.id
              WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              ORDER BY s.created_at DESC
              LIMIT 50";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success('Recent sales retrieved successfully', ['sales' => $sales]);
} catch (Exception $e) {
    Response::error('Failed to fetch recent sales: ' . $e->getMessage());
}
