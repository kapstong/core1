<?php
/**
 * Inventory Reports API Endpoint
 * GET /backend/api/reports/inventory.php - Generate inventory reports
 *
 * Query Parameters:
 * - type: 'low_stock', 'movements', 'valuation', 'by_category', 'aging'
 * - start_date: Start date (YYYY-MM-DD)
 * - end_date: End date (YYYY-MM-DD)
 * - category_id: Filter by category
 * - low_stock_threshold: Custom low stock threshold (default: 10)
 * - limit: Number of records to return (default: 100)
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
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

    // Get parameters
    $reportType = $_GET['type'] ?? 'low_stock';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    $lowStockThreshold = isset($_GET['low_stock_threshold']) ? (int)$_GET['low_stock_threshold'] : 10;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

    // Validate dates
    if (!strtotime($startDate) || !strtotime($endDate)) {
        Response::error('Invalid date format. Use YYYY-MM-DD', 400);
    }

    if (strtotime($startDate) > strtotime($endDate)) {
        Response::error('Start date cannot be after end date', 400);
    }

    $reportData = [];

    switch ($reportType) {
        case 'low_stock':
            $reportData = generateLowStockReport($conn, $lowStockThreshold, $categoryId, $limit);
            break;



        case 'movements':
            $reportData = generateStockMovements($conn, $startDate, $endDate, $categoryId, $limit);
            break;

        case 'valuation':
            $reportData = generateInventoryValuation($conn, $categoryId);
            break;

        case 'by_category':
            $reportData = generateInventoryByCategory($conn);
            break;

        case 'aging':
            $reportData = generateInventoryAging($conn, $categoryId, $limit);
            break;

        default:
            Response::error('Invalid report type. Supported types: low_stock, movements, valuation, by_category, aging', 400);
    }

    Response::success($reportData);

} catch (Exception $e) {
    Response::serverError('Failed to generate report: ' . $e->getMessage());
}

function generateLowStockReport($conn, $threshold, $categoryId, $limit) {
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            p.reorder_level,
            c.name as category_name,
            i.quantity_on_hand,
            i.quantity_available,
            i.quantity_reserved,
            i.last_stock_check,
            i.warehouse_location,
            CASE
                WHEN i.quantity_available <= 0 THEN 'Out of Stock'
                WHEN i.quantity_available <= p.reorder_level THEN 'Low Stock'
                ELSE 'In Stock'
            END as stock_status,
            (p.selling_price * i.quantity_on_hand) as total_value
        FROM inventory i
        INNER JOIN products p ON i.product_id = p.id
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1
    ";

    $params = [];
    $conditions = [];

    if ($categoryId) {
        $conditions[] = "p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    // Include products that are low stock or out of stock
    $conditions[] = "(i.quantity_available <= :threshold OR i.quantity_available <= p.reorder_level)";

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY i.quantity_available ASC, p.name ASC LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':threshold', $threshold, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary
    $summary = [
        'total_products' => count($products),
        'out_of_stock' => 0,
        'low_stock' => 0,
        'total_value' => 0
    ];

    foreach ($products as $product) {
        $summary['total_value'] += $product['total_value'];
        if ($product['quantity_available'] <= 0) {
            $summary['out_of_stock']++;
        } else {
            $summary['low_stock']++;
        }
    }

    return [
        'type' => 'low_stock_report',
        'threshold' => $threshold,
        'summary' => $summary,
        'data' => $products
    ];
}



function generateStockMovements($conn, $startDate, $endDate, $categoryId, $limit) {
    $query = "
        SELECT
            sm.id,
            sm.movement_type,
            sm.quantity,
            sm.quantity_before,
            sm.quantity_after,
            sm.reference_type,
            sm.reference_id,
            sm.notes,
            sm.created_at,
            p.sku,
            p.name as product_name,
            c.name as category_name,
            u.full_name as performed_by_name
        FROM stock_movements sm
        INNER JOIN products p ON sm.product_id = p.id
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN users u ON sm.performed_by = u.id
        WHERE DATE(sm.created_at) BETWEEN :start_date AND :end_date
    ";

    $params = [':start_date' => $startDate, ':end_date' => $endDate];
    $conditions = [];

    if ($categoryId) {
        $conditions[] = "p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY sm.created_at DESC LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary
    $summary = [
        'total_movements' => count($movements),
        'by_type' => []
    ];

    foreach ($movements as $movement) {
        $type = $movement['movement_type'];
        if (!isset($summary['by_type'][$type])) {
            $summary['by_type'][$type] = 0;
        }
        $summary['by_type'][$type]++;
    }

    return [
        'type' => 'stock_movements',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'summary' => $summary,
        'data' => $movements
    ];
}

function generateInventoryValuation($conn, $categoryId) {
    $query = "
        SELECT
            SUM(p.cost_price * i.quantity_on_hand) as total_cost_value,
            SUM(p.selling_price * i.quantity_on_hand) as total_selling_value,
            SUM((p.selling_price - p.cost_price) * i.quantity_on_hand) as total_potential_profit,
            COUNT(DISTINCT p.id) as total_products,
            AVG(p.cost_price) as avg_cost_price,
            AVG(p.selling_price) as avg_selling_price
        FROM inventory i
        INNER JOIN products p ON i.product_id = p.id
        WHERE p.is_active = 1
    ";

    $params = [];
    if ($categoryId) {
        $query .= " AND p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $valuation = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get breakdown by category if no specific category requested
    $categoryBreakdown = [];
    if (!$categoryId) {
        $catQuery = "
            SELECT
                c.name as category_name,
                COUNT(p.id) as product_count,
                SUM(p.cost_price * i.quantity_on_hand) as cost_value,
                SUM(p.selling_price * i.quantity_on_hand) as selling_value
            FROM inventory i
            INNER JOIN products p ON i.product_id = p.id
            INNER JOIN categories c ON p.category_id = c.id
            WHERE p.is_active = 1
            GROUP BY c.id, c.name
            ORDER BY selling_value DESC
        ";

        $catStmt = $conn->prepare($catQuery);
        $catStmt->execute();
        $categoryBreakdown = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return [
        'type' => 'inventory_valuation',
        'valuation' => $valuation,
        'category_breakdown' => $categoryBreakdown
    ];
}

function generateInventoryByCategory($conn) {
    $query = "
        SELECT
            c.id,
            c.name as category_name,
            c.slug,
            COUNT(DISTINCT p.id) as total_products,
            SUM(i.quantity_on_hand) as total_quantity,
            SUM(i.quantity_available) as total_available,
            SUM(i.quantity_reserved) as total_reserved,
            SUM(p.cost_price * i.quantity_on_hand) as total_cost_value,
            SUM(p.selling_price * i.quantity_on_hand) as total_selling_value,
            AVG(p.cost_price) as avg_cost_price,
            AVG(p.selling_price) as avg_selling_price
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
        LEFT JOIN inventory i ON p.id = i.product_id
        GROUP BY c.id, c.name, c.slug
        ORDER BY total_selling_value DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'inventory_by_category',
        'total_categories' => count($categories),
        'data' => $categories
    ];
}

function generateInventoryAging($conn, $categoryId, $limit) {
    // This is a simplified aging report - in a real system you'd track purchase dates
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            c.name as category_name,
            i.quantity_on_hand,
            i.last_stock_check,
            DATEDIFF(CURDATE(), COALESCE(i.last_stock_check, p.created_at)) as days_since_check,
            CASE
                WHEN DATEDIFF(CURDATE(), COALESCE(i.last_stock_check, p.created_at)) <= 30 THEN 'Recent'
                WHEN DATEDIFF(CURDATE(), COALESCE(i.last_stock_check, p.created_at)) <= 90 THEN 'Moderate'
                WHEN DATEDIFF(CURDATE(), COALESCE(i.last_stock_check, p.created_at)) <= 180 THEN 'Old'
                ELSE 'Very Old'
            END as aging_status,
            (p.selling_price * i.quantity_on_hand) as current_value
        FROM inventory i
        INNER JOIN products p ON i.product_id = p.id
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 AND i.quantity_on_hand > 0
    ";

    $params = [];
    if ($categoryId) {
        $query .= " AND p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    $query .= " ORDER BY days_since_check DESC, p.name ASC LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate aging summary
    $agingSummary = [
        'recent' => 0,
        'moderate' => 0,
        'old' => 0,
        'very_old' => 0
    ];

    foreach ($products as $product) {
        switch ($product['aging_status']) {
            case 'Recent': $agingSummary['recent']++; break;
            case 'Moderate': $agingSummary['moderate']++; break;
            case 'Old': $agingSummary['old']++; break;
            case 'Very Old': $agingSummary['very_old']++; break;
        }
    }

    return [
        'type' => 'inventory_aging',
        'aging_summary' => $agingSummary,
        'total_products' => count($products),
        'data' => $products
    ];
}
