<?php
/**
 * Supplier Reports API Endpoint
 * GET /backend/api/reports/suppliers.php - Generate supplier reports
 *
 * Query Parameters:
 * - type: 'performance', 'purchases', 'payments', 'ratings', 'by_category', 'comparison'
 * - start_date: Start date (YYYY-MM-DD)
 * - end_date: End date (YYYY-MM-DD)
 * - supplier_id: Filter by specific supplier
 * - limit: Number of records to return (default: 100)
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

    // Get parameters
    $reportType = $_GET['type'] ?? 'performance';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-365 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    $supplierId = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;
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
            $reportData = generateSupplierPerformance($conn, $startDate, $endDate, $supplierId, $limit);
            break;

        case 'purchases':
            $reportData = generateSupplierPurchases($conn, $startDate, $endDate, $supplierId, $limit);
            break;

        case 'payments':
            $reportData = generateSupplierPayments($conn, $startDate, $endDate, $supplierId, $limit);
            break;

        case 'ratings':
            $reportData = generateSupplierRatings($conn, $supplierId, $limit);
            break;

        case 'by_category':
            $reportData = generateSuppliersByCategory($conn, $startDate, $endDate, $limit);
            break;

        case 'comparison':
            $reportData = generateSupplierComparison($conn, $startDate, $endDate, $limit);
            break;

        default:
            Response::error('Invalid report type. Supported types: performance, purchases, payments, ratings, by_category, comparison', 400);
    }

    Response::success($reportData);

} catch (Exception $e) {
    Response::serverError('Failed to generate report: ' . $e->getMessage());
}

function generateSupplierPerformance($conn, $startDate, $endDate, $supplierId, $limit) {
    $query = "
        SELECT
            s.id,
            s.code,
            s.name,
            s.contact_person,
            s.email,
            s.phone,
            s.rating,
            COUNT(DISTINCT po.id) as total_orders,
            COUNT(DISTINCT CASE WHEN po.status = 'received' THEN po.id END) as completed_orders,
            COUNT(DISTINCT CASE WHEN po.status IN ('submitted', 'approved', 'partially_received') THEN po.id END) as pending_orders,
            COUNT(DISTINCT CASE WHEN po.status = 'cancelled' THEN po.id END) as cancelled_orders,
            SUM(po.total_amount) as total_purchase_value,
            AVG(po.total_amount) as avg_order_value,
            MIN(po.order_date) as first_order_date,
            MAX(po.order_date) as last_order_date,
            DATEDIFF(MAX(po.order_date), MIN(po.order_date)) as relationship_days,
            CASE
                WHEN COUNT(DISTINCT po.id) > 0
                THEN ROUND(COUNT(DISTINCT CASE WHEN po.status = 'received' THEN po.id END) * 100.0 / COUNT(DISTINCT po.id), 2)
                ELSE 0
            END as completion_rate,
            CASE
                WHEN DATEDIFF(MAX(po.order_date), MIN(po.order_date)) > 0
                THEN ROUND(COUNT(DISTINCT po.id) * 30.0 / DATEDIFF(MAX(po.order_date), MIN(po.order_date)), 2)
                ELSE 0
            END as orders_per_month
        FROM suppliers s
        LEFT JOIN purchase_orders po ON s.id = po.supplier_id
            AND DATE(po.order_date) BETWEEN :start_date AND :end_date
        WHERE s.is_active = 1
    ";

    $params = [':start_date' => $startDate, ':end_date' => $endDate];
    if ($supplierId) {
        $query .= " AND s.id = :supplier_id";
        $params[':supplier_id'] = $supplierId;
    }

    $query .= " GROUP BY s.id, s.code, s.name, s.contact_person, s.email, s.phone, s.rating
               ORDER BY total_purchase_value DESC, completion_rate DESC
               LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'supplier_performance',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_suppliers' => count($suppliers),
        'data' => $suppliers
    ];
}

function generateSupplierPurchases($conn, $startDate, $endDate, $supplierId, $limit) {
    $query = "
        SELECT
            po.id,
            po.po_number,
            po.order_date,
            po.expected_delivery_date,
            po.status,
            po.total_amount,
            po.notes,
            CONCAT('SUP-', LPAD(s.id, 5, '0')) as supplier_code,
            s.full_name as supplier_name,
            u.full_name as created_by_name,
            COUNT(poi.id) as items_count,
            SUM(poi.quantity_ordered) as total_quantity_ordered,
            SUM(poi.quantity_received) as total_quantity_received,
            CASE
                WHEN SUM(poi.quantity_ordered) > 0
                THEN ROUND(SUM(poi.quantity_received) * 100.0 / SUM(poi.quantity_ordered), 2)
                ELSE 0
            END as fulfillment_rate
        FROM purchase_orders po
        INNER JOIN users s ON po.supplier_id = s.id AND s.role = 'supplier'
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
        WHERE DATE(po.order_date) BETWEEN :start_date AND :end_date
    ";

    $params = [':start_date' => $startDate, ':end_date' => $endDate];
    if ($supplierId) {
        $query .= " AND po.supplier_id = :supplier_id";
        $params[':supplier_id'] = $supplierId;
    }

    $query .= " GROUP BY po.id, po.po_number, po.order_date, po.expected_delivery_date, po.status,
                        po.total_amount, po.notes, s.id, s.full_name, u.full_name
               ORDER BY po.order_date DESC
               LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'supplier_purchases',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_purchases' => count($purchases),
        'data' => $purchases
    ];
}

function generateSupplierPayments($conn, $startDate, $endDate, $supplierId, $limit) {
    // Note: This is a simplified payment tracking - in a real system you'd have a payments table
    $query = "
        SELECT
            s.id,
            s.code,
            s.name,
            s.payment_terms,
            COUNT(po.id) as total_orders,
            SUM(po.total_amount) as total_amount_ordered,
            SUM(CASE WHEN po.status = 'received' THEN po.total_amount ELSE 0 END) as total_amount_received,
            AVG(po.total_amount) as avg_order_value,
            MIN(po.order_date) as earliest_order,
            MAX(po.order_date) as latest_order,
            GROUP_CONCAT(DISTINCT po.status) as order_statuses
        FROM suppliers s
        LEFT JOIN purchase_orders po ON s.id = po.supplier_id
            AND DATE(po.order_date) BETWEEN :start_date AND :end_date
        WHERE s.is_active = 1
    ";

    $params = [':start_date' => $startDate, ':end_date' => $endDate];
    if ($supplierId) {
        $query .= " AND s.id = :supplier_id";
        $params[':supplier_id'] = $supplierId;
    }

    $query .= " GROUP BY s.id, s.code, s.name, s.payment_terms
               ORDER BY total_amount_ordered DESC
               LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'supplier_payments',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_suppliers' => count($payments),
        'data' => $payments
    ];
}

function generateSupplierRatings($conn, $supplierId, $limit) {
    $query = "
        SELECT
            s.id,
            s.code,
            s.name,
            s.contact_person,
            s.email,
            s.phone,
            s.rating,
            s.notes,
            COUNT(po.id) as total_orders,
            COUNT(CASE WHEN po.status = 'received' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN po.status = 'cancelled' THEN 1 END) as cancelled_orders,
            AVG(CASE
                WHEN po.status = 'received' AND poi.quantity_ordered > 0
                THEN (poi.quantity_received * 100.0 / poi.quantity_ordered)
                ELSE NULL
            END) as avg_fulfillment_rate,
            DATEDIFF(CURDATE(), MIN(po.order_date)) as relationship_days,
            MAX(po.order_date) as last_order_date,
            CASE
                WHEN MAX(po.order_date) < DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 'Inactive'
                WHEN COUNT(po.id) = 0 THEN 'New'
                WHEN COUNT(CASE WHEN po.status = 'received' THEN 1 END) * 100.0 / COUNT(po.id) >= 95 THEN 'Excellent'
                WHEN COUNT(CASE WHEN po.status = 'received' THEN 1 END) * 100.0 / COUNT(po.id) >= 85 THEN 'Good'
                WHEN COUNT(CASE WHEN po.status = 'received' THEN 1 END) * 100.0 / COUNT(po.id) >= 70 THEN 'Average'
                ELSE 'Poor'
            END as performance_rating
        FROM suppliers s
        LEFT JOIN purchase_orders po ON s.id = po.supplier_id
        LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
        WHERE s.is_active = 1
    ";

    $params = [];
    if ($supplierId) {
        $query .= " AND s.id = :supplier_id";
        $params[':supplier_id'] = $supplierId;
    }

    $query .= " GROUP BY s.id, s.code, s.name, s.contact_person, s.email, s.phone, s.rating, s.notes
               ORDER BY
                   CASE performance_rating
                       WHEN 'Excellent' THEN 1
                       WHEN 'Good' THEN 2
                       WHEN 'Average' THEN 3
                       WHEN 'Poor' THEN 4
                       WHEN 'Inactive' THEN 5
                       WHEN 'New' THEN 6
                   END,
                   total_orders DESC
               LIMIT :limit";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'supplier_ratings',
        'total_suppliers' => count($ratings),
        'data' => $ratings
    ];
}

function generateSuppliersByCategory($conn, $startDate, $endDate, $limit) {
    $query = "
        SELECT
            c.name as category_name,
            c.slug,
            COUNT(DISTINCT s.id) as supplier_count,
            COUNT(DISTINCT po.id) as total_orders,
            SUM(po.total_amount) as total_purchase_value,
            AVG(po.total_amount) as avg_order_value,
            COUNT(DISTINCT p.id) as products_supplied,
            GROUP_CONCAT(DISTINCT s.full_name ORDER BY s.full_name SEPARATOR ', ') as supplier_names
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.is_active = 1
        LEFT JOIN purchase_order_items poi ON p.id = poi.product_id
        LEFT JOIN purchase_orders po ON poi.po_id = po.id
            AND DATE(po.order_date) BETWEEN :start_date AND :end_date
        LEFT JOIN users s ON po.supplier_id = s.id AND s.role = 'supplier' AND s.is_active = 1
        GROUP BY c.id, c.name, c.slug
        HAVING supplier_count > 0
        ORDER BY total_purchase_value DESC, supplier_count DESC
        LIMIT :limit
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'suppliers_by_category',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_categories' => count($categories),
        'data' => $categories
    ];
}

function generateSupplierComparison($conn, $startDate, $endDate, $limit) {
    $query = "
        SELECT
            s.id,
            s.code,
            s.name,
            COUNT(po.id) as order_count,
            SUM(po.total_amount) as total_spent,
            AVG(po.total_amount) as avg_order_value,
            MIN(po.total_amount) as min_order_value,
            MAX(po.total_amount) as max_order_value,
            COUNT(CASE WHEN po.status = 'received' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN po.status = 'cancelled' THEN 1 END) as cancelled_orders,
            ROUND(
                CASE
                    WHEN COUNT(po.id) > 0
                    THEN COUNT(CASE WHEN po.status = 'received' THEN 1 END) * 100.0 / COUNT(po.id)
                    ELSE 0
                END, 2
            ) as completion_rate,
            DATEDIFF(:end_date, MIN(po.order_date)) as active_days,
            ROUND(
                CASE
                    WHEN DATEDIFF(:end_date, MIN(po.order_date)) > 0
                    THEN COUNT(po.id) * 30.0 / DATEDIFF(:end_date, MIN(po.order_date))
                    ELSE 0
                END, 2
            ) as orders_per_month
        FROM suppliers s
        LEFT JOIN purchase_orders po ON s.id = po.supplier_id
            AND DATE(po.order_date) BETWEEN :start_date AND :end_date
        WHERE s.is_active = 1
        GROUP BY s.id, s.code, s.name
        HAVING order_count > 0
        ORDER BY total_spent DESC, completion_rate DESC
        LIMIT :limit
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate comparison metrics
    $comparison = [
        'best_performer' => null,
        'worst_performer' => null,
        'highest_spender' => null,
        'most_reliable' => null,
        'averages' => [
            'avg_orders_per_supplier' => 0,
            'avg_completion_rate' => 0,
            'avg_order_value' => 0,
            'total_suppliers' => count($suppliers)
        ]
    ];

    if (!empty($suppliers)) {
        $comparison['best_performer'] = $suppliers[0]; // Already sorted by total_spent DESC
        $comparison['worst_performer'] = end($suppliers);
        $comparison['highest_spender'] = $suppliers[0];

        // Find most reliable (highest completion rate)
        $mostReliable = $suppliers[0];
        foreach ($suppliers as $supplier) {
            if ($supplier['completion_rate'] > $mostReliable['completion_rate']) {
                $mostReliable = $supplier;
            }
        }
        $comparison['most_reliable'] = $mostReliable;

        // Calculate averages
        $totalOrders = 0;
        $totalCompletion = 0;
        $totalValue = 0;
        foreach ($suppliers as $supplier) {
            $totalOrders += $supplier['order_count'];
            $totalCompletion += $supplier['completion_rate'];
            $totalValue += $supplier['total_spent'];
        }
        $comparison['averages']['avg_orders_per_supplier'] = round($totalOrders / count($suppliers), 2);
        $comparison['averages']['avg_completion_rate'] = round($totalCompletion / count($suppliers), 2);
        $comparison['averages']['avg_order_value'] = round($totalValue / count($suppliers), 2);
    }

    return [
        'type' => 'supplier_comparison',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'data' => $suppliers,
        'comparison' => $comparison
    ];
}

