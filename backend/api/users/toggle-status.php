<?php
/**
 * Users API - Toggle user active status
 * POST /backend/api/users/toggle-status.php?id={user_id}
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

    // Only admins can toggle user status
    if (!$user || $user['role'] !== 'admin') {
        Response::error('Access denied. Admin privileges required.', 403);
    }

    // Get user ID from query parameter
    $targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$targetUserId) {
        Response::error('User ID is required', 400);
    }

    // Cannot deactivate yourself
    if ($targetUserId === $user['id']) {
        Response::error('Cannot deactivate your own account', 400);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if target user exists
    $checkStmt = $conn->prepare("SELECT id, username, role, is_active FROM users WHERE id = ?");
    $checkStmt->execute([$targetUserId]);
    $targetUser = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$targetUser) {
        Response::error('User not found', 404);
    }

    // Toggle the status
    $newStatus = $targetUser['is_active'] == 1 ? 0 : 1;

    // Update user status
    $updateStmt = $conn->prepare("UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$newStatus, $targetUserId]);

    // Log the action
    require_once __DIR__ . '/../../utils/Logger.php';
    $logger = new Logger($user['id']);
    $logger->log('user_status_toggle', 'users', $targetUserId, [
        'target_user' => $targetUser['username'],
        'target_role' => $targetUser['role'],
        'old_status' => $targetUser['is_active'],
        'new_status' => $newStatus,
        'action' => $newStatus ? 'activated' : 'deactivated'
    ]);

    Response::success([
        'message' => "User " . ($newStatus ? 'activated' : 'deactivated') . " successfully",
        'user' => [
            'id' => $targetUser['id'],
            'username' => $targetUser['username'],
            'is_active' => $newStatus
        ]
    ]);

} catch (Exception $e) {
    Response::serverError('An error occurred while updating user status');
}
?>
