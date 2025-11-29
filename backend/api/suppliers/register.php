<?php
/**
 * Public Supplier Registration Endpoint
 * POST /backend/api/suppliers/register.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data)) {
    error_log('Supplier registration - Invalid JSON received: ' . substr($input, 0, 500));
    Response::error('Invalid request format. Please ensure all required fields are provided and try again.', 400);
}

// Validate required fields
$required_fields = ['name', 'email', 'username', 'password'];
$missing_fields = [];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
    }
}

if (!empty($missing_fields)) {
    error_log('Supplier registration - Missing fields: ' . implode(', ', $missing_fields) . ' | Received data: ' . json_encode($data));
    Response::error('Required fields are missing: ' . implode(', ', $missing_fields), 400);
}

// Supplier-specific fields (optional, stored in suppliers table)
$supplierData = [
    'company_name' => $data['company_name'] ?? $data['company'] ?? $data['name'], // Company name from registration
    'contact_person' => $data['contact_person'] ?? null,
    'phone' => $data['phone'] ?? null,
    'email' => $data['supplier_email'] ?? $data['email'], // Supplier contact email
    'address' => $data['address'] ?? null,
    'city' => $data['city'] ?? null,
    'state' => $data['state'] ?? null,
    'postal_code' => $data['postal_code'] ?? null,
    'country' => $data['country'] ?? 'Philippines',
    'tax_id' => $data['tax_id'] ?? null,
    'payment_terms' => $data['payment_terms'] ?? 'Net 30',
    'notes' => $data['notes'] ?? null
];

// Validate email format
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    Response::error('Please enter a valid email address.', 400);
}

// Validate username format
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['username'])) {
    Response::error('Username must be 3-50 characters long and contain only letters, numbers, and underscores.', 400);
}

// Validate password strength
if (strlen($data['password']) < 8) {
    Response::error('Password must be at least 8 characters long.', 400);
}

try {
    $db = Database::getInstance()->getConnection();

    // Check for existing username
    $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $data['username']]);
    if ($stmt->fetch()) {
        Response::error('This username is already taken. Please choose a different username.', 400);
    }

    // Check for existing email
    $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $data['email']]);
    if ($stmt->fetch()) {
        Response::error('An account with this email address already exists. Please use a different email or try logging in.', 400);
    }

    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);

    // Start transaction for atomic operation
    $db->beginTransaction();

    try {
        // Generate supplier code
        $supplierCode = 'SUP-' . str_pad(mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);

    // Insert into users with role = supplier, is_active = 0 (pending approval)
    $uq = "INSERT INTO users (username, email, password_hash, role, full_name, is_active, created_at) VALUES (:username, :email, :password_hash, 'supplier', :full_name, 0, NOW())";
        $stmt = $db->prepare($uq);
        $stmt->execute([
            ':username' => $data['username'],
            ':email' => $data['email'],
            ':password_hash' => $password_hash,
            ':full_name' => $data['name']
        ]);

        $userId = $db->lastInsertId();

        // Insert supplier information into suppliers table
        $supplierQuery = "
            INSERT INTO suppliers (
                user_id, company_name, supplier_code, contact_person, phone, email,
                address, city, state, postal_code, country, tax_id, payment_terms, notes,
                created_at, updated_at
            ) VALUES (
                :user_id, :company_name, :supplier_code, :contact_person, :phone, :email,
                :address, :city, :state, :postal_code, :country, :tax_id, :payment_terms, :notes,
                NOW(), NOW()
            )
        ";
        $stmt = $db->prepare($supplierQuery);
        $stmt->execute([
            ':user_id' => $userId,
            ':company_name' => $supplierData['company_name'],
            ':supplier_code' => $supplierCode,
            ':contact_person' => $supplierData['contact_person'],
            ':phone' => $supplierData['phone'],
            ':email' => $supplierData['email'],
            ':address' => $supplierData['address'],
            ':city' => $supplierData['city'],
            ':state' => $supplierData['state'],
            ':postal_code' => $supplierData['postal_code'],
            ':country' => $supplierData['country'],
            ':tax_id' => $supplierData['tax_id'],
            ':payment_terms' => $supplierData['payment_terms'],
            ':notes' => $supplierData['notes']
        ]);

        // Commit transaction
        $db->commit();

        Response::success([
            'message' => 'Your supplier account has been successfully created! Our team will review your application and activate your account within 24-48 hours. You will receive an email confirmation once approved.',
            'user_id' => $userId,
            'supplier_code' => $supplierCode
        ], 201);

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        error_log('Supplier registration transaction error: ' . $e->getMessage());
        Response::error('A system error occurred while processing your registration. Please try again later or contact support.', 500);
    }
} catch (PDOException $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Supplier registration database error: ' . $e->getMessage());
    Response::error('A system error occurred while processing your registration. Please try again later or contact support.', 500);
}
