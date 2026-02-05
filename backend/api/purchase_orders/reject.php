<?php

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once '../../config/database.php';
require_once '../../models/PurchaseOrder.php';
require_once '../../middleware/Auth.php';
require_once '../../utils/Response.php';

// Ensure only suppliers can reject POs
Auth::requireRole('supplier');
$supplier_id = Auth::user()->id;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Get PO ID from the URL
$po_id = isset($_GET['id']) ? intval($_GET['id']) : null;
if (!$po_id) {
    Response::error('Purchase order ID is required', 400);
}

try {
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
    if ($poDetails['status'] !== 'pending_approval') {
        Response::error('Purchase order is not pending approval');
    }

    // Get the rejection reason from the request body
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['reason']) || empty($data['reason'])) {
        Response::error('Rejection reason is required', 400);
    }

    // Update PO status and add rejection reason to notes
    $sql = "UPDATE purchase_orders 
            SET status = 'rejected',
                notes = CONCAT(IFNULL(notes, ''), '\nRejected by supplier. Reason: ', :reason)
            WHERE id = :id";

    if ($db->execute($sql, [':id' => $po_id, ':reason' => $data['reason']])) {
        Response::success(['message' => 'Purchase order rejected successfully']);
    } else {
        Response::error('Failed to reject purchase order');
    }
} catch (Exception $e) {
    Response::error('An error occurred: ' . $e->getMessage());
}

