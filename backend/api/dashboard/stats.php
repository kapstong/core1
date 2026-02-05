<?php
/**
 * Dashboard Stats API Endpoint
 * GET /backend/api/dashboard/stats.php - Get dashboard statistics
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

// Check authentication first
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $db = Database::getInstance();

    // Get inventory stats
    $inventoryStats = [
        'total_products' => (int)$db->fetchValue("SELECT COUNT(*) FROM products WHERE is_active = 1"),
        'total_categories' => (int)$db->fetchValue("SELECT COUNT(*) FROM categories WHERE is_active = 1"),
        'low_stock_count' => (int)$db->fetchValue("
            SELECT COUNT(*)
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.is_active = 1 AND COALESCE(i.quantity_available, 0) <= p.reorder_level
        "),
        'out_of_stock_count' => (int)$db->fetchValue("
            SELECT COUNT(*)
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.is_active = 1 AND COALESCE(i.quantity_available, 0) = 0
        "),
        'total_value' => (float)$db->fetchValue("
            SELECT COALESCE(SUM(p.cost_price * COALESCE(i.quantity_on_hand, 0)), 0)
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.is_active = 1
        ")
    ];

    // Get sales stats
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');
    $thisYear = date('Y');

    $salesStats = [
        'today' => [
            'count' => (int)$db->fetchValue("SELECT COUNT(*) FROM sales WHERE DATE(sale_date) = ?", [$today]),
            'total' => (float)$db->fetchValue("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE(sale_date) = ?", [$today])
        ],
        'this_month' => [
            'count' => (int)$db->fetchValue("SELECT COUNT(*) FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?", [$thisMonth]),
            'total' => (float)$db->fetchValue("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?", [$thisMonth])
        ],
        'this_year' => [
            'count' => (int)$db->fetchValue("SELECT COUNT(*) FROM sales WHERE YEAR(sale_date) = ?", [$thisYear]),
            'total' => (float)$db->fetchValue("SELECT COALESCE(SUM(total_amount), 0) FROM sales WHERE YEAR(sale_date) = ?", [$thisYear])
        ]
    ];

    // Get purchase orders stats
    $purchaseOrdersStats = [
        'pending_count' => (int)$db->fetchValue("SELECT COUNT(*) FROM purchase_orders WHERE status = 'pending'")
    ];

    // Get system stats
    $systemStats = [
        'total_suppliers' => (int)$db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'supplier'"),
        'total_users' => (int)$db->fetchValue("SELECT COUNT(*) FROM users WHERE is_active = 1")
    ];

    // Get recent sales (last 5)
    $recentSales = $db->fetchAll("
        SELECT s.id, s.invoice_number, s.total_amount, s.sale_date,
               u.full_name as cashier_name
        FROM sales s
        LEFT JOIN users u ON s.cashier_id = u.id
        ORDER BY s.sale_date DESC
        LIMIT 5
    ");

    // Get top products (by sales quantity)
    $topProducts = $db->fetchAll("
        SELECT p.name, SUM(si.quantity) as total_sold,
               SUM(si.quantity * si.unit_price) as total_revenue
        FROM sale_items si
        LEFT JOIN products p ON si.product_id = p.id
        LEFT JOIN sales s ON si.sale_id = s.id
        WHERE s.sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 5
    ");

    Response::success([
        'inventory' => $inventoryStats,
        'sales' => $salesStats,
        'purchase_orders' => $purchaseOrdersStats,
        'system' => $systemStats,
        'recent_sales' => $recentSales,
        'top_products' => $topProducts
    ]);

} catch (Exception $e) {
    error_log('Dashboard Stats Error: ' . $e->getMessage());
    Response::serverError('Failed to fetch dashboard stats: ' . $e->getMessage());
}

