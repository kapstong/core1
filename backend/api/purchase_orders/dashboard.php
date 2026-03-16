<?php

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once '../../config/database.php';
require_once '../../models/PurchaseOrder.php';
require_once '../../middleware/Auth.php';
require_once '../../utils/Response.php';

// Ensure supplier is authenticated
Auth::requireRole('supplier');
$user = Auth::user();
$supplier_id = $user['id'];

// Get statistics and recent orders for the supplier
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $po = new PurchaseOrder($db);

    // Get summary statistics - all POs for this supplier
    $total_pos = $po->countBySupplierId($supplier_id); // All POs

    $approved_count =
        $po->countBySupplierId($supplier_id, 'approved') +
        $po->countBySupplierId($supplier_id, 'ordered') +
        $po->countBySupplierId($supplier_id, 'partially_received');

    $pending_count = $po->countBySupplierId($supplier_id, 'pending_supplier');

    $completedStmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM purchase_orders
        WHERE supplier_id = :supplier_id
          AND status IN ('received', 'completed')
    ");
    $completedStmt->execute([':supplier_id' => $supplier_id]);
    $completed_count = (int)($completedStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $summary = [
        'total_pos' => $total_pos,
        // POs that have been approved (either by admin/purchasing officer or supplier)
        'approved_orders' => $approved_count,
        // POs waiting for supplier approval
        'pending_orders' => $pending_count,
        // POs that have been received/delivered (completed)
        'completed_orders' => $completed_count,
        'total_amount' => $po->getTotalAmountBySupplierId($supplier_id)
    ];

    // Get recent orders
    $recent_orders = $po->getRecentBySupplierId($supplier_id, 5);

    // Get monthly order counts
    $monthly_orders = $po->getMonthlyCountsBySupplierId($supplier_id);

    Response::success([
        'summary' => $summary,
        'recent_orders' => $recent_orders,
        'monthly_orders' => $monthly_orders
    ]);
} catch (Exception $e) {
    Response::error('Failed to load dashboard data: ' . $e->getMessage());
}

