<?php

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once '../../config/database.php';
require_once '../../models/PurchaseOrder.php';
require_once '../../middleware/Auth.php';
require_once '../../utils/Response.php';
require_once '../../utils/Logger.php';
require_once '../../utils/AuditLogger.php';

// Ensure only suppliers can approve POs
Auth::requireRole('supplier');
$user = Auth::user();
$supplier_id = (int)($user['id'] ?? 0);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $po_id = isset($_GET['id'])
        ? (int)$_GET['id']
        : (isset($input['id']) ? (int)$input['id'] : (isset($input['po_id']) ? (int)$input['po_id'] : 0));

    if ($po_id <= 0) {
        Response::error('Purchase order ID is required', 400);
    }

    $reason = isset($input['reason']) ? trim((string)$input['reason']) : null;

    $db = Database::getInstance();
    $po = new PurchaseOrder($db);

    // Get PO details
    $poDetails = $po->getById($po_id);
    if (!$poDetails) {
        Response::error('Purchase order not found', 404);
    }

    // Verify supplier owns this PO
    if ($poDetails['supplier_id'] != $supplier_id) {
        Response::error('Access denied', 403);
    }

    // Verify PO is in pending status
    if ($poDetails['status'] !== 'pending_supplier') {
        Response::error('Purchase order is not pending supplier approval', 400);
    }

    $newStatus = 'approved';
    $notes = 'Approved by supplier' . ($reason ? ": {$reason}" : '');

    // Update the PO status
    if ($po->updateStatus($po_id, $newStatus, $supplier_id, $notes)) {
        // Log the approval/rejection to audit logs
        AuditLogger::logUpdate('purchase_order', $po_id, "Purchase order {$poDetails['po_number']} approved by supplier", [
            'old_status' => $poDetails['status'],
            'new_status' => $newStatus,
            'po_number' => $poDetails['po_number'],
            'supplier_id' => $supplier_id,
            'reason' => $reason
        ], [
            'status' => $newStatus,
            'notes' => $notes,
            'supplier_approved_at' => date('Y-m-d H:i:s')
        ]);

        Response::success([
            'status' => $newStatus,
            'message' => 'Purchase order has been approved successfully'
        ]);
    } else {
        Response::error('Failed to approve purchase order');
    }
} catch (Exception $e) {
    Response::error('An error occurred: ' . $e->getMessage());
}

