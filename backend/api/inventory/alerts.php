<?php
/**
 * Inventory Alerts API Endpoint
 * GET /backend/api/inventory/alerts.php - Get inventory alerts and low stock warnings
 */

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

// Check if user has permission to view inventory
if (!in_array($user['role'], ['admin', 'inventory_manager', 'staff'])) {
    Response::error('Access denied', 403);
}

try {
    $db = Database::getInstance()->getConnection();

    $alerts = [];

    // Out of stock items (quantity = 0)
    $outOfStockQuery = "
        SELECT
            p.id,
            p.name,
            p.sku,
            p.reorder_level,
            COALESCE(i.quantity_on_hand, 0) as stock_quantity,
            c.name as category_name,
            p.selling_price
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        AND (i.quantity_on_hand IS NULL OR i.quantity_on_hand = 0)
        ORDER BY p.name ASC
    ";

    $stmt = $db->prepare($outOfStockQuery);
    $stmt->execute();
    $outOfStock = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $alerts['out_of_stock'] = array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'sku' => $item['sku'],
            'stock_quantity' => (int)$item['stock_quantity'],
            'low_stock_threshold' => (int)$item['reorder_level'],
            'category' => $item['category_name'] ?: 'N/A',
            'selling_price' => (float)$item['selling_price']
        ];
    }, $outOfStock);

    // Low stock items (quantity <= reorder_level)
    $lowStockQuery = "
        SELECT
            p.id,
            p.name,
            p.sku,
            p.reorder_level,
            COALESCE(i.quantity_on_hand, 0) as stock_quantity,
            c.name as category_name,
            p.selling_price
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        AND i.quantity_on_hand > 0
        AND i.quantity_on_hand <= p.reorder_level
        ORDER BY i.quantity_on_hand ASC, p.name ASC
    ";

    $stmt = $db->prepare($lowStockQuery);
    $stmt->execute();
    $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $alerts['low_stock'] = array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'sku' => $item['sku'],
            'stock_quantity' => (int)$item['stock_quantity'],
            'low_stock_threshold' => (int)$item['reorder_level'],
            'category' => $item['category_name'] ?: 'N/A',
            'selling_price' => (float)$item['selling_price']
        ];
    }, $lowStock);

    // Items to reorder (quantity <= reorder_level * 1.5, but still have some stock)
    $reorderQuery = "
        SELECT
            p.id,
            p.name,
            p.sku,
            p.reorder_level,
            COALESCE(i.quantity_on_hand, 0) as stock_quantity,
            c.name as category_name,
            p.selling_price
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
        AND i.quantity_on_hand > 0
        AND i.quantity_on_hand <= (p.reorder_level * 1.5)
        ORDER BY i.quantity_on_hand ASC, p.name ASC
    ";

    $stmt = $db->prepare($reorderQuery);
    $stmt->execute();
    $toReorder = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $alerts['to_reorder'] = array_map(function($item) {
        return [
            'id' => $item['id'],
            'name' => $item['name'],
            'sku' => $item['sku'],
            'stock_quantity' => (int)$item['stock_quantity'],
            'low_stock_threshold' => (int)$item['reorder_level'],
            'category' => $item['category_name'] ?: 'N/A',
            'selling_price' => (float)$item['selling_price']
        ];
    }, $toReorder);

    // Statistics
    $stats = [
        'out_of_stock_count' => count($alerts['out_of_stock']),
        'low_stock_count' => count($alerts['low_stock']),
        'reorder_count' => count($alerts['to_reorder']),
        'value_at_risk' => array_reduce($alerts['out_of_stock'], function($sum, $item) {
            return $sum + ($item['selling_price'] * $item['stock_quantity']);
        }, 0.0)
    ];

    Response::success([
        'alerts' => $alerts,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    Response::serverError('Failed to load inventory alerts: ' . $e->getMessage());
}
