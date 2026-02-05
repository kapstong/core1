<?php

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
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

    // Get all pending purchase orders for this supplier
    $orders = $po->getPendingBySupplierId($supplier_id);

    // Add items to each order
    foreach ($orders as &$order) {
        $order['items'] = $poItems->getByPurchaseOrderId($order['id']);
    }

    Response::success([
        'orders' => $orders
    ]);
} catch (Exception $e) {
    Response::error('Failed to load pending orders: ' . $e->getMessage());
}

