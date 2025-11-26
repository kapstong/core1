<?php
/**
 * Clear Cache API Endpoint
 * POST /backend/api/settings/clear-cache.php
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
    $cleared = [];

    // Clear session cache
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
        session_start();
        $cleared[] = 'Session cache';
    }

    // Clear opcode cache if available
    if (function_exists('opcache_reset')) {
        opcache_reset();
        $cleared[] = 'OpCache';
    }

    // Clear any custom cache files (if implemented)
    $cacheDir = __DIR__ . '/../../cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $cleared[] = 'File cache (' . count($files) . ' files)';
    }

    Response::success([
        'cleared' => $cleared,
        'timestamp' => date('Y-m-d H:i:s')
    ], 'Cache cleared successfully');

} catch (Exception $e) {
    error_log("Clear Cache Error: " . $e->getMessage());
    Response::error('An error occurred while clearing cache: ' . $e->getMessage(), 500);
}
