<?php
/**
 * Advanced Dashboard API Endpoint
 * GET /backend/api/dashboard/index.php - Get comprehensive dashboard data
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication and role (all roles except supplier can access)
Auth::requireRole(['admin', 'inventory_manager', 'purchasing_officer', 'staff']);

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        getDashboardData();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Dashboard data retrieval failed: ' . $e->getMessage());
}

function getDashboardData() {
    $db = Database::getInstance()->getConnection();
    $userRole = Auth::userRole();

    // Get date range parameters
    $period = isset($_GET['period']) ? $_GET['period'] : '30'; // days
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime("-{$period} days"));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

    $dashboard = [
        'summary' => getSummaryMetrics($db, $startDate, $endDate),
        'charts' => getChartData($db, $startDate, $endDate),
        'alerts' => getSystemAlerts($db),
        'recent_activity' => getRecentActivity($db),
        'top_performers' => getTopPerformers($db, $startDate, $endDate),
        'period' => [
            'start' => $startDate,
            'end' => $endDate,
            'days' => (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24)
        ]
    ];

    // Filter dashboard data based on user role
    $dashboard = filterDashboardByRole($dashboard, $userRole);

    Response::success($dashboard);
}

function getSummaryMetrics($db, $startDate, $endDate) {
    // Sales metrics - Pull from customer_orders (shop orders)
    $salesQuery = "
        SELECT
            COUNT(DISTINCT co.id) as total_sales,
            COALESCE(SUM(co.total_amount), 0) as total_revenue,
            COALESCE(AVG(co.total_amount), 0) as avg_sale_value,
            COUNT(DISTINCT co.customer_id) as unique_customers
        FROM customer_orders co
        WHERE DATE(co.order_date) BETWEEN :start_date AND :end_date
    ";

    $stmt = $db->prepare($salesQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $sales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Previous period comparison
    $prevStart = date('Y-m-d', strtotime($startDate . ' -' . ((strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24)) . ' days'));
    $prevEnd = $startDate;

    $stmt->bindParam(':start_date', $prevStart);
    $stmt->bindParam(':end_date', $prevEnd);
    $stmt->execute();
    $prevSales = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate growth percentages
    $salesGrowth = $prevSales['total_revenue'] > 0 ?
        (($sales['total_revenue'] - $prevSales['total_revenue']) / $prevSales['total_revenue']) * 100 : 0;

    // Inventory metrics
    $inventoryQuery = "
        SELECT
            COUNT(DISTINCT p.id) as total_products,
            SUM(i.quantity_on_hand) as total_stock_quantity,
            SUM(i.quantity_available) as total_available_stock,
            COUNT(CASE WHEN i.quantity_available <= p.reorder_level THEN 1 END) as low_stock_items,
            SUM(p.cost_price * i.quantity_on_hand) as total_inventory_value
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE p.is_active = 1
    ";

    $invStmt = $db->prepare($inventoryQuery);
    $invStmt->execute();
    $inventory = $invStmt->fetch(PDO::FETCH_ASSOC);

    // Customer metrics
    $customerQuery = "
        SELECT
            COUNT(DISTINCT c.id) as total_customers,
            COUNT(DISTINCT co.id) as total_orders,
            COALESCE(AVG(co.total_amount), 0) as avg_order_value
        FROM customers c
        LEFT JOIN customer_orders co ON c.id = co.customer_id
            AND DATE(co.order_date) BETWEEN :start_date AND :end_date
    ";

    $custStmt = $db->prepare($customerQuery);
    $custStmt->bindParam(':start_date', $startDate);
    $custStmt->bindParam(':end_date', $endDate);
    $custStmt->execute();
    $customers = $custStmt->fetch(PDO::FETCH_ASSOC);

    return [
        'sales' => [
            'total_sales' => (int)$sales['total_sales'],
            'total_revenue' => (float)$sales['total_revenue'],
            'avg_sale_value' => round((float)$sales['avg_sale_value'], 2),
            'unique_customers' => (int)$sales['unique_customers'],
            'growth_percentage' => round($salesGrowth, 2)
        ],
        'inventory' => [
            'total_products' => (int)$inventory['total_products'],
            'total_stock_quantity' => (int)$inventory['total_stock_quantity'],
            'total_available_stock' => (int)$inventory['total_available_stock'],
            'low_stock_items' => (int)$inventory['low_stock_items'],
            'total_inventory_value' => round((float)$inventory['total_inventory_value'], 2),

        ],
        'customers' => [
            'total_customers' => (int)$customers['total_customers'],
            'total_orders' => (int)$customers['total_orders'],
            'avg_order_value' => round((float)$customers['avg_order_value'], 2)
        ]
    ];
}

function getChartData($db, $startDate, $endDate) {
    // Sales by day - Pull from customer_orders (shop orders)
    $salesChartQuery = "
        SELECT
            DATE(co.order_date) as date,
            COUNT(*) as sales_count,
            SUM(co.total_amount) as revenue
        FROM customer_orders co
        WHERE DATE(co.order_date) BETWEEN :start_date AND :end_date
        GROUP BY DATE(co.order_date)
        ORDER BY date ASC
    ";

    $stmt = $db->prepare($salesChartQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top products by sales - Pull from customer_order_items
    $productsChartQuery = "
        SELECT
            p.name as product_name,
            SUM(coi.quantity) as total_sold,
            SUM(coi.total_price) as total_revenue
        FROM customer_order_items coi
        INNER JOIN products p ON coi.product_id = p.id
        INNER JOIN customer_orders co ON coi.order_id = co.id
        WHERE DATE(co.order_date) BETWEEN :start_date AND :end_date
        GROUP BY p.id, p.name
        ORDER BY total_revenue DESC
        LIMIT 10
    ";

    $stmt = $db->prepare($productsChartQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $productsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sales by category - Pull from customer_order_items
    $categoryChartQuery = "
        SELECT
            c.name as category_name,
            SUM(coi.quantity) as total_sold,
            SUM(coi.total_price) as total_revenue
        FROM customer_order_items coi
        INNER JOIN products p ON coi.product_id = p.id
        INNER JOIN categories c ON p.category_id = c.id
        INNER JOIN customer_orders co ON coi.order_id = co.id
        WHERE DATE(co.order_date) BETWEEN :start_date AND :end_date
        GROUP BY c.id, c.name
        ORDER BY total_revenue DESC
        LIMIT 10
    ";

    $stmt = $db->prepare($categoryChartQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment method distribution - Pull from customer_orders
    $paymentChartQuery = "
        SELECT
            co.payment_method,
            COUNT(*) as transaction_count,
            SUM(co.total_amount) as total_amount
        FROM customer_orders co
        WHERE DATE(co.order_date) BETWEEN :start_date AND :end_date
        GROUP BY co.payment_method
        ORDER BY total_amount DESC
    ";

    $stmt = $db->prepare($paymentChartQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $paymentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'sales_over_time' => $salesData,
        'top_products' => $productsData,
        'sales_by_category' => $categoryData,
        'payment_methods' => $paymentData
    ];
}

function getSystemAlerts($db) {
    $alerts = [];

    // Low stock alerts
    $lowStockQuery = "
        SELECT
            p.id,
            p.name,
            p.sku,
            i.quantity_available,
            p.reorder_level
        FROM products p
        INNER JOIN inventory i ON p.id = i.product_id
        WHERE p.is_active = 1
            AND i.quantity_available <= p.reorder_level
            AND i.quantity_available > 0
        ORDER BY i.quantity_available ASC
        LIMIT 5
    ";

    $stmt = $db->prepare($lowStockQuery);
    $stmt->execute();
    $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($lowStockItems as $item) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Low Stock Alert',
            'message' => "Product '{$item['name']}' has only {$item['quantity_available']} units remaining (reorder level: {$item['reorder_level']})",
            'entity_type' => 'product',
            'entity_id' => $item['id'],
            'action_required' => true
        ];
    }

    // Out of stock alerts
    $outOfStockQuery = "
        SELECT
            p.id,
            p.name,
            p.sku
        FROM products p
        INNER JOIN inventory i ON p.id = i.product_id
        WHERE p.is_active = 1 AND i.quantity_available <= 0
        LIMIT 5
    ";

    $stmt = $db->prepare($outOfStockQuery);
    $stmt->execute();
    $outOfStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($outOfStockItems as $item) {
        $alerts[] = [
            'type' => 'error',
            'title' => 'Out of Stock',
            'message' => "Product '{$item['name']}' is out of stock",
            'entity_type' => 'product',
            'entity_id' => $item['id'],
            'action_required' => true
        ];
    }

    // Pending orders alert
    $pendingOrdersQuery = "
        SELECT COUNT(*) as pending_count
        FROM purchase_orders
        WHERE status IN ('submitted', 'approved')
    ";

    $stmt = $db->prepare($pendingOrdersQuery);
    $stmt->execute();
    $pendingOrders = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pendingOrders['pending_count'] > 0) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Pending Purchase Orders',
            'message' => "There are {$pendingOrders['pending_count']} purchase orders awaiting approval",
            'entity_type' => 'purchase_order',
            'entity_id' => null,
            'action_required' => true
        ];
    }

    // Recent failed payments
    $failedPaymentsQuery = "
        SELECT COUNT(*) as failed_count
        FROM customer_orders
        WHERE payment_status = 'failed'
            AND order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ";

    $stmt = $db->prepare($failedPaymentsQuery);
    $stmt->execute();
    $failedPayments = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($failedPayments['failed_count'] > 0) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Failed Payments',
            'message' => "{$failedPayments['failed_count']} payments failed in the last 7 days",
            'entity_type' => 'payment',
            'entity_id' => null,
            'action_required' => true
        ];
    }

    return $alerts;
}

function getRecentActivity($db) {
    try {
        $activityQuery = "
            SELECT
                al.id,
                al.action,
                al.entity_type,
                al.entity_id,
                al.details,
                al.created_at,
                u.username,
                u.full_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 10
        ";

        $stmt = $db->prepare($activityQuery);
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function($activity) {
            $details = json_decode($activity['details'], true) ?: [];

            return [
                'id' => $activity['id'],
                'timestamp' => $activity['created_at'],
                'action' => $activity['action'],
                'entity_type' => $activity['entity_type'],
                'entity_id' => $activity['entity_id'],
                'user' => [
                    'username' => $activity['username'],
                    'full_name' => $activity['full_name']
                ],
                'details' => $details,
                'description' => generateActivityDescription($activity, $details)
            ];
        }, $activities);
    } catch (Exception $e) {
        // Return empty array if activity_logs table doesn't exist or query fails
        return [];
    }
}

function getTopPerformers($db, $startDate, $endDate) {
    $topProducts = [];
    $topCategories = [];
    $topCustomers = [];

    try {
        // Top selling products - Pull from customer_order_items
        $topProductsQuery = "
            SELECT
                p.id,
                p.name,
                p.sku,
                SUM(coi.quantity) as total_sold,
                SUM(coi.total_price) as total_revenue
            FROM customer_order_items coi
            INNER JOIN products p ON coi.product_id = p.id
            INNER JOIN customer_orders co ON coi.order_id = co.id
            WHERE DATE(co.order_date) BETWEEN :start_date AND :end_date
            GROUP BY p.id, p.name, p.sku
            ORDER BY total_revenue DESC
            LIMIT 5
        ";

        $stmt = $db->prepare($topProductsQuery);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Return empty array if query fails
        $topProducts = [];
    }

    try {
        // Top performing categories - Pull from customer_order_items
        $topCategoriesQuery = "
            SELECT
                c.id,
                c.name,
                SUM(coi.quantity) as total_sold,
                SUM(coi.total_price) as total_revenue
            FROM customer_order_items coi
            INNER JOIN products p ON coi.product_id = p.id
            INNER JOIN categories c ON p.category_id = c.id
            INNER JOIN customer_orders co ON coi.order_id = co.id
            WHERE DATE(co.order_date) BETWEEN :start_date AND :end_date
            GROUP BY c.id, c.name
            ORDER BY total_revenue DESC
            LIMIT 5
        ";

        $stmt = $db->prepare($topCategoriesQuery);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Return empty array if query fails
        $topCategories = [];
    }

    try {
        // Top customers by spending
        $topCustomersQuery = "
            SELECT
                c.id,
                c.first_name,
                c.last_name,
                c.email,
                COUNT(co.id) as order_count,
                SUM(co.total_amount) as total_spent
            FROM customers c
            INNER JOIN customer_orders co ON c.id = co.customer_id
            WHERE DATE(co.order_date) BETWEEN :start_date AND :end_date
            GROUP BY c.id, c.first_name, c.last_name, c.email
            ORDER BY total_spent DESC
            LIMIT 5
        ";

        $stmt = $db->prepare($topCustomersQuery);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Return empty array if query fails
        $topCustomers = [];
    }

    return [
        'products' => $topProducts,
        'categories' => $topCategories,
        'customers' => $topCustomers
    ];
}

function generateActivityDescription($activity, $details) {
    $user = $activity['full_name'] ?: $activity['username'] ?: 'System';

    switch ($activity['action']) {
        case 'login':
            return "{$user} logged in";
        case 'logout':
            return "{$user} logged out";
        case 'create':
            return "{$user} created a new {$activity['entity_type']}";
        case 'update':
            return "{$user} updated {$activity['entity_type']} #{$activity['entity_id']}";
        case 'delete':
            return "{$user} deleted {$activity['entity_type']} #{$activity['entity_id']}";
        case 'stock_adjustment':
            $type = $details['adjustment_type'] ?? 'unknown';
            return "{$user} made a {$type} stock adjustment";
        case 'sale_created':
            return "{$user} created sale #{$activity['entity_id']}";
        case 'order_placed':
            return "New customer order #{$activity['entity_id']} placed";
        default:
            return "{$user} performed action: {$activity['action']}";
    }
}

/**
 * Filter dashboard data based on user role
 *
 * Access Levels:
 * - Admin & Inventory Manager: Full access to all data
 * - Purchasing Officer: Limited access (no customer analytics, payment methods hidden)
 * - Staff: Basic access (sales and inventory only, no financial details or purchase orders)
 */
function filterDashboardByRole($dashboard, $role) {
    // Admin and Inventory Manager get full access
    if ($role === 'admin' || $role === 'inventory_manager') {
        return $dashboard;
    }

    // Purchasing Officer restrictions
    if ($role === 'purchasing_officer') {
        // Hide customer-specific data
        if (isset($dashboard['summary']['customers'])) {
            unset($dashboard['summary']['customers']['total_customers']);
            unset($dashboard['summary']['customers']['avg_order_value']);
        }

        // Hide payment method distribution (sensitive financial data)
        if (isset($dashboard['charts']['payment_methods'])) {
            unset($dashboard['charts']['payment_methods']);
        }

        // Hide customer analytics from top performers
        if (isset($dashboard['top_performers']['customers'])) {
            unset($dashboard['top_performers']['customers']);
        }

        // Filter alerts to show only inventory and purchase order related
        if (isset($dashboard['alerts'])) {
            $dashboard['alerts'] = array_filter($dashboard['alerts'], function($alert) {
                return in_array($alert['type'], ['warning', 'error', 'info']) &&
                       in_array($alert['entity_type'], ['product', 'purchase_order', 'inventory']);
            });
            $dashboard['alerts'] = array_values($dashboard['alerts']);
        }

        return $dashboard;
    }

    // Staff restrictions (most restrictive)
    if ($role === 'staff') {
        // Keep only basic sales metrics
        if (isset($dashboard['summary']['sales'])) {
            unset($dashboard['summary']['sales']['growth_percentage']);
        }

        // Hide detailed customer analytics
        if (isset($dashboard['summary']['customers'])) {
            unset($dashboard['summary']['customers']);
        }

        // Simplify inventory metrics (remove financial data)
        if (isset($dashboard['summary']['inventory'])) {
            unset($dashboard['summary']['inventory']['total_inventory_value']);
        }

        // Keep sales trend chart (sales_over_time)
        // Keep top products chart

        // Hide payment methods and customer analytics from charts
        if (isset($dashboard['charts']['payment_methods'])) {
            unset($dashboard['charts']['payment_methods']);
        }
        if (isset($dashboard['charts']['sales_by_category'])) {
            // Keep but simplify
            $dashboard['charts']['sales_by_category'] = array_slice($dashboard['charts']['sales_by_category'], 0, 5);
        }

        // Show only low stock and out of stock alerts (no purchase orders or payments)
        if (isset($dashboard['alerts'])) {
            $dashboard['alerts'] = array_filter($dashboard['alerts'], function($alert) {
                return $alert['entity_type'] === 'product';
            });
            $dashboard['alerts'] = array_values($dashboard['alerts']);
        }

        // Remove customer data from top performers
        if (isset($dashboard['top_performers']['customers'])) {
            unset($dashboard['top_performers']['customers']);
        }

        // REMOVE recent activity completely for staff
        if (isset($dashboard['recent_activity'])) {
            unset($dashboard['recent_activity']);
        }

        return $dashboard;
    }

    return $dashboard;
}
