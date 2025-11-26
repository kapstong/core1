<?php
/**
 * Create Supplier API Endpoint
 * POST /backend/api/suppliers/create.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication first
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Role-based access
if (!in_array($user['role'], ['admin', 'purchasing_officer'])) {
    Response::error('Access denied', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        Response::error('Invalid JSON input');
    }

    // Validate required fields
    $errors = Validator::required($input, ['name', 'code']);

    if ($errors) {
        Response::validationError($errors);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Generate username from name if not provided
    $username = isset($input['username']) ? trim($input['username']) : strtolower(str_replace(' ', '_', trim($input['name'])));

    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetch()) {
        Response::error('Username already exists', 400);
    }

    // Check if email already exists
    if (isset($input['email']) && !empty($input['email'])) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => trim($input['email'])]);
        if ($stmt->fetch()) {
            Response::error('Email already exists', 400);
        }
    }

    // Create supplier user account
    $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;
    $supplierStatus = $isActive ? 'approved' : 'pending_approval';

    $query = "
        INSERT INTO users (
            username, email, phone, password_hash, role, full_name, is_active, supplier_status
        ) VALUES (
            :username, :email, :phone, :password_hash, 'supplier', :full_name, :is_active, :supplier_status
        )
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':username' => $username,
        ':email' => isset($input['email']) ? trim($input['email']) : null,
        ':phone' => isset($input['phone']) ? trim($input['phone']) : null,
        ':password_hash' => password_hash('password', PASSWORD_DEFAULT), // Default password
        ':full_name' => trim($input['name']),
        ':is_active' => $isActive,
        ':supplier_status' => $supplierStatus
    ]);

    $supplierId = $conn->lastInsertId();

    // Fetch created supplier user
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $supplierId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format response to match frontend expectations
    $formattedSupplier = [
        'id' => $supplier['id'],
        'user_id' => $supplier['id'],
        'code' => 'SUP-' . str_pad($supplier['id'], 5, '0', STR_PAD_LEFT),
        'name' => $supplier['full_name'],
        'username' => $supplier['username'],
        'email' => $supplier['email'],
        'phone' => $supplier['phone'] ?? 'N/A',
        'is_active' => (bool)$supplier['is_active'],
        'status' => (bool)$supplier['is_active'] ? 'active' : 'inactive',
        'supplier_status' => $supplier['supplier_status'] ?? 'pending_approval',
        'created_at' => $supplier['created_at'],
        'updated_at' => $supplier['updated_at']
    ];

    Response::success([
        'supplier' => $supplier
    ], 'Supplier created successfully');

} catch (Exception $e) {
    Response::serverError('Failed to create supplier: ' . $e->getMessage());
}
