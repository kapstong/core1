<?php
/**
 * Activity Logs API Endpoint
 * GET /backend/api/logs/index.php - Get activity logs for audit and monitoring
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
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

// Check if user has permission to view logs (admin or inventory_manager)
if (!in_array($user['role'], ['admin', 'inventory_manager'])) {
    Response::error('Access denied. Admin or inventory manager role required', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        getActivityLogs();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Logs operation failed: ' . $e->getMessage());
}

function getActivityLogs() {
    $logger = new Logger();

    // Get query parameters
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $entityType = isset($_GET['entity_type']) ? $_GET['entity_type'] : null;
    $action = isset($_GET['action']) ? $_GET['action'] : null;
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    $ipAddress = isset($_GET['ip_address']) ? $_GET['ip_address'] : null;

    // Validate limit
    if ($limit < 1 || $limit > 500) {
        $limit = 50;
    }

    // Get logs
    $logs = $logger->getRecentActivities($limit, $userId, $entityType, $startDate, $endDate);

    // Apply additional filters that Logger doesn't handle
    if ($action) {
        $logs = array_filter($logs, function($log) use ($action) {
            return $log['action'] === $action;
        });
    }

    if ($ipAddress) {
        $logs = array_filter($logs, function($log) use ($ipAddress) {
            return $log['ip_address'] === $ipAddress;
        });
    }

    // Re-index array after filtering
    $logs = array_values($logs);

    // Get total count for pagination (this is an approximation since we filtered)
    $totalCount = count($logs);

    // Apply offset and limit after filtering
    $logs = array_slice($logs, $offset, $limit);

    // Format logs
    $formattedLogs = array_map(function($log) {
        $details = json_decode($log['details'], true) ?: [];

        return [
            'id' => $log['id'],
            'timestamp' => $log['created_at'],
            'user' => [
                'id' => $log['user_id'],
                'username' => $log['username'],
                'full_name' => $log['full_name']
            ],
            'action' => $log['action'],
            'entity' => [
                'type' => $log['entity_type'],
                'id' => $log['entity_id']
            ],
            'details' => $details,
            'client_info' => [
                'ip_address' => $log['ip_address'],
                'user_agent' => $log['user_agent']
            ]
        ];
    }, $logs);

    // Get summary statistics
    $summary = getLogsSummary($userId, $entityType, $startDate, $endDate);

    Response::success([
        'logs' => $formattedLogs,
        'pagination' => [
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ],
        'summary' => $summary,
        'filters' => [
            'user_id' => $userId,
            'entity_type' => $entityType,
            'action' => $action,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'ip_address' => $ipAddress
        ]
    ]);
}

function getLogsSummary($userId = null, $entityType = null, $startDate = null, $endDate = null) {
    $db = Database::getInstance()->getConnection();

    // Build base query
    $query = "SELECT
                COUNT(*) as total_logs,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(CASE WHEN entity_type = 'security' THEN 1 END) as security_events,
                COUNT(CASE WHEN entity_type = 'error' THEN 1 END) as error_events
              FROM activity_logs
              WHERE 1=1";

    $params = [];

    if ($userId) {
        $query .= " AND user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    if ($entityType) {
        $query .= " AND entity_type = :entity_type";
        $params[':entity_type'] = $entityType;
    }

    if ($startDate) {
        $query .= " AND DATE(created_at) >= :start_date";
        $params[':start_date'] = $startDate;
    }

    if ($endDate) {
        $query .= " AND DATE(created_at) <= :end_date";
        $params[':end_date'] = $endDate;
    }

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get action breakdown
    $actionQuery = "SELECT action, COUNT(*) as count
                    FROM activity_logs
                    WHERE 1=1";

    if ($userId) $actionQuery .= " AND user_id = :user_id";
    if ($entityType) $actionQuery .= " AND entity_type = :entity_type";
    if ($startDate) $actionQuery .= " AND DATE(created_at) >= :start_date";
    if ($endDate) $actionQuery .= " AND DATE(created_at) <= :end_date";

    $actionQuery .= " GROUP BY action ORDER BY count DESC LIMIT 10";

    $actionStmt = $db->prepare($actionQuery);
    foreach ($params as $key => $value) {
        $actionStmt->bindValue($key, $value);
    }
    $actionStmt->execute();

    $actions = $actionStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get entity type breakdown
    $entityQuery = "SELECT entity_type, COUNT(*) as count
                    FROM activity_logs
                    WHERE entity_type IS NOT NULL";

    if ($userId) $entityQuery .= " AND user_id = :user_id";
    if ($startDate) $entityQuery .= " AND DATE(created_at) >= :start_date";
    if ($endDate) $entityQuery .= " AND DATE(created_at) <= :end_date";

    $entityQuery .= " GROUP BY entity_type ORDER BY count DESC";

    $entityStmt = $db->prepare($entityQuery);
    $entityParams = array_filter($params, function($key) {
        return $key !== ':entity_type';
    }, ARRAY_FILTER_USE_KEY);
    foreach ($entityParams as $key => $value) {
        $entityStmt->bindValue($key, $value);
    }
    $entityStmt->execute();

    $entities = $entityStmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'total_logs' => (int)$summary['total_logs'],
        'unique_users' => (int)$summary['unique_users'],
        'unique_ips' => (int)$summary['unique_ips'],
        'security_events' => (int)$summary['security_events'],
        'error_events' => (int)$summary['error_events'],
        'top_actions' => $actions,
        'entity_breakdown' => $entities
    ];
}
