<?php
/**
 * Supplier Notifications API Endpoint
 * GET /backend/api/suppliers/notifications.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

// Check authentication
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

try {
    $db = Database::getInstance()->getConnection();
    $user = Auth::user();

    // Get supplier ID if user is a supplier
    $supplierId = null;
    if ($user['role'] === 'supplier') {
        $stmt = $db->prepare("SELECT id FROM suppliers WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
        $stmt->execute();
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        $supplierId = $supplier ? $supplier['id'] : null;
    }

    $notifications = [];

    // Get pending purchase orders for this supplier
    if ($supplierId) {
        $stmt = $db->prepare("
            SELECT po.id, po.po_number, po.status, po.order_date, po.total_amount, po.created_at
            FROM purchase_orders po
            WHERE po.supplier_id = :supplier_id AND po.status IN ('pending_supplier', 'approved') AND po.deleted_at IS NULL
            ORDER BY po.created_at DESC
            LIMIT 10
        ");
        $stmt->bindParam(':supplier_id', $user['id'], PDO::PARAM_INT);
        $stmt->execute();
        $pendingPOs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pendingPOs as $po) {
            $notifications[] = [
                'id' => 'po_' . $po['id'],
                'type' => 'purchase_order',
                'title' => 'Purchase Order ' . $po['po_number'],
                'message' => 'Status: ' . ucfirst(str_replace('_', ' ', $po['status'])),
                'amount' => $po['total_amount'],
                'date' => $po['created_at'],
                'read' => false
            ];
        }
    }

    Response::success([
        'notifications' => $notifications,
        'unread_count' => count($notifications)
    ]);

} catch (Exception $e) {
    error_log("Supplier Notifications Error: " . $e->getMessage());
    Response::error('An error occurred while fetching notifications: ' . $e->getMessage(), 500);
}
