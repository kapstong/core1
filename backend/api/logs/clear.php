<?php
/**
 * Clear Logs API Endpoint
 * POST /backend/api/logs/clear.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Check authentication and admin role
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

$user = Auth::user();
if ($user['role'] !== 'admin') {
    Response::error('Forbidden - Admin access required', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $logType = isset($input['log_type']) ? $input['log_type'] : 'all';

    $clearedLogs = [];
    $logsDir = __DIR__ . '/../../logs';

    // Define log files
    $logFiles = [
        'api_errors' => 'api_errors.log',
        'debug' => 'debug.log',
        'test_errors' => '../../logs/test_errors.log'
    ];

    if ($logType === 'all') {
        // Clear all log files
        foreach ($logFiles as $type => $file) {
            $fullPath = $logsDir . '/' . basename($file);
            if (file_exists($fullPath)) {
                file_put_contents($fullPath, '');
                $clearedLogs[] = $type;
            }
        }
    } else if (isset($logFiles[$logType])) {
        // Clear specific log file
        $fullPath = $logsDir . '/' . basename($logFiles[$logType]);
        if (file_exists($fullPath)) {
            file_put_contents($fullPath, '');
            $clearedLogs[] = $logType;
        }
    } else {
        Response::error('Invalid log type specified');
    }

    Response::success([
        'cleared_logs' => $clearedLogs,
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Logs cleared successfully');

} catch (Exception $e) {
    error_log("Clear Logs Error: " . $e->getMessage());
    Response::error('An error occurred while clearing logs: ' . $e->getMessage(), 500);
}
