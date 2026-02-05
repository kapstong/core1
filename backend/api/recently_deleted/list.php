<?php
/**
 * Recently Deleted - List soft-deleted entities
 * GET /backend/api/recently_deleted/list.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../middleware/Auth.php';

CORS::handle();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

Auth::requireRole(['admin']);

try {
    $db = Database::getInstance()->getConnection();

    // Auto-clean items deleted more than 30 days ago
    $cutoff = "DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $db->exec("DELETE FROM categories WHERE deleted_at IS NOT NULL AND deleted_at < {$cutoff}");
    $db->exec("DELETE i FROM inventory i INNER JOIN products p ON i.product_id = p.id WHERE p.deleted_at IS NOT NULL AND p.deleted_at < {$cutoff}");
    $db->exec("DELETE FROM products WHERE deleted_at IS NOT NULL AND deleted_at < {$cutoff}");
    $db->exec("DELETE s FROM suppliers s INNER JOIN users u ON s.user_id = u.id WHERE u.deleted_at IS NOT NULL AND u.deleted_at < {$cutoff}");
    $db->exec("DELETE FROM users WHERE deleted_at IS NOT NULL AND deleted_at < {$cutoff}");
    $db->exec("DELETE poi FROM purchase_order_items poi INNER JOIN purchase_orders po ON poi.po_id = po.id WHERE po.deleted_at IS NOT NULL AND po.deleted_at < {$cutoff}");
    $db->exec("DELETE FROM purchase_orders WHERE deleted_at IS NOT NULL AND deleted_at < {$cutoff}");
    $db->exec("DELETE gi FROM grn_items gi INNER JOIN goods_received_notes g ON gi.grn_id = g.id WHERE g.deleted_at IS NOT NULL AND g.deleted_at < {$cutoff}");
    $db->exec("DELETE FROM goods_received_notes WHERE deleted_at IS NOT NULL AND deleted_at < {$cutoff}");

    $type = $_GET['type'] ?? 'all';
    $types = ['categories', 'products', 'purchase_orders', 'grns', 'users'];
    if ($type !== 'all' && !in_array($type, $types, true)) {
        Response::error('Invalid type', 400);
    }

    $results = [];

    if ($type === 'all' || $type === 'categories') {
        $stmt = $db->query("
            SELECT id, name, slug, deleted_at
            FROM categories
            WHERE deleted_at IS NOT NULL
            ORDER BY deleted_at DESC
        ");
        $results['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($type === 'all' || $type === 'products') {
        $stmt = $db->query("
            SELECT id, sku, name, deleted_at
            FROM products
            WHERE deleted_at IS NOT NULL
            ORDER BY deleted_at DESC
        ");
        $results['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($type === 'all' || $type === 'purchase_orders') {
        $stmt = $db->query("
            SELECT id, po_number, status, total_amount, deleted_at
            FROM purchase_orders
            WHERE deleted_at IS NOT NULL
            ORDER BY deleted_at DESC
        ");
        $results['purchase_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($type === 'all' || $type === 'grns') {
        $stmt = $db->query("
            SELECT id, grn_number, po_id, inspection_status, deleted_at
            FROM goods_received_notes
            WHERE deleted_at IS NOT NULL
            ORDER BY deleted_at DESC
        ");
        $results['grns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($type === 'all' || $type === 'users') {
        $stmt = $db->query("
            SELECT id, username, full_name, email, role, deleted_at
            FROM users
            WHERE deleted_at IS NOT NULL
            ORDER BY deleted_at DESC
        ");
        $results['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    Response::success([
        'results' => $results,
        'retention_days' => 30
    ]);

} catch (Exception $e) {
    Response::serverError('Failed to load recently deleted items: ' . $e->getMessage());
}
