<?php
/**
 * Supplier - Approve Purchase Order API Endpoint
 * POST /backend/api/supplier/approve-po.php
 * Body: { "po_id": 123, "notes": "Optional supplier notes" }
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

if (($user['role'] ?? null) !== 'supplier') {
    Response::error('Access denied. This endpoint is only for suppliers.', 403);
}

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    if ($input === null && trim((string)$rawInput) !== '') {
        Response::error('Invalid JSON input');
    }

    if (!is_array($input)) {
        $input = [];
    }

    $errors = Validator::required($input, ['po_id']);
    if ($errors) {
        Response::validationError($errors);
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();
    $hasDeletedAt = $db->columnExists('purchase_orders', 'deleted_at');
    $hasSupplierApprovedAt = $db->columnExists('purchase_orders', 'supplier_approved_at');

    $poId = (int)$input['po_id'];
    $supplierNotes = isset($input['notes']) ? trim((string)$input['notes']) : '';
    $approvalNote = 'Approved by supplier' . ($supplierNotes !== '' ? ": {$supplierNotes}" : '');

    $conn->beginTransaction();

    $poStmt = $conn->prepare("
        SELECT id, po_number, status, supplier_id, notes
        FROM purchase_orders
        WHERE id = :po_id
          AND supplier_id = :supplier_id" . ($hasDeletedAt ? "
          AND deleted_at IS NULL" : "") . "
        LIMIT 1
    ");
    $poStmt->execute([
        ':po_id' => $poId,
        ':supplier_id' => $user['id']
    ]);
    $po = $poStmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        $conn->rollBack();
        Response::error('Purchase order not found or access denied', 404);
    }

    if (($po['status'] ?? null) !== 'pending_supplier') {
        $conn->rollBack();
        Response::error('Purchase order cannot be approved. Current status: ' . ($po['status'] ?? 'unknown'), 400);
    }

    $updateSql = "
        UPDATE purchase_orders
        SET
            status = 'approved',
            approved_by = :approved_by,
            notes = CASE
                WHEN notes IS NULL OR TRIM(notes) = '' THEN :approval_note_new
                ELSE CONCAT(notes, '\n', :approval_note_append)
            END,
            updated_at = NOW()" . ($hasSupplierApprovedAt ? ",
            supplier_approved_at = NOW()" : "") . "
        WHERE id = :po_id
    ";

    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->execute([
        ':po_id' => $poId,
        ':approved_by' => $user['id'],
        ':approval_note_new' => $approvalNote,
        ':approval_note_append' => $approvalNote
    ]);

    $conn->commit();

    $updatedStmt = $conn->prepare("
        SELECT
            po.*,
            s.full_name AS supplier_name,
            u.full_name AS created_by_name,
            ua.full_name AS approved_by_name
        FROM purchase_orders po
        LEFT JOIN users s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        LEFT JOIN users ua ON po.approved_by = ua.id
        WHERE po.id = :po_id" . ($hasDeletedAt ? "
          AND po.deleted_at IS NULL" : "") . "
        LIMIT 1
    ");
    $updatedStmt->execute([':po_id' => $poId]);
    $updatedPO = $updatedStmt->fetch(PDO::FETCH_ASSOC);

    AuditLogger::logUpdate('purchase_order', $poId, "Purchase order {$po['po_number']} approved by supplier", [
        'old_status' => $po['status'],
        'po_number' => $po['po_number'],
        'supplier_id' => (int)$user['id']
    ], [
        'new_status' => 'approved',
        'approval_note' => $approvalNote,
        'supplier_approved_at' => date('Y-m-d H:i:s'),
        'auto_inventory_update' => false
    ]);

    Response::success([
        'purchase_order' => $updatedPO,
        'auto_inventory_update' => false
    ], 'Purchase order approved successfully');

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to approve purchase order: ' . $e->getMessage());
}
