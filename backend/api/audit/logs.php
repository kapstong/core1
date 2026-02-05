<?php
/**
 * Audit Logs API Endpoint
 * GET /backend/api/audit/logs.php
 */

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    // Require authentication (only admins can view audit logs)
    Auth::requireRole('admin');

    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 50;
    $offset = ($page - 1) * $limit;

    // Get filters
    $filters = [];

    if (!empty($_GET['action'])) {
        $filters['action'] = $_GET['action'];
    }

    if (!empty($_GET['entity_type'])) {
        $filters['entity_type'] = $_GET['entity_type'];
    }

    if (!empty($_GET['user_id'])) {
        $filters['user_id'] = intval($_GET['user_id']);
    }

    if (!empty($_GET['start_date'])) {
        $filters['start_date'] = $_GET['start_date'];
    }

    if (!empty($_GET['end_date'])) {
        $filters['end_date'] = $_GET['end_date'];
    }

    // Enhanced filtering for inventory system activities
    if (!empty($_GET['inventory_action'])) {
        $inventoryActions = explode(',', $_GET['inventory_action']);
        $validInventoryActions = ['create', 'update', 'delete'];

        $filteredActions = array_intersect($inventoryActions, $validInventoryActions);
        if (!empty($filteredActions)) {
            $filters['inventory_actions'] = $filteredActions;
        }
    }

    // Filter for inventory-related entity types
    if (!empty($_GET['inventory_entities']) && $_GET['inventory_entities'] === 'true') {
        $filters['inventory_entities'] = true;
    }

    // Get logs
    $logs = AuditLogger::getLogs($limit, $offset, $filters);
    $total = AuditLogger::getLogsCount($filters);

    Response::success([
        'logs' => $logs,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    error_log("Audit logs error: " . $e->getMessage());
    Response::serverError('An error occurred while fetching audit logs');
}

