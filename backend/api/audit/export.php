<?php
/**
 * Audit Logs Export API Endpoint
 * GET /backend/api/audit/export.php
 * Exports audit logs to CSV format
 */

// Suppress error display for clean output
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');

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
    // Require authentication (only admins can export audit logs)
    Auth::requireRole('admin');

    // Get filters (same as the logs.php endpoint)
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

    // Get all logs (no pagination for export)
    $logs = AuditLogger::getLogs(10000, 0, $filters); // Max 10000 records

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Add CSV header
    fputcsv($output, [
        'ID',
        'Timestamp',
        'User ID',
        'Username',
        'Action',
        'Entity Type',
        'Entity ID',
        'Description',
        'IP Address',
        'User Agent'
    ]);

    // Add data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['created_at'],
            $log['user_id'] ?? '',
            $log['username'] ?? 'System',
            $log['action'],
            $log['entity_type'],
            $log['entity_id'] ?? '',
            $log['description'],
            $log['ip_address'] ?? '',
            $log['user_agent'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Audit logs export error: " . $e->getMessage());
    header('Content-Type: application/json');
    Response::serverError('An error occurred while exporting audit logs');
}
