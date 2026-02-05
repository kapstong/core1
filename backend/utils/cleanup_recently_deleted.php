<?php
/**
 * Cleanup Recently Deleted
 * Permanently deletes records soft-deleted more than 30 days ago.
 *
 * Example cron: 0 2 * * * php /path/to/cleanup_recently_deleted.php
 */

require_once __DIR__ . '/../config/database.php';

$retentionDays = 30;

try {
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    $cutoff = "DATE_SUB(NOW(), INTERVAL {$retentionDays} DAY)";

    // Categories
    $db->exec("DELETE FROM categories WHERE deleted_at IS NOT NULL AND deleted_at < {$cutoff}");

    // Products (remove inventory rows first)
    $db->exec("DELETE i FROM inventory i INNER JOIN products p ON i.product_id = p.id WHERE p.deleted_at IS NOT NULL AND p.deleted_at < {$cutoff}");
    $db->exec("DELETE FROM products WHERE deleted_at IS NOT NULL AND deleted_at < {$cutoff}");

    // Users (remove supplier profile if exists)
    $db->exec("DELETE s FROM suppliers s INNER JOIN users u ON s.user_id = u.id WHERE u.deleted_at IS NOT NULL AND u.deleted_at < {$cutoff}");
    $db->exec("DELETE FROM users WHERE deleted_at IS NOT NULL AND deleted_at < {$cutoff}");

    // Purchase orders
    $db->exec("DELETE poi FROM purchase_order_items poi INNER JOIN purchase_orders po ON poi.po_id = po.id WHERE po.deleted_at IS NOT NULL AND po.deleted_at < {$cutoff}");
    $db->exec("DELETE FROM purchase_orders WHERE deleted_at IS NOT NULL AND deleted_at < {$cutoff}");

    // GRNs
    $db->exec("DELETE gi FROM grn_items gi INNER JOIN goods_received_notes g ON gi.grn_id = g.id WHERE g.deleted_at IS NOT NULL AND g.deleted_at < {$cutoff}");
    $db->exec("DELETE FROM goods_received_notes WHERE deleted_at IS NOT NULL AND deleted_at < {$cutoff}");

    $db->commit();

    echo "Cleanup completed.\n";
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo "Cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}
