<?php
/**
 * Users API - Reset user password
 * POST /backend/api/users/reset-password.php?id={user_id}
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    // Check authentication
    if (!Auth::check()) {
        Response::error('Unauthorized', 401);
    }

    $user = Auth::user();

    // Only admins can reset passwords
    if (!$user || $user['role'] !== 'admin') {
        Response::error('Access denied. Admin privileges required.', 403);
    }

    // Get user ID from query parameter
    $targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$targetUserId) {
        Response::error('User ID is required', 400);
    }

    // Cannot reset your own password through this endpoint
    if ($targetUserId === $user['id']) {
        Response::error('Cannot reset your own password through this endpoint', 400);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if target user exists
    $checkStmt = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $checkStmt->execute([$targetUserId]);
    $targetUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        Response::error('User not found', 404);
    }

    // Generate a temporary password
    $tempPassword = bin2hex(random_bytes(8)); // 16 character random password
    $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

    // Update password
    $updateStmt = $conn->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$passwordHash, $targetUserId]);

    // Log the action
    require_once __DIR__ . '/../../utils/Logger.php';
    $logger = new Logger($user['id']);
    $logger->log('password_reset', 'users', $targetUserId, [
        'target_user' => $targetUser['username'],
        'target_role' => $targetUser['role'],
        'action' => 'admin_reset'
    ]);

    Response::success([
        'message' => 'Password reset successfully',
        'temp_password' => $tempPassword,
        'user' => [
            'id' => $targetUser['id'],
            'username' => $targetUser['username']
        ]
    ]);

} catch (Exception $e) {
    Response::serverError('An error occurred while resetting password');
}
?>
