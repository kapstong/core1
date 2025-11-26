<?php

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';

CORS::handle();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Verify authentication
Auth::requireAuth();

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $validator = new Validator();
    $validator->required(['name', 'phone'], $data);

    if (!$validator->isValid()) {
        Response::error('Validation failed: ' . implode(', ', $validator->getErrors()));
        exit;
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Insert new customer
    $sql = "INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $data['name'],
        $data['email'] ?? null,
        $data['phone'],
        $data['address'] ?? null
    ]);

    $customerId = $conn->lastInsertId();

    // Fetch the created customer
    $sql = "SELECT id, name, email, phone, address FROM customers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    Response::success('Customer created successfully', ['customer' => $customer]);
} catch (Exception $e) {
    Response::error('Failed to create customer: ' . $e->getMessage());
}
