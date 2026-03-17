<?php
/**
 * Complete GRN API Endpoint
 * POST /backend/api/grn/complete.php?id={id}
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

$user = Auth::user();

if (!in_array($user['role'], ['admin', 'inventory_manager', 'purchasing_officer'], true)) {
    Response::error('Access denied', 403);
}

try {
    $grnId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($grnId <= 0) {
        Response::error('GRN ID is required', 400);
    }

    $dbInstance = Database::getInstance();
    $db = $dbInstance->getConnection();
    $hasDeletedAt = $dbInstance->columnExists('goods_received_notes', 'deleted_at');

    $grnCheckStmt = $db->prepare("
        SELECT grn.*, po.po_number, s.full_name AS supplier_name
        FROM goods_received_notes grn
        LEFT JOIN purchase_orders po ON grn.po_id = po.id
        LEFT JOIN users s ON po.supplier_id = s.id AND s.role = 'supplier'
        WHERE grn.id = :id" . ($hasDeletedAt ? "
          AND grn.deleted_at IS NULL" : "") . "
    ");
    $grnCheckStmt->execute([':id' => $grnId]);
    $grn = $grnCheckStmt->fetch(PDO::FETCH_ASSOC);

    if (!$grn) {
        Response::error('GRN not found', 404);
    }

    if (($grn['inspection_status'] ?? null) === 'completed') {
        Response::error('GRN is already completed', 400);
    }

    $itemsStmt = $db->prepare("
        SELECT grni.*, p.name AS product_name, p.sku
        FROM grn_items grni
        LEFT JOIN products p ON grni.product_id = p.id
        WHERE grni.grn_id = :grn_id
        ORDER BY grni.id
    ");
    $itemsStmt->execute([':grn_id' => $grnId]);
    $grnItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($grnItems)) {
        Response::error('GRN has no items', 400);
    }

    $db->beginTransaction();

    try {
        $processedItems = count($grnItems);
        $totalReceived = 0;
        $totalAccepted = 0;
        $totalRejected = 0;

        foreach ($grnItems as $item) {
            $totalReceived += (int)($item['quantity_received'] ?? 0);
            $totalAccepted += (int)($item['quantity_accepted'] ?? 0);
            $totalRejected += (int)($item['quantity_rejected'] ?? 0);
        }

        // Inventory and PO quantities are already applied during GRN creation.
        // Completion only finalizes the inspection state.
        $poTotalsStmt = $db->prepare("
            SELECT
                COALESCE(SUM(quantity_ordered), 0) AS total_ordered,
                COALESCE(SUM(quantity_received), 0) AS total_received
            FROM purchase_order_items
            WHERE po_id = :po_id
        ");
        $poTotalsStmt->execute([':po_id' => $grn['po_id']]);
        $poTotals = $poTotalsStmt->fetch(PDO::FETCH_ASSOC);

        $newPOStatus = 'approved';
        if ((int)($poTotals['total_received'] ?? 0) >= (int)($poTotals['total_ordered'] ?? 0) && (int)($poTotals['total_ordered'] ?? 0) > 0) {
            $newPOStatus = 'received';
        } elseif ((int)($poTotals['total_received'] ?? 0) > 0) {
            $newPOStatus = 'partially_received';
        }

        $poUpdateStmt = $db->prepare("
            UPDATE purchase_orders
            SET status = :status,
                updated_at = NOW()
            WHERE id = :po_id
        ");
        $poUpdateStmt->execute([
            ':status' => $newPOStatus,
            ':po_id' => $grn['po_id']
        ]);

        $grnUpdateStmt = $db->prepare("
            UPDATE goods_received_notes
            SET inspection_status = 'completed',
                updated_at = NOW()
            WHERE id = :id
        ");
        $grnUpdateStmt->execute([':id' => $grnId]);

        $db->commit();

        AuditLogger::logUpdate('grn', $grnId, "GRN {$grn['grn_number']} completed - Receipt finalized", [
            'old_status' => $grn['inspection_status'],
            'new_status' => 'completed',
            'grn_number' => $grn['grn_number'],
            'po_number' => $grn['po_number'],
            'supplier_name' => $grn['supplier_name'],
            'completed_by' => $user['full_name']
        ], [
            'inspection_status' => 'completed',
            'items_processed' => $processedItems,
            'total_accepted' => $totalAccepted,
            'total_rejected' => $totalRejected,
            'inventory_updated' => false,
            'po_status_updated' => $newPOStatus
        ]);

        Response::success([
            'id' => $grnId,
            'status' => 'completed',
            'grn_number' => $grn['grn_number'],
            'po_number' => $grn['po_number'],
            'supplier_name' => $grn['supplier_name'],
            'items_processed' => $processedItems,
            'total_received' => $totalReceived,
            'total_accepted' => $totalAccepted,
            'total_rejected' => $totalRejected,
            'inventory_updated' => false,
            'po_status_updated' => $newPOStatus,
            'completed_by' => $user['full_name'],
            'completed_at' => date('Y-m-d H:i:s')
        ], 'GRN completed successfully');

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log('GRN Complete Error: ' . $e->getMessage());
    Response::error('An error occurred while completing GRN: ' . $e->getMessage(), 500);
}
