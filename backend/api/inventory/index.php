<?php
/**
 * Inventory API Endpoint
 * GET /backend/api/inventory/index.php - Get all inventory with product details
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
    $conn = $db->getConnection();

    // Build query
    $query = "
        SELECT
            i.id,
            i.product_id,
            i.quantity_on_hand,
            i.quantity_reserved,
            i.quantity_available,
            i.warehouse_location,
            i.last_updated,
            p.sku,
            p.name as product_name,
            p.brand,
            p.image_url,
            p.cost_price,
            p.selling_price,
            p.reorder_level,
            c.name as category_name
        FROM inventory i
        INNER JOIN products p ON i.product_id = p.id
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
    ";

    // Optional filters
    $conditions = [];
    $params = [];

    if (isset($_GET['low_stock']) && $_GET['low_stock'] == '1') {
        $conditions[] = "i.quantity_available <= p.reorder_level";
    }

    if (isset($_GET['category_id'])) {
        $conditions[] = "p.category_id = :category_id";
        $params[':category_id'] = (int)$_GET['category_id'];
    }

    if (isset($_GET['search'])) {
        $conditions[] = "(p.name LIKE :search OR p.sku LIKE :search OR p.brand LIKE :search)";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY i.quantity_available ASC, p.name ASC";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $totalProducts = count($inventory);
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $totalValue = 0;

    foreach ($inventory as &$item) {
        $item['stock_value'] = $item['quantity_on_hand'] * $item['cost_price'];
        $totalValue += $item['stock_value'];

        $item['stock_status'] = 'in_stock';
        if ($item['quantity_available'] <= 0) {
            $item['stock_status'] = 'out_of_stock';
            $outOfStockCount++;
        } elseif ($item['quantity_available'] <= $item['reorder_level']) {
            $item['stock_status'] = 'low_stock';
            $lowStockCount++;
        }
    }

    Response::success([
        'inventory' => $inventory,
        'stats' => [
            'total_products' => $totalProducts,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'total_stock_value' => round($totalValue, 2)
        ]
    ]);

} catch (Exception $e) {
    Response::serverError('Failed to fetch inventory');
}
