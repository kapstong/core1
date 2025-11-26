<?php
/**
 * Product Reports API Endpoint
 * GET /backend/api/reports/products.php - Generate product reports
 *
 * Query Parameters:
 * - type: 'performance', 'profitability', 'movement', 'top_sellers', 'slow_movers', 'by_category', 'price_analysis'
 * - start_date: Start date (YYYY-MM-DD)
 * - end_date: End date (YYYY-MM-DD)
 * - category_id: Filter by category
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
    $reportType = $_GET['type'] ?? 'performance';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-90 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
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
        case 'performance':
            $reportData = generateProductPerformance($conn, $startDate, $endDate, $categoryId, $limit);
            break;

        case 'profitability':
            $reportData = generateProductProfitability($conn, $startDate, $endDate, $categoryId, $limit);
            break;

        case 'movement':
            $reportData = generateProductMovement($conn, $startDate, $endDate, $categoryId, $limit);
            break;

        case 'top_sellers':
            $reportData = generateTopSellers($conn, $startDate, $endDate, $categoryId, $limit);
            break;

        case 'slow_movers':
            $reportData = generateSlowMovers($conn, $categoryId, $limit);
            break;

        case 'by_category':
            $reportData = generateProductsByCategory($conn, $startDate, $endDate, $limit);
            break;

        case 'price_analysis':
            $reportData = generatePriceAnalysis($conn, $categoryId, $limit);
            break;

        default:
            Response::error('Invalid report type. Supported types: performance, profitability, movement, top_sellers, slow_movers, by_category, price_analysis', 400);
    }

    Response::success($reportData);

} catch (Exception $e) {
    Response::serverError('Failed to generate report: ' . $e->getMessage());
}

function generateProductPerformance($conn, $startDate, $endDate, $categoryId, $limit) {
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            p.cost_price,
            p.selling_price,
            p.reorder_level,
            c.name as category_name,
            i.quantity_on_hand,
            i.quantity_available,
            COALESCE(SUM(si.quantity), 0) as total_sold,
            COALESCE(SUM(si.quantity * si.unit_price), 0) as total_sales_revenue,
            COALESCE(SUM(si.quantity * p.cost_price), 0) as total_cost_of_goods,
            COALESCE(AVG(si.unit_price), 0) as avg_selling_price,
            COUNT(DISTINCT s.id) as sales_count,

            CASE
                WHEN COALESCE(SUM(si.quantity * si.unit_price), 0) > 0
                THEN ROUND((COALESCE(SUM(si.quantity * si.unit_price), 0) - COALESCE(SUM(si.quantity * p.cost_price), 0)) * 100.0 / COALESCE(SUM(si.quantity * si.unit_price), 0), 2)
                ELSE 0
            END as profit_margin
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id
        LEFT JOIN sale_items si ON p.id = si.product_id
        LEFT JOIN sales s ON si.sale_id = s.id AND DATE(s.sale_date) BETWEEN :start_date AND :end_date
        WHERE p.is_active = 1
    ";

    $params = [':start_date' => $startDate, ':end_date' => $endDate];
    if ($categoryId) {
        $query .= " AND p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    $query .= " GROUP BY p.id, p.sku, p.name, p.cost_price, p.selling_price, p.reorder_level,
                        c.name, i.quantity_on_hand, i.quantity_available
               ORDER BY total_sales_revenue DESC, total_sold DESC
               LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'product_performance',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_products' => count($products),
        'data' => $products
    ];
}

function generateProductProfitability($conn, $startDate, $endDate, $categoryId, $limit) {
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            p.cost_price,
            p.selling_price,
            c.name as category_name,
            COALESCE(SUM(si.quantity), 0) as units_sold,
            COALESCE(SUM(si.quantity * si.unit_price), 0) as total_revenue,
            COALESCE(SUM(si.quantity * p.cost_price), 0) as total_cost,
            COALESCE(SUM(si.quantity * (si.unit_price - p.cost_price)), 0) as total_profit,
            CASE
                WHEN COALESCE(SUM(si.quantity * si.unit_price), 0) > 0
                THEN ROUND(COALESCE(SUM(si.quantity * (si.unit_price - p.cost_price)), 0) * 100.0 / COALESCE(SUM(si.quantity * si.unit_price), 0), 2)
                ELSE 0
            END as profit_margin_percentage,
            CASE
                WHEN COALESCE(SUM(si.quantity), 0) > 0
                THEN ROUND(COALESCE(SUM(si.quantity * (si.unit_price - p.cost_price)), 0) / COALESCE(SUM(si.quantity), 0), 2)
                ELSE 0
            END as profit_per_unit,
            ROUND((p.selling_price - p.cost_price) * 100.0 / p.selling_price, 2) as markup_percentage,
            COUNT(DISTINCT s.id) as number_of_sales
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN sale_items si ON p.id = si.product_id
        LEFT JOIN sales s ON si.sale_id = s.id AND DATE(s.sale_date) BETWEEN :start_date AND :end_date
        WHERE p.is_active = 1
    ";

    $params = [':start_date' => $startDate, ':end_date' => $endDate];
    if ($categoryId) {
        $query .= " AND p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    $query .= " GROUP BY p.id, p.sku, p.name, p.cost_price, p.selling_price, c.name
               HAVING units_sold > 0
               ORDER BY total_profit DESC, profit_margin_percentage DESC
               LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'product_profitability',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_products' => count($products),
        'data' => $products
    ];
}

function generateProductMovement($conn, $startDate, $endDate, $categoryId, $limit) {
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            c.name as category_name,
            i.quantity_on_hand as current_stock,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'sale' THEN ABS(sm.quantity) ELSE 0 END), 0) as sold_quantity,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'purchase' THEN sm.quantity ELSE 0 END), 0) as purchased_quantity,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'adjustment' THEN sm.quantity ELSE 0 END), 0) as adjusted_quantity,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'return' THEN sm.quantity ELSE 0 END), 0) as returned_quantity,
            COUNT(CASE WHEN sm.movement_type = 'sale' THEN 1 END) as sale_transactions,
            COUNT(CASE WHEN sm.movement_type = 'purchase' THEN 1 END) as purchase_transactions,
            MIN(sm.created_at) as first_movement,
            MAX(sm.created_at) as last_movement,
            DATEDIFF(:end_date, :start_date) as days_in_period,
            CASE
                WHEN DATEDIFF(:end_date, :start_date) > 0
                THEN ROUND(COALESCE(SUM(CASE WHEN sm.movement_type = 'sale' THEN ABS(sm.quantity) ELSE 0 END), 0) * 30.0 / DATEDIFF(:end_date, :start_date), 2)
                ELSE 0
            END as monthly_movement_rate
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id
        LEFT JOIN stock_movements sm ON p.id = sm.product_id
            AND DATE(sm.created_at) BETWEEN :start_date AND :end_date
        WHERE p.is_active = 1
    ";

    $params = [':start_date' => $startDate, ':end_date' => $endDate];
    if ($categoryId) {
        $query .= " AND p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    $query .= " GROUP BY p.id, p.sku, p.name, c.name, i.quantity_on_hand
               ORDER BY sold_quantity DESC, monthly_movement_rate DESC
               LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'product_movement',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_products' => count($products),
        'data' => $products
    ];
}

function generateTopSellers($conn, $startDate, $endDate, $categoryId, $limit) {
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            p.selling_price,
            c.name as category_name,
            SUM(si.quantity) as total_units_sold,
            SUM(si.quantity * si.unit_price) as total_revenue,
            COUNT(DISTINCT s.id) as number_of_sales,
            AVG(si.unit_price) as avg_selling_price,
            MAX(s.sale_date) as last_sale_date,
            MIN(s.sale_date) as first_sale_date,
            ROUND(SUM(si.quantity * si.unit_price) / SUM(si.quantity), 2) as avg_revenue_per_unit,
            RANK() OVER (ORDER BY SUM(si.quantity * si.unit_price) DESC) as revenue_rank,
            RANK() OVER (ORDER BY SUM(si.quantity) DESC) as volume_rank
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        INNER JOIN sale_items si ON p.id = si.product_id
        INNER JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
          AND p.is_active = 1
    ";

    $params = [':start_date' => $startDate, ':end_date' => $endDate];
    if ($categoryId) {
        $query .= " AND p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    $query .= " GROUP BY p.id, p.sku, p.name, p.selling_price, c.name
               ORDER BY total_revenue DESC, total_units_sold DESC
               LIMIT :limit";

    $stmt = $conn->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'top_sellers',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_products' => count($products),
        'data' => $products
    ];
}

function generateSlowMovers($conn, $categoryId, $limit) {
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            p.selling_price,
            p.reorder_level,
            c.name as category_name,
            i.quantity_on_hand,
            i.quantity_available,
            COALESCE(SUM(si.quantity), 0) as total_sold_90_days,
            COALESCE(MAX(s.sale_date), NULL) as last_sale_date,
            DATEDIFF(CURDATE(), COALESCE(MAX(s.sale_date), p.created_at)) as days_since_last_sale,
            CASE
                WHEN COALESCE(MAX(s.sale_date), p.created_at) < DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 'Very Slow'
                WHEN COALESCE(MAX(s.sale_date), p.created_at) < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 'Slow'
                WHEN COALESCE(SUM(si.quantity), 0) = 0 THEN 'No Sales'
                ELSE 'Active'
            END as movement_status,
            ROUND(i.quantity_on_hand * p.selling_price, 2) as inventory_value,

        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN inventory i ON p.id = i.product_id
        LEFT JOIN sale_items si ON p.id = si.product_id
        LEFT JOIN sales s ON si.sale_id = s.id AND DATE(s.sale_date) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        WHERE p.is_active = 1
    ";

    $params = [];
    if ($categoryId) {
        $query .= " AND p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    $query .= " GROUP BY p.id, p.sku, p.name, p.selling_price, p.reorder_level, c.name,
                        i.quantity_on_hand, i.quantity_available, p.created_at
               ORDER BY days_since_last_sale DESC, total_sold_90_days ASC, inventory_value DESC
               LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'slow_movers',
        'analysis_period_days' => 90,
        'total_products' => count($products),
        'data' => $products
    ];
}

function generateProductsByCategory($conn, $startDate, $endDate, $limit) {
    $query = "
        SELECT
            c.id,
            c.name as category_name,
            c.slug,
            COUNT(DISTINCT p.id) as total_products,
            COUNT(DISTINCT CASE WHEN p.is_active = 1 THEN p.id END) as active_products,
            SUM(i.quantity_on_hand) as total_stock_quantity,
            SUM(i.quantity_on_hand * p.selling_price) as total_inventory_value,
            COALESCE(SUM(si.quantity), 0) as total_units_sold,
            COALESCE(SUM(si.quantity * si.unit_price), 0) as total_sales_revenue,
            COALESCE(AVG(si.unit_price), 0) as avg_selling_price,
            COUNT(DISTINCT s.id) as total_sales_transactions,

            ROUND(
                CASE
                    WHEN COALESCE(SUM(si.quantity * si.unit_price), 0) > 0
                    THEN (COALESCE(SUM(si.quantity * si.unit_price), 0) - COALESCE(SUM(si.quantity * p.cost_price), 0)) * 100.0 / COALESCE(SUM(si.quantity * si.unit_price), 0)
                    ELSE 0
                END, 2
            ) as avg_profit_margin
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        LEFT JOIN inventory i ON p.id = i.product_id
        LEFT JOIN sale_items si ON p.id = si.product_id
        LEFT JOIN sales s ON si.sale_id = s.id AND DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY c.id, c.name, c.slug
        ORDER BY total_sales_revenue DESC, total_inventory_value DESC
        LIMIT :limit
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'products_by_category',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_categories' => count($categories),
        'data' => $categories
    ];
}

function generatePriceAnalysis($conn, $categoryId, $limit) {
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            p.cost_price,
            p.selling_price,
            c.name as category_name,
            ROUND((p.selling_price - p.cost_price) * 100.0 / p.selling_price, 2) as markup_percentage,
            ROUND((p.selling_price - p.cost_price) * 100.0 / p.cost_price, 2) as margin_percentage,
            COALESCE(AVG(si.unit_price), 0) as avg_actual_selling_price,
            COALESCE(MIN(si.unit_price), p.selling_price) as min_selling_price,
            COALESCE(MAX(si.unit_price), p.selling_price) as max_selling_price,
            COUNT(si.id) as total_sale_items,
            CASE
                WHEN p.selling_price > 0 AND COALESCE(AVG(si.unit_price), 0) > 0
                THEN ROUND((COALESCE(AVG(si.unit_price), 0) - p.selling_price) * 100.0 / p.selling_price, 2)
                ELSE 0
            END as avg_price_variance,
            CASE
                WHEN COUNT(si.id) > 0 THEN 'Has Sales'
                ELSE 'No Sales'
            END as sales_status
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN sale_items si ON p.id = si.product_id
        WHERE p.is_active = 1
    ";

    $params = [];
    if ($categoryId) {
        $query .= " AND p.category_id = :category_id";
        $params[':category_id'] = $categoryId;
    }

    $query .= " GROUP BY p.id, p.sku, p.name, p.cost_price, p.selling_price, c.name
               ORDER BY markup_percentage DESC, total_sale_items DESC
               LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'price_analysis',
        'total_products' => count($products),
        'data' => $products
    ];
}
