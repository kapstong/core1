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

    // Build unified query that pulls from both customer_orders (online) and sales (POS) tables
    $query = "
        (
            SELECT
                co.id as id,
                co.order_number as invoice_number,
                co.order_number as sale_number,
                co.customer_id,
                CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
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
                COALESCE(COUNT(coi.id), 0) as items_count,
                'online' as sale_type
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

    // Search filter for invoice number or customer name
    if (isset($_GET['search'])) {
        $searchTerm = '%' . $_GET['search'] . '%';
        $conditions[] = "(co.order_number LIKE :search OR c.first_name LIKE :search OR c.last_name LIKE :search)";
        $params[':search'] = $searchTerm;
    }

    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " GROUP BY co.id ORDER BY co.order_date DESC
        )
        UNION ALL
        (
            SELECT
                s.id as id,
                s.invoice_number as invoice_number,
                s.invoice_number as sale_number,
                NULL as customer_id,
                s.customer_name,
                s.customer_email,
                s.customer_phone,
                s.subtotal,
                s.tax_amount,
                s.tax_rate,
                s.discount_amount,
                s.total_amount,
                s.payment_method,
                s.payment_status,
                s.sale_date,
                s.notes,
                COALESCE(COUNT(si.id), 0) as items_count,
                'pos' as sale_type
            FROM sales s
            LEFT JOIN sale_items si ON s.id = si.sale_id
            WHERE 1=1
    ";

    // Apply filters to POS sales as well
    if (isset($_GET['start_date']) || isset($_GET['date_from'])) {
        $conditions_pos = [];
        $conditions_pos[] = "DATE(s.sale_date) >= :start_date_pos";
        $params[':start_date_pos'] = $_GET['start_date'] ?? $_GET['date_from'];
        $query .= " AND " . implode(" AND ", $conditions_pos);
    }

    if (isset($_GET['end_date']) || isset($_GET['date_to'])) {
        $query .= " AND DATE(s.sale_date) <= :end_date_pos";
        $params[':end_date_pos'] = $_GET['end_date'] ?? $_GET['date_to'];
    }

    if (isset($_GET['payment_status'])) {
        $query .= " AND s.payment_status = :payment_status_pos";
        $params[':payment_status_pos'] = $_GET['payment_status'];
    }

    if (isset($_GET['payment_method'])) {
        $query .= " AND s.payment_method = :payment_method_pos";
        $params[':payment_method_pos'] = $_GET['payment_method'];
    }

    // Search filter for POS sales
    if (isset($_GET['search'])) {
        $query .= " AND (s.invoice_number LIKE :search_pos OR s.customer_name LIKE :search_pos)";
        $params[':search_pos'] = '%' . $_GET['search'] . '%';
    }

    $query .= " GROUP BY s.id ORDER BY s.sale_date DESC
        )
        ORDER BY sale_date DESC
        LIMIT 100
    ";

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

    Response::success($sales);

} catch (Exception $e) {
    Response::serverError('Failed to fetch sales');
}
