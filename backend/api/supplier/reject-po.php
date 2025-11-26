<?php
/**
 * Supplier - Reject Purchase Order API Endpoint
 * POST /backend/api/supplier/reject-po.php
 * Body: { "po_id": 123, "reason": "Reason for rejection" }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Only suppliers can access this endpoint
if ($user['role'] !== 'supplier') {
    Response::error('Access denied. This endpoint is only for suppliers.', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null) {
        Response::error('Invalid JSON input');
    }

    // Validate required fields
    $errors = Validator::required($input, ['po_id', 'reason']);

    if ($errors) {
        Response::validationError($errors);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    $poId = intval($input['po_id']);

    // Start transaction
    $conn->beginTransaction();

    // Get the purchase order - verify it belongs to this supplier and is pending
    $stmt = $conn->prepare("
        SELECT id, po_number, status
        FROM purchase_orders
        WHERE id = :po_id AND supplier_id = :supplier_id
        LIMIT 1
    ");
    $stmt->execute([
        ':po_id' => $poId,
        ':supplier_id' => $user['id']
    ]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        $conn->rollBack();
        Response::error('Purchase order not found or access denied', 404);
    }

    // Check if PO is in the correct status
    if ($po['status'] !== 'pending_supplier') {
        $conn->rollBack();
        Response::error('Purchase order cannot be rejected. Current status: ' . $po['status'], 400);
    }

    // Update purchase order status to rejected
    // Store rejection reason in notes and track who rejected it
    $updateQuery = "
        UPDATE purchase_orders
        SET
            status = 'rejected',
            approved_by = :rejected_by,
            notes = CONCAT(COALESCE(notes, ''), '\n\n[REJECTED by Supplier] ', :reason),
            updated_at = NOW()
        WHERE id = :po_id
    ";

    $stmt = $conn->prepare($updateQuery);
    $stmt->execute([
        ':po_id' => $poId,
        ':rejected_by' => $user['id'],
        ':reason' => $input['reason']
    ]);

    $conn->commit();

    // Fetch updated PO
    $stmt = $conn->prepare("
        SELECT
            po.*,
            u.full_name as created_by_name,
            ua.full_name as approved_by_name
        FROM purchase_orders po
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN users ua ON po.approved_by = ua.id
        WHERE po.id = :po_id
    ");
    $stmt->execute([':po_id' => $poId]);
    $updatedPO = $stmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'purchase_order' => $updatedPO
    ], 'Purchase order rejected');

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to reject purchase order: ' . $e->getMessage());
}
