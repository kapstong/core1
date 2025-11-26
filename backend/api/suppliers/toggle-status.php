<?php
/**
 * Toggle Supplier Status Endpoint
 * POST /backend/api/suppliers/toggle-status.php
 *
 * Toggle supplier active/inactive status (changes users.is_active)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Check authentication
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Only admin can toggle supplier status
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

    $db = Database::getInstance()->getConnection();

    // Start transaction
    $conn->beginTransaction();

    // Verify user exists and is a supplier
    $stmt = $conn->prepare("SELECT id, username, email, full_name, is_active FROM users WHERE id = :id AND role = 'supplier'");
    $stmt->execute([':id' => $userId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        $conn->rollBack();
        Response::error('Supplier user not found', 404);
    }

    // Toggle the active status
    $newStatus = $supplier['is_active'] ? 0 : 1;
    $statusText = $newStatus ? 'activated' : 'deactivated';

    $stmt = $conn->prepare("UPDATE users SET is_active = :status, updated_at = NOW() WHERE id = :id");
    $stmt->execute([':status' => $newStatus, ':id' => $userId]);

    // Update supplier record timestamp
    $stmt = $conn->prepare("UPDATE suppliers SET updated_at = NOW() WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);

    // Log the action
    $logger = new Logger($user['id']);
    $logger->log(
        'supplier_' . $statusText,
        'users',
        $userId,
        ['status' => $newStatus, 'supplier_code' => 'SUP-' . str_pad($userId, 5, '0', STR_PAD_LEFT)]
    );

    $conn->commit();

    Response::success([
        'message' => 'Supplier ' . $statusText . ' successfully',
        'supplier_code' => 'SUP-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
        'user_id' => $userId,
        'new_status' => $newStatus ? 'active' : 'inactive'
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to toggle supplier status: ' . $e->getMessage());
}
