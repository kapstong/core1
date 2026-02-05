<?php
/**
 * Recently Deleted - Permanently delete soft-deleted entity
 * POST /backend/api/recently_deleted/purge.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';

CORS::handle();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

Auth::requireRole(['admin']);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || empty($input['type']) || empty($input['id'])) {
        Response::error('type and id are required', 400);
    }

    $type = $input['type'];
    $id = (int)$input['id'];

    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    switch ($type) {
        case 'category':
            $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::logDelete('category', $id, "Category permanently deleted");
            break;
        case 'product':
            $stmt = $db->prepare("DELETE FROM inventory WHERE product_id = :id");
            $stmt->execute([':id' => $id]);
            $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::logDelete('product', $id, "Product permanently deleted");
            break;
        case 'purchase_order':
            $stmt = $db->prepare("DELETE FROM purchase_order_items WHERE po_id = :id");
            $stmt->execute([':id' => $id]);
            $stmt = $db->prepare("DELETE FROM purchase_orders WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::logDelete('purchase_order', $id, "Purchase order permanently deleted");
            break;
        case 'grn':
            $stmt = $db->prepare("DELETE FROM grn_items WHERE grn_id = :id");
            $stmt->execute([':id' => $id]);
            $stmt = $db->prepare("DELETE FROM goods_received_notes WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::logDelete('grn', $id, "GRN permanently deleted");
            break;
        case 'user':
            $stmt = $db->prepare("DELETE FROM suppliers WHERE user_id = :id");
            $stmt->execute([':id' => $id]);
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::logDelete('user', $id, "User permanently deleted");
            break;
        default:
            $db->rollBack();
            Response::error('Invalid type', 400);
    }

    $db->commit();

    Response::success(['message' => 'Item permanently deleted']);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    Response::serverError('Failed to permanently delete item: ' . $e->getMessage());
}

