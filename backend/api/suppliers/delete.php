<?php
/**
 * Delete Supplier Endpoint
 * DELETE /backend/api/suppliers/delete.php
 *
 * Permanently delete a supplier (user + supplier record)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    Response::error('Method not allowed', 405);
}

// Check authentication
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Only admin can delete suppliers
$user = Auth::user();
if ($user['role'] !== 'admin') {
    Response::error('Access denied. Admin privileges required.', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // Accept 'id' or 'user_id'
    $userId = isset($input['id']) ? intval($input['id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);

    if ($userId <= 0) {
        Response::error('Supplier ID is required', 400);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Verify user exists and is a supplier
    $stmt = $conn->prepare("SELECT id, username, email, full_name FROM users WHERE id = :id AND role = 'supplier'");
    $stmt->execute([':id' => $userId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        $conn->rollBack();
        Response::error('Supplier user not found', 404);
    }

    // Log the deletion before actually deleting
    $logger = new Logger($user['id']);
    $logger->log(
        'supplier_deleted',
        'users',
        $userId,
        ['supplier_code' => 'SUP-' . str_pad($userId, 5, '0', STR_PAD_LEFT), 'username' => $supplier['username']]
    );

    // Delete supplier record first (due to foreign key constraint)
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);

    // Then delete the user record
    $stmt = $conn->prepare("DELETE FROM users WHERE id = :id AND role = 'supplier'");
    $stmt->execute([':id' => $userId]);

    $conn->commit();

    Response::success([
        'message' => 'Supplier permanently deleted',
        'supplier_code' => 'SUP-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
        'user_id' => $userId
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to delete supplier: ' . $e->getMessage());
}
