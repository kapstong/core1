<?php
/**
 * Delete Purchase Order API Endpoint
 * DELETE /backend/api/purchase_orders/delete.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication first
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Only allow DELETE requests
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    Response::error('Method not allowed', 405);
}

// Role-based access - only admin can delete
if ($user['role'] !== 'admin') {
    Response::error('Access denied. Only administrators can delete purchase orders.', 403);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input === null || !isset($input['id'])) {
        Response::error('Purchase order ID is required', 400);
    }

    $poId = intval($input['id']);

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Check if PO exists
    $stmt = $conn->prepare("SELECT status FROM purchase_orders WHERE id = :id");
    $stmt->execute([':id' => $poId]);
    $po = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$po) {
        Response::error('Purchase order not found', 404);
    }

    // Business rule: can only delete draft POs
    if ($po['status'] !== 'draft') {
        Response::error('Can only delete purchase orders in draft status', 400);
    }

    // Start transaction
    $conn->beginTransaction();

    // Delete PO items first (cascade will handle this, but being explicit)
    $stmt = $conn->prepare("DELETE FROM purchase_order_items WHERE po_id = :po_id");
    $stmt->execute([':po_id' => $poId]);

    // Get PO details for logging before deletion
    $poQuery = "
        SELECT po.*, s.full_name as supplier_name, u.full_name as created_by_name
        FROM purchase_orders po
        LEFT JOIN users s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = :po_id
    ";
    $stmt = $conn->prepare($poQuery);
    $stmt->execute([':po_id' => $poId]);
    $poDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete PO items first (cascade will handle this, but being explicit)
    $stmt = $conn->prepare("DELETE FROM purchase_order_items WHERE po_id = :po_id");
    $stmt->execute([':po_id' => $poId]);

    // Delete the purchase order
    $stmt = $conn->prepare("DELETE FROM purchase_orders WHERE id = :id");
    $stmt->execute([':id' => $poId]);

    $conn->commit();

    // Log PO deletion to audit logs
    AuditLogger::logDelete('purchase_order', $poId, "Purchase order {$poDetails['po_number']} deleted", [
        'po_number' => $poDetails['po_number'],
        'supplier_id' => $poDetails['supplier_id'],
        'supplier_name' => $poDetails['supplier_name'],
        'status' => $poDetails['status'],
        'total_amount' => $poDetails['total_amount'],
        'created_by' => $poDetails['created_by_name'],
        'deleted_by' => $user['full_name'],
        'reason' => 'Deleted by administrator'
    ]);

    Response::success([], 'Purchase order deleted successfully');

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    Response::serverError('Failed to delete purchase order: ' . $e->getMessage());
}
