<?php
/**
 * Logout All Sessions API Endpoint
 * POST /backend/api/auth/logout-all.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    if (!Auth::check()) {
        Response::error('Unauthorized', 401);
    }

    $userId = Auth::userId();
    $username = $_SESSION['username'] ?? 'Unknown';

    $db = Database::getInstance()->getConnection();

    // Invalidate remember-me tokens
    try {
        $stmt = $db->prepare("DELETE FROM persistent_login_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        // Ignore if table doesn't exist
    }

    // Invalidate 2FA bypass records
    try {
        $stmt = $db->prepare("DELETE FROM 2fa_bypass_records WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        // Ignore if table doesn't exist
    }

    // Clear active login sessions
    try {
        $stmt = $db->prepare("UPDATE login_sessions SET is_active = 0, logout_time = NOW() WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        // Ignore if table doesn't exist
    }

    // Remove any pending 2FA codes
    try {
        $stmt = $db->prepare("DELETE FROM verification_codes WHERE user_id = ? AND code_type = '2fa'");
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        // Ignore if table doesn't exist
    }

    // Clear remember_token cookie if present
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }

    // Audit log
    try {
        $logger = new Logger($userId);
        $logger->log('logout_all', 'user', $userId);
        AuditLogger::logLogout($userId, $username);
    } catch (Exception $e) {
        // Ignore logging failures
    }

    // Logout current session
    Auth::logout();

    Response::success(null, 'Logged out from all sessions');
} catch (Exception $e) {
    Logger::logError($e->getMessage(), ['file' => __FILE__]);
    Response::serverError('An error occurred while logging out all sessions');
}
