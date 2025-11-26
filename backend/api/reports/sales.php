<?php
/**
 * Sales Reports API Endpoint
 * GET /backend/api/reports/sales.php - Generate sales reports
 *
 * Query Parameters:
 * - type: 'summary', 'detailed', 'by_product', 'by_category', 'by_cashier', 'by_payment_method'
 * - start_date: Start date (YYYY-MM-DD)
 * - end_date: End date (YYYY-MM-DD)
 * - group_by: 'day', 'week', 'month' (for summary reports)
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
    $reportType = $_GET['type'] ?? 'summary';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $groupBy = $_GET['group_by'] ?? 'day';
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
        case 'summary':
            $reportData = generateSalesSummary($conn, $startDate, $endDate, $groupBy);
            break;

        case 'detailed':
            $reportData = generateDetailedSales($conn, $startDate, $endDate, $limit);
            break;

        case 'by_product':
            $reportData = generateSalesByProduct($conn, $startDate, $endDate, $limit);
            break;

        case 'by_category':
            $reportData = generateSalesByCategory($conn, $startDate, $endDate, $limit);
            break;

        case 'by_cashier':
            $reportData = generateSalesByCashier($conn, $startDate, $endDate, $limit);
            break;

        case 'by_payment_method':
            $reportData = generateSalesByPaymentMethod($conn, $startDate, $endDate);
            break;

        default:
            Response::error('Invalid report type. Supported types: summary, detailed, by_product, by_category, by_cashier, by_payment_method', 400);
    }

    Response::success($reportData);

} catch (Exception $e) {
    Response::serverError('Failed to generate report: ' . $e->getMessage());
}

function generateSalesSummary($conn, $startDate, $endDate, $groupBy) {
    $dateFormat = match($groupBy) {
        'week' => "DATE_FORMAT(s.sale_date, '%Y-%u')",
        'month' => "DATE_FORMAT(s.sale_date, '%Y-%m')",
        default => "DATE(s.sale_date)"
    };

    $query = "
        SELECT
            {$dateFormat} as period,
            COUNT(DISTINCT s.id) as total_sales,
            SUM(s.total_amount) as total_revenue,
            SUM(s.tax_amount) as total_tax,
            AVG(s.total_amount) as average_sale,
            SUM(si.quantity) as total_items_sold,
            COUNT(DISTINCT si.product_id) as unique_products
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY {$dateFormat}
        ORDER BY period DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totals = [
        'total_sales' => 0,
        'total_revenue' => 0,
        'total_tax' => 0,
        'total_items_sold' => 0,
        'unique_products' => 0
    ];

    foreach ($results as $row) {
        $totals['total_sales'] += $row['total_sales'];
        $totals['total_revenue'] += $row['total_revenue'];
        $totals['total_tax'] += $row['total_tax'];
        $totals['total_items_sold'] += $row['total_items_sold'];
        $totals['unique_products'] = max($totals['unique_products'], $row['unique_products']);
    }

    return [
        'type' => 'sales_summary',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'group_by' => $groupBy,
        'data' => $results,
        'totals' => $totals
    ];
}

function generateDetailedSales($conn, $startDate, $endDate, $limit) {
    $query = "
        SELECT
            s.id,
            s.invoice_number,
            s.customer_name,
            s.customer_email,
            s.customer_phone,
            s.subtotal,
            s.tax_amount,
            s.discount_amount,
            s.total_amount,
            s.payment_method,
            s.payment_status,
            s.sale_date,
            u.full_name as cashier_name,
            COUNT(si.id) as items_count
        FROM sales s
        INNER JOIN users u ON s.cashier_id = u.id
        LEFT JOIN sale_items si ON s.id = si.sale_id
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY s.id
        ORDER BY s.sale_date DESC
        LIMIT :limit
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'detailed_sales',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_records' => count($sales),
        'data' => $sales
    ];
}

function generateSalesByProduct($conn, $startDate, $endDate, $limit) {
    $query = "
        SELECT
            p.id,
            p.sku,
            p.name,
            c.name as category_name,
            SUM(si.quantity) as total_quantity,
            SUM(si.quantity * si.unit_price) as total_revenue,
            AVG(si.unit_price) as average_price,
            COUNT(DISTINCT s.id) as sales_count
        FROM sale_items si
        INNER JOIN products p ON si.product_id = p.id
        INNER JOIN categories c ON p.category_id = c.id
        INNER JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY p.id, p.sku, p.name, c.name
        ORDER BY total_revenue DESC
        LIMIT :limit
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'sales_by_product',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_products' => count($products),
        'data' => $products
    ];
}

function generateSalesByCategory($conn, $startDate, $endDate, $limit) {
    $query = "
        SELECT
            c.id,
            c.name as category_name,
            c.slug,
            SUM(si.quantity) as total_quantity,
            SUM(si.quantity * si.unit_price) as total_revenue,
            COUNT(DISTINCT p.id) as products_count,
            COUNT(DISTINCT s.id) as sales_count
        FROM sale_items si
        INNER JOIN products p ON si.product_id = p.id
        INNER JOIN categories c ON p.category_id = c.id
        INNER JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY c.id, c.name, c.slug
        ORDER BY total_revenue DESC
        LIMIT :limit
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'sales_by_category',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_categories' => count($categories),
        'data' => $categories
    ];
}

function generateSalesByCashier($conn, $startDate, $endDate, $limit) {
    $query = "
        SELECT
            u.id,
            u.username,
            u.full_name,
            COUNT(DISTINCT s.id) as total_sales,
            SUM(s.total_amount) as total_revenue,
            SUM(s.tax_amount) as total_tax,
            AVG(s.total_amount) as average_sale,
            SUM(si.quantity) as total_items_sold
        FROM sales s
        INNER JOIN users u ON s.cashier_id = u.id
        LEFT JOIN sale_items si ON s.id = si.sale_id
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY u.id, u.username, u.full_name
        ORDER BY total_revenue DESC
        LIMIT :limit
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $cashiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'sales_by_cashier',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_cashiers' => count($cashiers),
        'data' => $cashiers
    ];
}

function generateSalesByPaymentMethod($conn, $startDate, $endDate) {
    $query = "
        SELECT
            s.payment_method,
            COUNT(*) as transaction_count,
            SUM(s.total_amount) as total_amount,
            AVG(s.total_amount) as average_amount,
            MIN(s.total_amount) as min_amount,
            MAX(s.total_amount) as max_amount
        FROM sales s
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY s.payment_method
        ORDER BY total_amount DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();

    $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'sales_by_payment_method',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'data' => $methods
    ];
}
