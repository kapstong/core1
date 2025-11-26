<?php
/**
 * Users API - Change password
 * POST /backend/api/users/change-password.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/User.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Require authentication
$user = Auth::requireAuth();

// Get current user data
$currentUser = Auth::user();
$userId = $currentUser['id'];

$data = json_decode(file_get_contents('php://input'), true);

try {
    $db = Database::getInstance()->getConnection();
    $userModel = new User();

    // Manual validation using existing Validator methods
    $errors = [];

    // Validate current password
    if (empty($data['current_password'])) {
        $errors[] = 'Current password is required';
    }

    // Validate new password
    if (empty($data['new_password'])) {
        $errors[] = 'New password is required';
    } elseif (!Validator::minLength($data['new_password'], 6)) {
        $errors[] = 'New password must be at least 6 characters';
    }

    if (!empty($errors)) {
        Response::error('Validation failed: ' . implode(', ', $errors), 400);
    }

    // Get current user from database to verify current password
    $userData = $userModel->findById($userId);
    if (!$userData) {
        Response::error('User not found', 404);
    }

    // Verify current password
    if (!password_verify($data['current_password'], $userData['password'])) {
        Response::error('Current password is incorrect', 400);
    }

    // Check if new password is different from current
    if (password_verify($data['new_password'], $userData['password'])) {
        Response::error('New password must be different from current password', 400);
    }

    // Hash new password
    $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);

    // Update password in database
    $result = $userModel->updatePassword($userId, $hashedPassword);

    if ($result) {
        // Log the password change action
        $logger = new Logger($currentUser['id']);
        $logger->log('Password changed', 'user', $userId, [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        Response::success([
            'message' => 'Password changed successfully'
        ]);
    } else {
        Response::error('Failed to update password', 500);
    }

} catch (Exception $e) {
    Logger::logError('Password change error', [
        'user_id' => $userId,
        'error' => $e->getMessage()
    ], $currentUser['id']);
    Response::error('An error occurred while changing your password', 500);
}
