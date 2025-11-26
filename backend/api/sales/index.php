<?php
/**
 * Sales API Endpoint
 * GET /backend/api/sales/index.php - Get all sales
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

    // Build query - Pull from customer_orders (shop orders) instead of sales table
    $query = "
        SELECT
            co.id,
            co.order_number as invoice_number,
            co.customer_id,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.email as customer_email,
            c.phone as customer_phone,
            co.subtotal,
            co.tax_amount,
            0 as tax_rate,
            co.discount_amount,
            co.total_amount,
            co.payment_method,
            co.payment_status,
            co.order_date as sale_date,
            co.status as notes,
            COALESCE(COUNT(coi.id), 0) as items_count
        FROM customer_orders co
        LEFT JOIN customers c ON co.customer_id = c.id
        LEFT JOIN customer_order_items coi ON co.id = coi.order_id
        WHERE 1=1
    ";

    // Optional filters
    $conditions = [];
    $params = [];

    if (isset($_GET['start_date']) || isset($_GET['date_from'])) {
        $conditions[] = "DATE(co.order_date) >= :start_date";
        $params[':start_date'] = $_GET['start_date'] ?? $_GET['date_from'];
    }

    if (isset($_GET['end_date']) || isset($_GET['date_to'])) {
        $conditions[] = "DATE(co.order_date) <= :end_date";
        $params[':end_date'] = $_GET['end_date'] ?? $_GET['date_to'];
    }

    if (isset($_GET['payment_status'])) {
        $conditions[] = "co.payment_status = :payment_status";
        $params[':payment_status'] = $_GET['payment_status'];
    }

    if (isset($_GET['payment_method'])) {
        $conditions[] = "co.payment_method = :payment_method";
        $params[':payment_method'] = $_GET['payment_method'];
    }

    if (isset($_GET['customer_id'])) {
        $conditions[] = "co.customer_id = :customer_id";
        $params[':customer_id'] = (int)$_GET['customer_id'];
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " GROUP BY co.id ORDER BY co.order_date DESC LIMIT 100";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary statistics
    $totalSales = 0;
    $totalRevenue = 0;
    $totalTax = 0;

    foreach ($sales as $sale) {
        $totalRevenue += $sale['total_amount'];
        $totalTax += $sale['tax_amount'];
    }
    $totalSales = count($sales);

    Response::success([
        'sales' => $sales,
        'summary' => [
            'total_sales' => $totalSales,
            'total_revenue' => round($totalRevenue, 2),
            'total_tax' => round($totalTax, 2),
            'average_sale' => $totalSales > 0 ? round($totalRevenue / $totalSales, 2) : 0
        ]
    ]);

} catch (Exception $e) {
    Response::serverError('Failed to fetch sales');
}
