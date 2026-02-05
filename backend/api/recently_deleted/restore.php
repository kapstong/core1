<?php
/**
 * Recently Deleted - Restore soft-deleted entity
 * POST /backend/api/recently_deleted/restore.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
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

    switch ($type) {
        case 'category':
            $stmt = $db->prepare("UPDATE categories SET deleted_at = NULL, is_active = 1 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::logUpdate('category', $id, "Category restored from Recently Deleted");
            break;
        case 'product':
            $stmt = $db->prepare("UPDATE products SET deleted_at = NULL, is_active = 1 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::logUpdate('product', $id, "Product restored from Recently Deleted");
            break;
        case 'purchase_order':
            $stmt = $db->prepare("UPDATE purchase_orders SET deleted_at = NULL WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::logUpdate('purchase_order', $id, "Purchase order restored from Recently Deleted");
            break;
        case 'grn':
            $stmt = $db->prepare("UPDATE goods_received_notes SET deleted_at = NULL WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::logUpdate('grn', $id, "GRN restored from Recently Deleted");
            break;
        case 'user':
            $stmt = $db->prepare("UPDATE users SET deleted_at = NULL, is_active = 1 WHERE id = :id");
            $stmt->execute([':id' => $id]);
            AuditLogger::logUpdate('user', $id, "User restored from Recently Deleted");
            break;
        default:
            Response::error('Invalid type', 400);
    }

    Response::success(['message' => 'Item restored successfully']);

} catch (Exception $e) {
    Response::serverError('Failed to restore item: ' . $e->getMessage());
}
