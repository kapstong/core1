<?php
/**
 * Activity Logs Export Endpoint
 * GET /backend/api/logs/export.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

try {
    // Require admin authentication
    Auth::requireRole('admin');

    $db = Database::getInstance()->getConnection();

    // Build query with filters
    $where = [];
    $params = [];

    if (!empty($_GET['user_id']) && $_GET['user_id'] !== 'all') {
        $where[] = "user_id = ?";
        $params[] = $_GET['user_id'];
    }

    if (!empty($_GET['action_type']) && $_GET['action_type'] !== 'all') {
        $where[] = "action_type = ?";
        $params[] = $_GET['action_type'];
    }

    if (!empty($_GET['date_from'])) {
        $where[] = "created_at >= ?";
        $params[] = $_GET['date_from'] . ' 00:00:00';
    }

    if (!empty($_GET['date_to'])) {
        $where[] = "created_at <= ?";
        $params[] = $_GET['date_to'] . ' 23:59:59';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get activity logs
    $sql = "SELECT
                al.id,
                al.user_id,
                u.username,
                al.action_type,
                al.entity_type,
                al.entity_id,
                al.description,
                al.ip_address,
                al.created_at
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$whereClause}
            ORDER BY al.created_at DESC
            LIMIT 10000";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Header
    fputcsv($output, [
        'ID',
        'Timestamp',
        'User ID',
        'Username',
        'Action Type',
        'Entity Type',
        'Entity ID',
        'Description',
        'IP Address'
    ]);

    // Data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['created_at'],
            $log['user_id'] ?? '',
            $log['username'] ?? 'System',
            $log['action_type'],
            $log['entity_type'],
            $log['entity_id'] ?? '',
            $log['description'],
            $log['ip_address'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Activity logs export error: " . $e->getMessage());
    http_response_code(500);
    echo "Error exporting activity logs";
}

