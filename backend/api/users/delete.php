<?php
/**
 * Users API - Delete user
 * DELETE /backend/api/users/delete.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

Auth::requireAuth();

if ($_SESSION['user_role'] !== 'admin') {
    Response::error('Access denied', 403);
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['id'])) {
    Response::error('User ID is required', 400);
}

// Prevent deleting own account
if ($data['id'] == $_SESSION['user_id']) {
    Response::error('Cannot delete your own account', 400);
}

try {
    $db = Database::getInstance()->getConnection();

    // Check if user exists and get their role
    $check = $db->prepare("SELECT id, role, username, full_name, email, is_active FROM users WHERE id = ?");
    $check->execute([$data['id']]);
    $userToDelete = $check->fetch();
    if (!$userToDelete) {
        Response::error('User not found', 404);
    }

    // Prevent deleting admin accounts - only allow deletion of lower position accounts
    if ($userToDelete['role'] === 'admin') {
        Response::error('Cannot delete admin accounts', 403);
    }

    // Soft delete user
    $stmt = $db->prepare("UPDATE users SET is_active = 0, deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$data['id']]);

    AuditLogger::logDelete('user', (int)$data['id'], "User '{$userToDelete['username']}' soft-deleted", [
        'username' => $userToDelete['username'],
        'full_name' => $userToDelete['full_name'],
        'email' => $userToDelete['email'],
        'role' => $userToDelete['role'],
        'deleted_by' => $_SESSION['username'] ?? 'Unknown'
    ]);

    Response::success(['message' => 'User deleted successfully']);
} catch (PDOException $e) {
    Response::error('Database error: ' . $e->getMessage(), 500);
}
