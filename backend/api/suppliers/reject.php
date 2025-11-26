<?php
/**
 * Supplier Rejection Endpoint
 * POST /backend/api/suppliers/reject.php
 *
 * Simply deactivates a supplier user (sets is_active = 0 in users table)
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

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

// Only admin can reject suppliers
$user = Auth::user();
if ($user['role'] !== 'admin') {
    Response::error('Access denied. Admin privileges required.', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    // Accept 'id' or 'user_id'
    $userId = isset($input['id']) ? intval($input['id']) : (isset($input['user_id']) ? intval($input['user_id']) : 0);
    $reason = $input['reason'] ?? 'No reason provided';

    if ($userId <= 0) {
        Response::error('Supplier ID is required', 400);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

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

    // Send email notification before deletion (optional)
    try {
        require_once __DIR__ . '/../../utils/Email.php';
        $email = new Email();
        $email->sendSupplierRejectionNotification(
            $supplier['email'],
            $supplier['full_name'] ?: $supplier['username'],
            $reason
        );
    } catch (Exception $emailError) {
        error_log('Email notification failed: ' . $emailError->getMessage());
    }

    // Log the rejection before deletion
    $logger = new Logger($user['id']);
    $logger->log(
        'supplier_rejected',
        'users',
        $userId,
        ['reason' => $reason, 'username' => $supplier['username']]
    );

    // Permanently delete the supplier
    $stmt = $conn->prepare("DELETE FROM users WHERE id = :id AND role = 'supplier'");
    $stmt->execute([':id' => $userId]);

    $conn->commit();

    Response::success([
        'message' => 'Supplier rejected and removed',
        'user_id' => $userId
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to reject supplier: ' . $e->getMessage());
}
