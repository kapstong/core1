<?php
/**
 * Users API - Create new user
 * POST /backend/api/users/create.php
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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

Auth::requireAuth();

$user = Auth::user();
if ($user['role'] !== 'admin') {
    Response::error('Access denied', 403);
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['username', 'email', 'password', 'role'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        Response::error("Field '{$field}' is required", 400);
    }
}

// Validate role - exclude supplier since they are managed separately
$allowed_roles = ['admin', 'inventory_manager', 'purchasing_officer', 'staff'];
if (!in_array($data['role'], $allowed_roles)) {
    Response::error('Invalid role', 400);
}

try {
    $db = Database::getInstance()->getConnection();

    // Check if username already exists
    $check = $db->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$data['username']]);
    if ($check->fetch()) {
        Response::error('Username already exists', 400);
    }

    // Check if email already exists
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$data['email']]);
    if ($check->fetch()) {
        Response::error('Email already exists', 400);
    }

    // Hash password
    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);

    // Insert user
    $query = "INSERT INTO users (username, password_hash, full_name, email, role, is_active, created_at)
              VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $db->prepare($query);
    $stmt->execute([
        $data['username'],
        $password_hash,
        $data['full_name'] ?? null,
        $data['email'],
        $data['role'],
        $data['is_active'] ?? 1
    ]);

    $userId = $db->lastInsertId();

    Response::success([
        'message' => 'User created successfully',
        'user_id' => $userId
    ], 201);
} catch (PDOException $e) {
    Response::error('Database error: ' . $e->getMessage(), 500);
}
