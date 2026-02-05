<?php
/**
 * Sales Payment Methods API Endpoint
 * GET /backend/api/sales/payment-methods.php - Get payment method statistics
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

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

$user = Auth::user();

// Check if user has permission to view sales data
if (!in_array($user['role'], ['admin', 'inventory_manager', 'staff'])) {
    Response::error('Access denied', 403);
}

try {
    $db = Database::getInstance()->getConnection();

    // Get payment method breakdown
    $paymentQuery = "
        SELECT
            payment_method,
            COUNT(*) as transaction_count,
            SUM(total_amount) as total_amount,
            AVG(total_amount) as avg_amount,
            MIN(total_amount) as min_amount,
            MAX(total_amount) as max_amount
        FROM sales
        WHERE payment_status = 'paid'
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ";

    $stmt = $db->prepare($paymentQuery);
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = array_map(function($method) {
        return [
            'method' => $method['payment_method'],
            'count' => (int)$method['transaction_count'],
            'total_amount' => (float)$method['total_amount'],
            'avg_amount' => round((float)$method['avg_amount'], 2),
            'min_amount' => (float)$method['min_amount'],
            'max_amount' => (float)$method['max_amount']
        ];
    }, $paymentMethods);

    Response::success($result);

} catch (Exception $e) {
    Response::serverError('Failed to load payment methods: ' . $e->getMessage());
}

