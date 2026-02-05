<?php
/**
 * Recent Activity API Endpoint
 * GET /backend/api/reports/recent.php - Get recent activity for dashboard
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

    $recent = [];

    // Recent sales (last 10)
    $recentSalesQuery = "
        SELECT
            s.id,
            s.invoice_number,
            s.total_amount,
            s.sale_date,
            u.username as cashier_name,
            COUNT(si.id) as item_count
        FROM sales s
        LEFT JOIN users u ON s.cashier_id = u.id
        LEFT JOIN sale_items si ON s.id = si.sale_id
        GROUP BY s.id, s.invoice_number, s.total_amount, s.sale_date, u.username
        ORDER BY s.sale_date DESC
        LIMIT 10
    ";

    $stmt = $db->prepare($recentSalesQuery);
    $stmt->execute();
    $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $recent['sales'] = array_map(function($sale) {
        return [
            'id' => $sale['id'],
            'invoice_number' => $sale['invoice_number'],
            'total_amount' => (float)$sale['total_amount'],
            'sale_date' => $sale['sale_date'],
            'cashier_name' => $sale['cashier_name'] ?: 'System',
            'item_count' => (int)$sale['item_count']
        ];
    }, $recentSales);

    // Recent purchase orders (last 10)
    $recentPOQuery = "
        SELECT
            po.id,
            po.po_number,
            po.total_amount,
            po.created_at,
            po.status,
            su.full_name as supplier_name,
            u.username as created_by_name
        FROM purchase_orders po
        LEFT JOIN users su ON po.supplier_id = su.id AND su.role = 'supplier'
        LEFT JOIN users u ON po.created_by = u.id
        ORDER BY po.created_at DESC
        LIMIT 10
    ";

    $stmt = $db->prepare($recentPOQuery);
    $stmt->execute();
    $recentPOs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $recent['purchase_orders'] = array_map(function($po) {
        return [
            'id' => $po['id'],
            'po_number' => $po['po_number'],
            'total_amount' => (float)$po['total_amount'],
            'created_at' => $po['created_at'],
            'status' => $po['status'],
            'supplier_name' => $po['supplier_name'] ?: 'N/A',
            'created_by_name' => $po['created_by_name'] ?: 'System'
        ];
    }, $recentPOs);

    // Recent inventory adjustments (last 10)
    $recentAdjustmentsQuery = "
        SELECT
            sa.id,
            sa.adjustment_number,
            sa.adjustment_type,
            sa.quantity_adjusted,
            sa.adjustment_date,
            p.name as product_name,
            u.username as performed_by_name
        FROM stock_adjustments sa
        LEFT JOIN products p ON sa.product_id = p.id
        LEFT JOIN users u ON sa.performed_by = u.id
        ORDER BY sa.adjustment_date DESC
        LIMIT 10
    ";

    $stmt = $db->prepare($recentAdjustmentsQuery);
    $stmt->execute();
    $recentAdjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $recent['adjustments'] = array_map(function($adj) {
        return [
            'id' => $adj['id'],
            'adjustment_number' => $adj['adjustment_number'],
            'adjustment_type' => $adj['adjustment_type'],
            'quantity_adjusted' => (int)$adj['quantity_adjusted'],
            'adjustment_date' => $adj['adjustment_date'],
            'product_name' => $adj['product_name'] ?: 'N/A',
            'performed_by_name' => $adj['performed_by_name'] ?: 'System'
        ];
    }, $recentAdjustments);

    Response::success($recent);

} catch (Exception $e) {
    Response::serverError('Failed to load recent activity: ' . $e->getMessage());
}

