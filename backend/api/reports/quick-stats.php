<?php
/**
 * Quick Stats API Endpoint
 * GET /backend/api/reports/quick-stats.php - Get quick statistics for dashboard
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

// Check if user has permission to view reports
if (!in_array($user['role'], ['admin', 'inventory_manager', 'staff'])) {
    Response::error('Access denied', 403);
}

try {
    $db = Database::getInstance()->getConnection();

    $stats = [];

    // Total sales this month
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    $salesQuery = "
        SELECT
            COUNT(*) as total_sales,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(AVG(total_amount), 0) as avg_sale
        FROM sales
        WHERE DATE(sale_date) BETWEEN :start AND :end
    ";

    $stmt = $db->prepare($salesQuery);
    $stmt->bindParam(':start', $monthStart);
    $stmt->bindParam(':end', $monthEnd);
    $stmt->execute();
    $sales = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['month_sales'] = (float)$sales['total_revenue'];

    // Top product this month
    $topProductQuery = "
        SELECT
            p.name,
            SUM(si.quantity) as total_sold
        FROM sale_items si
        INNER JOIN products p ON si.product_id = p.id
        INNER JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.sale_date) BETWEEN :start AND :end
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 1
    ";

    $stmt = $db->prepare($topProductQuery);
    $stmt->bindParam(':start', $monthStart);
    $stmt->bindParam(':end', $monthEnd);
    $stmt->execute();
    $topProduct = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['top_product'] = $topProduct ? $topProduct['name'] : 'N/A';

    // Low stock items count
    $lowStockQuery = "
        SELECT COUNT(*) as low_stock_count
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE p.is_active = 1
        AND (i.quantity_on_hand IS NULL OR i.quantity_on_hand <= p.reorder_level)
    ";

    $stmt = $db->prepare($lowStockQuery);
    $stmt->execute();
    $lowStock = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['low_stock_count'] = (int)$lowStock['low_stock_count'];

    // Pending orders count
    $pendingOrdersQuery = "
        SELECT COUNT(*) as pending_count
        FROM purchase_orders
        WHERE status = 'pending_approval'
    ";

    $stmt = $db->prepare($pendingOrdersQuery);
    $stmt->execute();
    $pendingOrders = $stmt->fetch(PDO::FETCH_ASSOC);

    $stats['pending_orders'] = (int)$pendingOrders['pending_count'];

    Response::success($stats);

} catch (Exception $e) {
    Response::serverError('Failed to load quick stats: ' . $e->getMessage());
}

