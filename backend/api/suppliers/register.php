<?php
/**
 * Public Supplier Registration Endpoint
 * POST /backend/api/suppliers/register.php
 */

// Log start of request
error_log('=== Supplier Registration Request Started ===');

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

error_log('Files included successfully');

CORS::handle();

function toNullableString($value) {
    if ($value === null) {
        return null;
    }

    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function buildSupplierCode($userId) {
    return 'SUP-' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT);
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
error_log('CORS handled, REQUEST_METHOD: ' . ($requestMethod !== '' ? $requestMethod : 'NOT SET'));

if ($requestMethod !== 'POST') {
    error_log('Not a POST request, returning 405');
    Response::error('Method not allowed', 405);
}

$input = file_get_contents('php://input');
error_log('Raw input received: ' . substr($input, 0, 200));

$data = json_decode($input, true);
error_log('JSON decoded, data is: ' . var_export($data, true));

if (!is_array($data) || empty($data)) {
    error_log('Supplier registration - Invalid JSON received. Raw input: ' . var_export($input, true) . ' | Decoded: ' . var_export($data, true));
    Response::error('Invalid request format. Please ensure all required fields are provided and try again.', 400);
}

foreach ($data as $key => $value) {
    if (is_string($value)) {
        $data[$key] = trim($value);
    }
}

// Validate required fields
$required_fields = ['name', 'email', 'username', 'password'];
$missing_fields = [];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
        $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
    }
}

if (!empty($missing_fields)) {
    $errorMsg = 'Required fields are missing: ' . implode(', ', $missing_fields);
    error_log('Supplier registration - ' . $errorMsg . ' | Received data: ' . json_encode($data));
    Response::error($errorMsg, 400);
}

error_log('All required fields present');

$data['name'] = toNullableString($data['name']);
$data['email'] = toNullableString($data['email']);
$data['username'] = toNullableString($data['username']);

if (isset($data['supplier_email']) && $data['supplier_email'] !== null) {
    $data['supplier_email'] = toNullableString($data['supplier_email']);
}

// Supplier-specific fields (optional, stored in suppliers table)
$supplierData = [
    'company_name' => toNullableString($data['company_name'] ?? $data['company'] ?? $data['name']),
    'contact_person' => toNullableString($data['contact_person'] ?? null),
    'phone' => toNullableString($data['phone'] ?? null),
    'email' => toNullableString($data['supplier_email'] ?? $data['email']),
    'address' => toNullableString($data['address'] ?? null),
    'city' => toNullableString($data['city'] ?? null),
    'state' => toNullableString($data['state'] ?? null),
    'postal_code' => toNullableString($data['postal_code'] ?? null),
    'country' => toNullableString($data['country'] ?? 'Philippines') ?? 'Philippines',
    'tax_id' => toNullableString($data['tax_id'] ?? null),
    'payment_terms' => toNullableString($data['payment_terms'] ?? 'Net 30') ?? 'Net 30',
    'notes' => toNullableString($data['notes'] ?? null)
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

$db = null;
$conn = null;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check for existing username
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute([':username' => $data['username']]);
    if ($stmt->fetch()) {
        Response::error('This username is already taken. Please choose a different username.', 400);
    }

    // Check for existing email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $data['email']]);
    if ($stmt->fetch()) {
        Response::error('An account with this email address already exists. Please use a different email or try logging in.', 400);
    }

    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);

    // Start transaction for atomic operation
    $conn->beginTransaction();

    // Insert into users with role = supplier, is_active = 0 (pending approval)
    $userColumns = ['username', 'email', 'password_hash', 'role', 'full_name', 'is_active'];
    $userValues = [':username', ':email', ':password_hash', ':role', ':full_name', ':is_active'];
    $userParams = [
        ':username' => $data['username'],
        ':email' => $data['email'],
        ':password_hash' => $password_hash,
        ':role' => 'supplier',
        ':full_name' => $data['name'],
        ':is_active' => 0
    ];

    if ($db->columnExists('users', 'phone')) {
        $userColumns[] = 'phone';
        $userValues[] = ':phone';
        $userParams[':phone'] = $supplierData['phone'];
    }

    if ($db->columnExists('users', 'supplier_status')) {
        $userColumns[] = 'supplier_status';
        $userValues[] = ':supplier_status';
        $userParams[':supplier_status'] = 'pending_approval';
    }

    $userQuery = "INSERT INTO users (" . implode(', ', $userColumns) . ") VALUES (" . implode(', ', $userValues) . ")";
    $stmt = $conn->prepare($userQuery);
    $stmt->execute($userParams);

    $userId = (int)$conn->lastInsertId();
    $supplierCode = buildSupplierCode($userId);

    // Insert supplier information into suppliers table when compatible columns exist.
    $hasUserIdColumn = $db->columnExists('suppliers', 'user_id');
    $supplierNameColumn = $db->columnExists('suppliers', 'company_name') ? 'company_name' : ($db->columnExists('suppliers', 'name') ? 'name' : null);
    $supplierCodeColumn = $db->columnExists('suppliers', 'supplier_code') ? 'supplier_code' : ($db->columnExists('suppliers', 'code') ? 'code' : null);

    if ($hasUserIdColumn && $supplierNameColumn !== null && $supplierCodeColumn !== null) {
        $supplierColumns = ['user_id', $supplierNameColumn, $supplierCodeColumn];
        $supplierValues = [':user_id', ':supplier_name', ':supplier_code'];
        $supplierParams = [
            ':user_id' => $userId,
            ':supplier_name' => $supplierData['company_name'] ?? $data['name'],
            ':supplier_code' => $supplierCode
        ];

        $supplierFieldMap = [
            'contact_person' => 'contact_person',
            'phone' => 'phone',
            'email' => 'email',
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'postal_code' => 'postal_code',
            'country' => 'country',
            'tax_id' => 'tax_id',
            'payment_terms' => 'payment_terms',
            'notes' => 'notes'
        ];

        foreach ($supplierFieldMap as $column => $fieldKey) {
            if ($db->columnExists('suppliers', $column)) {
                $placeholder = ':' . $column;
                $supplierColumns[] = $column;
                $supplierValues[] = $placeholder;
                $supplierParams[$placeholder] = $supplierData[$fieldKey];
            }
        }

        if ($db->columnExists('suppliers', 'is_active')) {
            $supplierColumns[] = 'is_active';
            $supplierValues[] = ':supplier_is_active';
            $supplierParams[':supplier_is_active'] = 0;
        }

        if ($db->columnExists('suppliers', 'rating')) {
            $supplierColumns[] = 'rating';
            $supplierValues[] = ':supplier_rating';
            $supplierParams[':supplier_rating'] = 0;
        }

        $supplierQuery = "INSERT INTO suppliers (" . implode(', ', $supplierColumns) . ") VALUES (" . implode(', ', $supplierValues) . ")";
        $stmt = $conn->prepare($supplierQuery);
        $stmt->execute($supplierParams);
    } else {
        error_log('Supplier registration warning: suppliers insert skipped due to incompatible schema');
    }

    // Commit transaction
    $conn->commit();

    Response::success(
        [
            'user_id' => $userId,
            'supplier_code' => $supplierCode
        ],
        'Your supplier account has been successfully created! Our team will review your application and activate your account within 24-48 hours. You will receive an email confirmation once approved.',
        201
    );
} catch (Throwable $e) {
    if ($conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Supplier registration error: ' . $e->getMessage());
    error_log('Supplier registration trace: ' . $e->getTraceAsString());

    if ($e instanceof PDOException) {
        $errorCode = (string)$e->getCode();
        $errorMessage = strtolower($e->getMessage());

        if ($errorCode === '23000') {
            if (strpos($errorMessage, 'email') !== false) {
                Response::error('An account with this email address already exists. Please use a different email or try logging in.', 400, [
                    'email' => ['Email already exists']
                ]);
            }

            if (strpos($errorMessage, 'username') !== false) {
                Response::error('This username is already taken. Please choose a different username.', 400, [
                    'username' => ['Username already exists']
                ]);
            }

            if (strpos($errorMessage, 'supplier_code') !== false || strpos($errorMessage, '`code`') !== false) {
                Response::error('Could not allocate a unique supplier code. Please retry your registration.', 409);
            }

            if (strpos($errorMessage, 'user_id') !== false) {
                Response::error('A supplier account already exists for this user.', 409);
            }
        }

        if ($errorCode === '42S22') {
            Response::error('Registration setup issue detected. Please contact support and mention "supplier schema mismatch".', 500);
        }
    }

    Response::error('A system error occurred while processing your registration. Please try again later or contact support.', 500);
}

