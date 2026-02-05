<?php
/**
 * Update Supplier
 * POST/PUT /backend/api/suppliers/update.php
 *
 * Simplified - updates ONLY users table
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
    Response::error('Method not allowed', 405);
}

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

$user = Auth::user();

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // Accept ID from either query parameter or request body
    $supplierId = isset($_GET['id']) ? intval($_GET['id']) : (isset($input['id']) ? intval($input['id']) : null);

    if (!$supplierId) {
        Response::error('Supplier ID is required', 400);
    }

    // Access control: Admin can update any supplier, supplier can only update themselves
    if ($user['role'] !== 'admin') {
        if ($user['role'] !== 'supplier' || $user['id'] !== $supplierId) {
            Response::error('Access denied. You can only update your own profile.', 403);
        }
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if supplier exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND role = 'supplier'");
    $stmt->execute([':id' => $supplierId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        Response::error('Supplier not found', 404);
    }

    // Start transaction
    $conn->beginTransaction();

    // Build update query for users table
    $updateFields = [];
    $updateData = [':id' => $supplierId];

    // Map of input field -> database column (only columns that exist in users table)
    $allowedFields = [
        'name' => 'full_name',
        'full_name' => 'full_name',
        'username' => 'username',
        'email' => 'email',
        'phone' => 'phone'
    ];

    // Admin-only fields
    if ($user['role'] === 'admin') {
        $allowedFields['is_active'] = 'is_active';
        $allowedFields['supplier_status'] = 'supplier_status';
    }

    foreach ($allowedFields as $inputField => $dbColumn) {
        if (isset($input[$inputField]) && $input[$inputField] !== null) {
            $updateFields[] = "{$dbColumn} = :{$dbColumn}";
            $updateData[":{$dbColumn}"] = $input[$inputField];
        }
    }

    // Update users table if there are fields to update
    if (!empty($updateFields)) {
        $updateQuery = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $conn->prepare($updateQuery);
        $stmt->execute($updateData);
    }

    // Update suppliers table with supplier-specific fields
    $supplierUpdateFields = [];
    $supplierUpdateData = [':user_id' => $supplierId];

    $supplierAllowedFields = [
        'company_name' => 'company_name',
        'contact_person' => 'contact_person',
        'supplier_email' => 'email',
        'address' => 'address',
        'city' => 'city',
        'state' => 'state',
        'postal_code' => 'postal_code',
        'country' => 'country',
        'tax_id' => 'tax_id',
        'payment_terms' => 'payment_terms',
        'notes' => 'notes'
    ];

    // Admin-only supplier fields
    if ($user['role'] === 'admin') {
        $supplierAllowedFields['supplier_code'] = 'supplier_code';
    }

    foreach ($supplierAllowedFields as $inputField => $dbColumn) {
        if (isset($input[$inputField])) {
            $supplierUpdateFields[] = "{$dbColumn} = :{$dbColumn}";
            $supplierUpdateData[":{$dbColumn}"] = $input[$inputField];
        }
    }

    // Update suppliers table if there are fields to update
    if (!empty($supplierUpdateFields)) {
        // Check if supplier record exists
        $stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $supplierId]);
        $supplierRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($supplierRecord) {
            // Update existing supplier record
            $supplierUpdateQuery = "UPDATE suppliers SET " . implode(', ', $supplierUpdateFields) . ", updated_at = NOW() WHERE user_id = :user_id";
            $stmt = $conn->prepare($supplierUpdateQuery);
            $stmt->execute($supplierUpdateData);
        } else {
            // Create new supplier record if it doesn't exist
            $insertFields = array_keys($supplierUpdateData);
            $insertFields[] = 'created_at';
            $insertFields[] = 'updated_at';
            $supplierUpdateData[':created_at'] = date('Y-m-d H:i:s');
            $supplierUpdateData[':updated_at'] = date('Y-m-d H:i:s');

            $insertQuery = "INSERT INTO suppliers (" . implode(', ', array_map(function($f) { return str_replace(':', '', $f); }, $insertFields)) .
                          ") VALUES (" . implode(', ', $insertFields) . ")";
            $stmt = $conn->prepare($insertQuery);
            $stmt->execute($supplierUpdateData);
        }
    }

    $conn->commit();

    // Fetch updated supplier
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $supplierId]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    $isActive = (bool)$updated['is_active'];
    $supplierStatus = $updated['supplier_status'] ?? ($isActive ? 'approved' : 'pending_approval');

    Response::success([
        'id' => $updated['id'],
        'code' => 'SUP-' . str_pad($updated['id'], 5, '0', STR_PAD_LEFT),
        'name' => $updated['full_name'] ?? $updated['username'],
        'username' => $updated['username'],
        'email' => $updated['email'],
        'phone' => $updated['phone'] ?? '',
        'address' => $updated['address'] ?? '',
        'is_active' => $isActive,
        'status' => $isActive ? 'active' : 'inactive',
        'supplier_status' => $supplierStatus
    ], 'Supplier updated successfully');

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to update supplier: ' . $e->getMessage());
}

