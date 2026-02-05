<?php
/**
 * Unified Sales API Endpoint
 * Combines sales from both e-commerce (customer_orders) and in-store (sales) systems
 * GET /backend/api/sales/unified.php - Get unified sales data
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
    $conn = $db->getConnection();

    // Build unified sales query
    $query = "
        SELECT
            'online' as sale_type,
            co.id as sale_id,
            co.order_number as reference_number,
            co.customer_id,
            c.first_name || ' ' || c.last_name as customer_name,
            c.email as customer_email,
            co.total_amount as total,
            co.created_at as sale_date,
            co.status as order_status,
            co.payment_status,
            'Online Order' as sale_channel,
            NULL as cashier_id,
            NULL as cashier_name
        FROM customer_orders co
        LEFT JOIN customers c ON co.customer_id = c.id
        WHERE co.status IN ('confirmed', 'processing', 'shipped', 'delivered')

        UNION ALL

        SELECT
            'in_store' as sale_type,
            s.id as sale_id,
            s.invoice_number as reference_number,
            NULL as customer_id,
            s.customer_name,
            s.customer_email,
            s.total_amount as total,
            s.sale_date,
            'completed' as order_status,
            s.payment_status,
            'In-Store' as sale_channel,
            s.cashier_id,
            u.full_name as cashier_name
        FROM sales s
        LEFT JOIN users u ON s.cashier_id = u.id
        WHERE s.payment_status = 'paid'
    ";

    // Optional filters
    $conditions = [];
    $params = [];

    if (isset($_GET['start_date'])) {
        $conditions[] = "sale_date >= :start_date";
        $params[':start_date'] = $_GET['start_date'];
    }

    if (isset($_GET['end_date'])) {
        $conditions[] = "sale_date <= :end_date";
        $params[':end_date'] = $_GET['end_date'];
    }

    if (isset($_GET['sale_type'])) {
        $conditions[] = "sale_type = :sale_type";
        $params[':sale_type'] = $_GET['sale_type'];
    }

    if (isset($_GET['payment_status'])) {
        $conditions[] = "payment_status = :payment_status";
        $params[':payment_status'] = $_GET['payment_status'];
    }

    if (!empty($conditions)) {
        $query .= " HAVING " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY sale_date DESC LIMIT 100";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary statistics
    $totalSales = count($sales);
    $totalRevenue = 0;
    $onlineSales = 0;
    $inStoreSales = 0;
    $onlineRevenue = 0;
    $inStoreRevenue = 0;

    foreach ($sales as $sale) {
        $totalRevenue += $sale['total'];

        if ($sale['sale_type'] === 'online') {
            $onlineSales++;
            $onlineRevenue += $sale['total'];
        } else {
            $inStoreSales++;
            $inStoreRevenue += $sale['total'];
        }
    }

    // Get sales by date for chart
    $chartQuery = "
        SELECT
            DATE(sale_date) as date,
            COUNT(*) as total_sales,
            SUM(CASE WHEN sale_type = 'online' THEN 1 ELSE 0 END) as online_sales,
            SUM(CASE WHEN sale_type = 'in_store' THEN 1 ELSE 0 END) as in_store_sales,
            SUM(total) as total_revenue,
            SUM(CASE WHEN sale_type = 'online' THEN total ELSE 0 END) as online_revenue,
            SUM(CASE WHEN sale_type = 'in_store' THEN total ELSE 0 END) as in_store_revenue
        FROM (
            SELECT
                'online' as sale_type,
                co.total_amount as total,
                co.created_at as sale_date
            FROM customer_orders co
            WHERE co.status IN ('confirmed', 'processing', 'shipped', 'delivered')

            UNION ALL

            SELECT
                'in_store' as sale_type,
                s.total_amount as total,
                s.sale_date
            FROM sales s
            WHERE s.payment_status = 'paid'
        )
        WHERE sale_date >= date('now', '-30 days')
        GROUP BY DATE(sale_date)
        ORDER BY date DESC
    ";

    $chartStmt = $conn->prepare($chartQuery);
    $chartStmt->execute();
    $chartData = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success([
        'sales' => $sales,
        'summary' => [
            'total_sales' => $totalSales,
            'total_revenue' => round($totalRevenue, 2),
            'online_sales' => $onlineSales,
            'in_store_sales' => $inStoreSales,
            'online_revenue' => round($onlineRevenue, 2),
            'in_store_revenue' => round($inStoreRevenue, 2),
            'average_sale' => $totalSales > 0 ? round($totalRevenue / $totalSales, 2) : 0
        ],
        'chart_data' => $chartData
    ]);

} catch (Exception $e) {
    Response::serverError('Failed to fetch unified sales data: ' . $e->getMessage());
}
?>

