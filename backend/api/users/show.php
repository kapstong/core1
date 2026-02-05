<?php
/**
 * Users API - Get single user
 * GET /backend/api/users/show.php?id=1
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

Auth::requireAuth();

if ($_SESSION['user_role'] !== 'admin') {
    Response::error('Access denied', 403);
}

$id = $_GET['id'] ?? null;

if (!$id) {
    Response::error('User ID is required', 400);
}

try {
    $db = Database::getInstance()->getConnection();

    $query = "SELECT id, username, full_name, email, role, is_active, last_login, created_at
              FROM users WHERE id = ? AND deleted_at IS NULL";

    $stmt = $db->prepare($query);
    $stmt->execute([$id]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        Response::error('User not found', 404);
    }

    Response::success($user);
} catch (PDOException $e) {
    Response::error('Database error: ' . $e->getMessage(), 500);
}

