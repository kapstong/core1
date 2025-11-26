<?php
/**
 * Analytics and Tracking API Endpoint
 * GET /backend/api/analytics/index.php - Get analytics data
 * POST /backend/api/analytics/index.php - Track events
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication and role
Auth::requireRole(['admin', 'inventory_manager']);

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            getAnalytics();
            break;

        case 'POST':
            trackEvent();
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Analytics operation failed: ' . $e->getMessage());
}

function getAnalytics() {
    $db = Database::getInstance()->getConnection();

    $type = isset($_GET['type']) ? $_GET['type'] : 'overview';
    $period = isset($_GET['period']) ? $_GET['period'] : '30'; // days
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime("-{$period} days"));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

    switch ($type) {
        case 'sales':
            $analytics = getSalesAnalytics($db, $startDate, $endDate);
            break;

        case 'users':
            $analytics = getUserAnalytics($db, $startDate, $endDate);
            break;

        case 'products':
            $analytics = getProductAnalytics($db, $startDate, $endDate);
            break;

        case 'traffic':
            $analytics = getTrafficAnalytics($db, $startDate, $endDate);
            break;

        case 'overview':
        default:
            $analytics = getOverviewAnalytics($db, $startDate, $endDate);
            break;
    }

    Response::success($analytics);
}

function getOverviewAnalytics($db, $startDate, $endDate) {
    // Key metrics
    $metrics = [];

    try {
        // Sales metrics
        $salesQuery = "
            SELECT
                COUNT(DISTINCT s.id) as total_sales,
                COALESCE(SUM(s.total_amount), 0) as total_revenue,
                COALESCE(AVG(s.total_amount), 0) as avg_order_value,
                COUNT(DISTINCT s.customer_name) as unique_customers
            FROM sales s
            WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        ";

        $stmt = $db->prepare($salesQuery);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        $sales = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics['sales'] = [
            'total_sales' => (int)($sales['total_sales'] ?? 0),
            'total_revenue' => (float)($sales['total_revenue'] ?? 0),
            'avg_order_value' => round((float)($sales['avg_order_value'] ?? 0), 2),
            'unique_customers' => (int)($sales['unique_customers'] ?? 0)
        ];
    } catch (Exception $e) {
        $metrics['sales'] = [
            'total_sales' => 0,
            'total_revenue' => 0.0,
            'avg_order_value' => 0.0,
            'unique_customers' => 0
        ];
    }

    try {
        // Customer metrics (simplified - just count customers)
        $customerQuery = "
            SELECT COUNT(DISTINCT id) as total_customers
            FROM customers
            WHERE is_active = 1
        ";

        $stmt = $db->prepare($customerQuery);
        $stmt->execute();
        $customers = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics['customers'] = [
            'total_customers' => (int)($customers['total_customers'] ?? 0),
            'total_orders' => 0, // Simplified
            'avg_order_value' => 0.0 // Simplified
        ];
    } catch (Exception $e) {
        $metrics['customers'] = [
            'total_customers' => 0,
            'total_orders' => 0,
            'avg_order_value' => 0.0
        ];
    }

    try {
        // Product metrics
        $productQuery = "
            SELECT
                COUNT(DISTINCT p.id) as total_products,
                COALESCE(SUM(i.quantity_on_hand), 0) as total_inventory,
                COUNT(CASE WHEN COALESCE(i.quantity_on_hand, 0) <= COALESCE(p.reorder_level, 10) THEN 1 END) as low_stock_items
            FROM products p
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE p.is_active = 1
        ";

        $stmt = $db->prepare($productQuery);
        $stmt->execute();
        $products = $stmt->fetch(PDO::FETCH_ASSOC);

        $metrics['products'] = [
            'total_products' => (int)($products['total_products'] ?? 0),
            'total_inventory' => (int)($products['total_inventory'] ?? 0),
            'low_stock_items' => (int)($products['low_stock_items'] ?? 0)
        ];
    } catch (Exception $e) {
        $metrics['products'] = [
            'total_products' => 0,
            'total_inventory' => 0,
            'low_stock_items' => 0
        ];
    }

    // Traffic metrics (simplified - may not have analytics_events table)
    $metrics['traffic'] = [
        'total_events' => 0,
        'unique_sessions' => 0,
        'unique_users' => 0,
        'unique_ips' => 0
    ];

    // Top pages/events (simplified)
    $topPages = [];

    return [
        'type' => 'overview',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'metrics' => $metrics,
        'top_pages' => $topPages
    ];
}

function getSalesAnalytics($db, $startDate, $endDate) {
    // Sales by day
    $dailySalesQuery = "
        SELECT
            DATE(s.sale_date) as date,
            COUNT(*) as sales_count,
            SUM(s.total_amount) as revenue,
            AVG(s.total_amount) as avg_order_value
        FROM sales s
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY DATE(s.sale_date)
        ORDER BY date ASC
    ";

    $stmt = $db->prepare($dailySalesQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $dailySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sales by payment method
    $paymentMethodQuery = "
        SELECT
            s.payment_method,
            COUNT(*) as transaction_count,
            SUM(s.total_amount) as total_amount,
            AVG(s.total_amount) as avg_amount
        FROM sales s
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY s.payment_method
        ORDER BY total_amount DESC
    ";

    $stmt = $db->prepare($paymentMethodQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sales by hour of day
    $hourlySalesQuery = "
        SELECT
            HOUR(s.sale_date) as hour,
            COUNT(*) as sales_count,
            SUM(s.total_amount) as revenue
        FROM sales s
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY HOUR(s.sale_date)
        ORDER BY hour ASC
    ";

    $stmt = $db->prepare($hourlySalesQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $hourlySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'sales',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'daily_sales' => $dailySales,
        'payment_methods' => $paymentMethods,
        'hourly_sales' => $hourlySales
    ];
}

function getUserAnalytics($db, $startDate, $endDate) {
    // User registration trends
    $registrationQuery = "
        SELECT
            DATE(c.created_at) as date,
            COUNT(*) as registrations
        FROM customers c
        WHERE DATE(c.created_at) BETWEEN :start_date AND :end_date
        GROUP BY DATE(c.created_at)
        ORDER BY date ASC
    ";

    $stmt = $db->prepare($registrationQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // User login activity
    $loginQuery = "
        SELECT
            DATE(al.created_at) as date,
            COUNT(*) as login_events
        FROM activity_logs al
        WHERE al.action = 'login'
            AND DATE(al.created_at) BETWEEN :start_date AND :end_date
        GROUP BY DATE(al.created_at)
        ORDER BY date ASC
    ";

    $stmt = $db->prepare($loginQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $logins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Customer order frequency
    $orderFrequencyQuery = "
        SELECT
            order_count,
            COUNT(*) as customer_count
        FROM (
            SELECT
                c.id,
                COUNT(co.id) as order_count
            FROM customers c
            LEFT JOIN customer_orders co ON c.id = co.customer_id
                AND DATE(co.order_date) BETWEEN :start_date AND :end_date
            WHERE c.is_active = 1
            GROUP BY c.id
        ) customer_orders
        GROUP BY order_count
        ORDER BY order_count ASC
    ";

    $stmt = $db->prepare($orderFrequencyQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $orderFrequency = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'users',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'registrations' => $registrations,
        'logins' => $logins,
        'order_frequency' => $orderFrequency
    ];
}

function getProductAnalytics($db, $startDate, $endDate) {
    // Top selling products
    $topProductsQuery = "
        SELECT
            p.id,
            p.name,
            p.sku,
            SUM(si.quantity) as total_sold,
            SUM(si.quantity * si.unit_price) as total_revenue,
            COUNT(DISTINCT s.id) as order_count,
            AVG(si.unit_price) as avg_price
        FROM sale_items si
        INNER JOIN products p ON si.product_id = p.id
        INNER JOIN sales s ON si.sale_id = s.id
        WHERE DATE(s.sale_date) BETWEEN :start_date AND :end_date
        GROUP BY p.id, p.name, p.sku
        ORDER BY total_revenue DESC
        LIMIT 20
    ";

    $stmt = $db->prepare($topProductsQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Product view trends (from analytics)
    $productViewsQuery = "
        SELECT
            ae.page_url,
            COUNT(*) as view_count
        FROM analytics_events ae
        WHERE ae.event_type = 'product_view'
            AND DATE(ae.created_at) BETWEEN :start_date AND :end_date
        GROUP BY ae.page_url
        ORDER BY view_count DESC
        LIMIT 20
    ";

    $stmt = $db->prepare($productViewsQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $productViews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'products',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'top_products' => $topProducts,
        'product_views' => $productViews
    ];
}

function getTrafficAnalytics($db, $startDate, $endDate) {
    // Page views by URL
    $pageViewsQuery = "
        SELECT
            page_url,
            COUNT(*) as view_count,
            COUNT(DISTINCT session_id) as unique_sessions,
            AVG(duration) as avg_duration
        FROM analytics_events
        WHERE event_type = 'page_view'
            AND DATE(created_at) BETWEEN :start_date AND :end_date
        GROUP BY page_url
        ORDER BY view_count DESC
        LIMIT 20
    ";

    $stmt = $db->prepare($pageViewsQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $pageViews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Traffic by hour
    $hourlyTrafficQuery = "
        SELECT
            HOUR(created_at) as hour,
            COUNT(*) as event_count,
            COUNT(DISTINCT session_id) as unique_sessions
        FROM analytics_events
        WHERE DATE(created_at) BETWEEN :start_date AND :end_date
        GROUP BY HOUR(created_at)
        ORDER BY hour ASC
    ";

    $stmt = $db->prepare($hourlyTrafficQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $hourlyTraffic = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Device/browser breakdown
    $deviceQuery = "
        SELECT
            device_type,
            browser,
            COUNT(*) as session_count
        FROM analytics_events
        WHERE DATE(created_at) BETWEEN :start_date AND :end_date
            AND device_type IS NOT NULL
        GROUP BY device_type, browser
        ORDER BY session_count DESC
        LIMIT 20
    ";

    $stmt = $db->prepare($deviceQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Geographic data (by IP/country if available)
    $geoQuery = "
        SELECT
            country,
            region,
            city,
            COUNT(*) as visit_count,
            COUNT(DISTINCT session_id) as unique_visitors
        FROM analytics_events
        WHERE DATE(created_at) BETWEEN :start_date AND :end_date
            AND country IS NOT NULL
        GROUP BY country, region, city
        ORDER BY visit_count DESC
        LIMIT 20
    ";

    $stmt = $db->prepare($geoQuery);
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
    $stmt->execute();
    $geographic = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'type' => 'traffic',
        'period' => ['start' => $startDate, 'end' => $endDate],
        'page_views' => $pageViews,
        'hourly_traffic' => $hourlyTraffic,
        'devices' => $devices,
        'geographic' => $geographic
    ];
}

function trackEvent() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    if (!isset($input['event_type'])) {
        Response::error('event_type is required', 400);
    }

    $db = Database::getInstance()->getConnection();

    // Get session info
    $sessionId = session_id();
    $userId = $_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Prepare event data
    $eventData = [
        'event_type' => $input['event_type'],
        'session_id' => $sessionId,
        'user_id' => $userId,
        'ip_address' => $ipAddress,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'page_url' => $input['page_url'] ?? $_SERVER['REQUEST_URI'] ?? '',
        'referrer' => $input['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? ''),
        'device_type' => detectDeviceType(),
        'browser' => detectBrowser(),
        'country' => $input['country'] ?? null,
        'region' => $input['region'] ?? null,
        'city' => $input['city'] ?? null,
        'duration' => isset($input['duration']) ? (int)$input['duration'] : null,
        'event_data' => isset($input['event_data']) ? json_encode($input['event_data']) : null
    ];

    // Insert event
    $insertQuery = "INSERT INTO analytics_events
                   (event_type, session_id, user_id, ip_address, user_agent, page_url,
                    referrer, device_type, browser, country, region, city, duration, event_data)
                   VALUES
                   (:event_type, :session_id, :user_id, :ip_address, :user_agent, :page_url,
                    :referrer, :device_type, :browser, :country, :region, :city, :duration, :event_data)";

    $stmt = $db->prepare($insertQuery);

    foreach ($eventData as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }

    if (!$stmt->execute()) {
        Response::error('Failed to track event', 500);
    }

    Response::success([
        'message' => 'Event tracked successfully',
        'event_id' => $db->lastInsertId()
    ], 201);
}

function detectDeviceType() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (preg_match('/mobile/i', $userAgent)) {
        return 'mobile';
    } elseif (preg_match('/tablet/i', $userAgent)) {
        return 'tablet';
    } else {
        return 'desktop';
    }
}

function detectBrowser() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (preg_match('/Chrome/i', $userAgent)) {
        return 'Chrome';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        return 'Firefox';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        return 'Safari';
    } elseif (preg_match('/Edge/i', $userAgent)) {
        return 'Edge';
    } elseif (preg_match('/Opera/i', $userAgent)) {
        return 'Opera';
    } else {
        return 'Other';
    }
}
