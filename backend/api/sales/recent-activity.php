<?php
/**
 * Sales Recent Activity API Endpoint
 * GET /backend/api/sales/recent-activity.php - Get recent sales activity
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

    // Get recent sales activity (last 20 activities)
    $activityQuery = "
        SELECT
            s.id,
            s.invoice_number,
            s.total_amount,
            s.sale_date,
            s.payment_method,
            u.username as cashier_name,
            COUNT(si.id) as item_count
        FROM sales s
        LEFT JOIN users u ON s.cashier_id = u.id
        LEFT JOIN sale_items si ON s.id = si.sale_id
        GROUP BY s.id, s.invoice_number, s.total_amount, s.sale_date, s.payment_method, u.username
        ORDER BY s.sale_date DESC
        LIMIT 20
    ";

    $stmt = $db->prepare($activityQuery);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = array_map(function($activity) {
        return [
            'id' => $activity['id'],
            'description' => "Sale #{$activity['invoice_number']} - " . formatCurrency($activity['total_amount']),
            'created_at' => $activity['sale_date'],
            'cashier' => $activity['cashier_name'] ?: 'System',
            'payment_method' => $activity['payment_method'],
            'item_count' => (int)$activity['item_count'],
            'amount' => (float)$activity['total_amount']
        ];
    }, $activities);

    Response::success(['activities' => $result]);

} catch (Exception $e) {
    Response::serverError('Failed to load recent sales activity: ' . $e->getMessage());
}

// Helper function for currency formatting
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

