<?php
/**
 * Users API - Create new user
 * POST /backend/api/users/create.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/User.php';

function userTableRequiresExplicitId(PDO $conn) {
    try {
        $schemaStmt = $conn->query('SELECT DATABASE() AS db_name');
        $schemaRow = $schemaStmt->fetch(PDO::FETCH_ASSOC);
        $schemaName = $schemaRow['db_name'] ?? null;
        if (!$schemaName) {
            return false;
        }

        $stmt = $conn->prepare("
            SELECT IS_NULLABLE, COLUMN_DEFAULT, EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :schema
              AND TABLE_NAME = 'users'
              AND COLUMN_NAME = 'id'
            LIMIT 1
        ");
        $stmt->execute([':schema' => $schemaName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $extra = strtolower((string)($row['EXTRA'] ?? ''));
        $isNullable = strtoupper((string)($row['IS_NULLABLE'] ?? 'YES')) === 'YES';
        $hasDefault = array_key_exists('COLUMN_DEFAULT', $row) && $row['COLUMN_DEFAULT'] !== null;

        return strpos($extra, 'auto_increment') === false && !$isNullable && !$hasDefault;
    } catch (Throwable $e) {
        return false;
    }
}

function getNextUserId(PDO $conn) {
    $stmt = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM users");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = isset($row['next_id']) ? (int)$row['next_id'] : 1;
    return $nextId > 0 ? $nextId : 1;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$currentUser = Auth::requireAuth();
if (($currentUser['role'] ?? '') !== 'admin') {
    Response::error('Access denied. Admin privileges required.', 403);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

try {
    $database = Database::getInstance();
    $userModel = new User();
    $db = $database->getConnection();

    $requiredFields = ['username', 'full_name', 'email', 'password', 'role'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            Response::error("Field '{$field}' is required", 400);
        }
    }

    $username = trim((string)$data['username']);
    $fullName = trim((string)$data['full_name']);
    $email = trim(strtolower((string)$data['email']));
    $password = (string)$data['password'];
    $role = (string)$data['role'];
    $isActive = isset($data['is_active']) ? (int)((bool)$data['is_active']) : 1;

    $errors = [];

    if (!Validator::minLength($username, 3)) {
        $errors[] = 'Username must be at least 3 characters';
    } elseif (!Validator::maxLength($username, 50)) {
        $errors[] = 'Username cannot exceed 50 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, and underscores';
    }

    if (!Validator::minLength($fullName, 2)) {
        $errors[] = 'Full name must be at least 2 characters';
    } elseif (!Validator::maxLength($fullName, 100)) {
        $errors[] = 'Full name cannot exceed 100 characters';
    }

    if (!Validator::email($email)) {
        $errors[] = 'Invalid email format';
    } elseif (!Validator::maxLength($email, 100)) {
        $errors[] = 'Email cannot exceed 100 characters';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long';
    }

    $allowedRoles = ['admin', 'inventory_manager', 'purchasing_officer', 'staff'];
    if (!in_array($role, $allowedRoles, true)) {
        $errors[] = 'Invalid role specified';
    }

    if (!in_array($isActive, [0, 1], true)) {
        $errors[] = 'Active status must be 0 or 1';
    }

    if (!empty($errors)) {
        Response::error('Validation failed: ' . implode(', ', $errors), 400);
    }

    if ($userModel->usernameExists($username)) {
        Response::error('Username already exists', 400);
    }

    if ($userModel->emailExists($email)) {
        Response::error('Email already exists', 400);
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    $columns = ['username', 'email', 'password_hash', 'role', 'full_name', 'is_active'];
    $placeholders = [':username', ':email', ':password_hash', ':role', ':full_name', ':is_active'];
    $params = [
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':role' => $role,
        ':full_name' => $fullName,
        ':is_active' => $isActive,
    ];

    $manualUserId = null;
    if (userTableRequiresExplicitId($db)) {
        $manualUserId = getNextUserId($db);
        $columns[] = 'id';
        $placeholders[] = ':id';
        $params[':id'] = $manualUserId;
    }

    if ($database->columnExists('users', 'phone')) {
        $columns[] = 'phone';
        $placeholders[] = ':phone';
        $params[':phone'] = null;
    }
    if ($database->columnExists('users', 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    if ($database->columnExists('users', 'updated_at')) {
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    $stmt = $db->prepare(
        'INSERT INTO users (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', $placeholders) . ')'
    );

    try {
        $stmt->execute($params);
    } catch (PDOException $e) {
        $dbCode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : null;
        $message = strtolower($e->getMessage());

        if ($manualUserId === null && $dbCode === 1364 && strpos($message, "field 'id' doesn't have a default value") !== false) {
            $manualUserId = getNextUserId($db);
            $columns[] = 'id';
            $placeholders[] = ':id';
            $params[':id'] = $manualUserId;

            $stmt = $db->prepare(
                'INSERT INTO users (' . implode(', ', $columns) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            );
            $stmt->execute($params);
        } else {
            throw $e;
        }
    }

    $userId = $manualUserId !== null ? $manualUserId : (int)$db->lastInsertId();
    if ($userId <= 0) {
        $lookup = $db->prepare("SELECT id FROM users WHERE username = :username AND email = :email LIMIT 1");
        $lookup->execute([
            ':username' => $username,
            ':email' => $email,
        ]);
        $userRow = $lookup->fetch(PDO::FETCH_ASSOC);
        $userId = isset($userRow['id']) ? (int)$userRow['id'] : 0;
    }
    if ($userId <= 0) {
        throw new RuntimeException('Unable to determine new user ID after insert.');
    }
    $createdUser = $userModel->findById($userId);

    $logger = new Logger($currentUser['id']);
    $logger->log('User created', 'user', $userId, [
        'username' => $username,
        'email' => $email,
        'role' => $role,
        'is_active' => $isActive,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    Response::success([
        'message' => 'User created successfully',
        'user' => [
            'id' => $createdUser['id'] ?? $userId,
            'username' => $createdUser['username'] ?? $username,
            'full_name' => $createdUser['full_name'] ?? $fullName,
            'email' => $createdUser['email'] ?? $email,
            'role' => $createdUser['role'] ?? $role,
            'is_active' => isset($createdUser['is_active']) ? (bool)$createdUser['is_active'] : (bool)$isActive,
            'last_login' => $createdUser['last_login'] ?? null,
            'created_at' => $createdUser['created_at'] ?? null,
        ],
    ], 'User created successfully', 201);
} catch (Throwable $e) {
    error_log('User create error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
    Response::error('Failed to create user: ' . $e->getMessage(), 500);
}
