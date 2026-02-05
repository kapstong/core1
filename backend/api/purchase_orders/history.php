<?php

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
require_once '../../config/database.php';
require_once '../../models/PurchaseOrder.php';
require_once '../../models/PurchaseOrderItems.php';
require_once '../../middleware/Auth.php';
require_once '../../utils/Response.php';

// Ensure supplier is authenticated
Auth::requireRole('supplier');
$user = Auth::user();
$supplier_id = $user['id'];

try {
    $db = Database::getInstance();
    $po = new PurchaseOrder($db);
    $poItems = new PurchaseOrderItems($db);

    $sql = "SELECT po.*, s.full_name as supplier_name
            FROM purchase_orders po
            LEFT JOIN users s ON po.supplier_id = s.id AND s.role = 'supplier'
            WHERE po.supplier_id = :supplier_id AND po.deleted_at IS NULL ";

    $params = [':supplier_id' => $supplier_id];

    // Add status filter if provided
    if (isset($_GET['status']) && $_GET['status'] !== 'all') {
        $sql .= "AND po.status = :status ";
        $params[':status'] = $_GET['status'];
    }

    $sql .= "ORDER BY po.created_at DESC";

    $orders = $db->fetchAll($sql, $params);

    // Add items to each order
    foreach ($orders as &$order) {
        $order['items'] = $poItems->getByPurchaseOrderId($order['id']);
        $order['total_items'] = $poItems->getTotalQuantityByPurchaseOrderId($order['id']);
    }

    Response::success([
        'orders' => $orders
    ]);
} catch (Exception $e) {
    Response::error('Failed to load order history: ' . $e->getMessage());
}
