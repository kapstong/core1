<?php
/**
 * Supplier Approval Endpoint
 * POST /backend/api/suppliers/approve.php
 *
 * Simply activates a supplier user (sets is_active = 1 in users table)
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

// Only admin can approve suppliers
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
    $stmt = $conn->prepare("SELECT id, username, email, full_name, is_active FROM users WHERE id = :id AND role = 'supplier'");
    $stmt->execute([':id' => $userId]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        $conn->rollBack();
        Response::error('Supplier user not found', 404);
    }

    if ($supplier['is_active'] == 1) {
        $conn->rollBack();
        Response::error('Supplier is already active', 400);
    }

    // Activate the user (supplier status determined by is_active column)
    $stmt = $conn->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $userId]);

    // Update the supplier record timestamp
    $stmt = $conn->prepare("UPDATE suppliers SET updated_at = NOW() WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);

    // Log the approval
    $logger = new Logger($user['id']);
    $logger->log(
        'supplier_approved',
        'users',
        $userId,
        ['supplier_code' => 'SUP-' . str_pad($userId, 5, '0', STR_PAD_LEFT)]
    );

    // Send email notification (optional)
    try {
        require_once __DIR__ . '/../../utils/Email.php';
        $email = new Email();
        $email->sendSupplierApprovalNotification(
            $supplier['email'],
            $supplier['full_name'] ?: $supplier['username'],
            'SUP-' . str_pad($userId, 5, '0', STR_PAD_LEFT)
        );
    } catch (Exception $emailError) {
        error_log('Email notification failed: ' . $emailError->getMessage());
    }

    $conn->commit();

    Response::success([
        'message' => 'Supplier approved and activated successfully',
        'supplier_code' => 'SUP-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
        'user_id' => $userId
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to approve supplier: ' . $e->getMessage());
}
