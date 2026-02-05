<?php
/**
 * Logout API Endpoint
 * POST /backend/api/auth/logout.php
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
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $userId = Auth::userId();
    $username = $_SESSION['username'] ?? 'Unknown';

    // Log logout
    if ($userId) {
        $logger = new Logger($userId);
        $logger->log('logout', 'user', $userId);

        // Audit log
        AuditLogger::logLogout($userId, $username);
    }

    // Logout user
    Auth::logout();

    Response::success(null, 'Logged out successfully');

} catch (Exception $e) {
    Logger::logError($e->getMessage(), ['file' => __FILE__]);
    Response::serverError('An error occurred during logout');
}

