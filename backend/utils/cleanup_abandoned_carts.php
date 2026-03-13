<?php
/**
 * Cleanup Abandoned Shopping Carts
 * This script removes cart items that haven't been updated in over 2 hours
 *
 * Run this script periodically using a cron job or task scheduler:
 * Run every 30 minutes (example schedule: minute 0 and 30 each hour)
 */

require_once __DIR__ . '/../config/database.php';

// Configuration
$abandonedThresholdHours = 2; // Consider carts abandoned after this many hours of inactivity
$cutoffTimestamp = date('Y-m-d H:i:s', time() - ($abandonedThresholdHours * 3600));

try {
    $db = Database::getInstance()->getConnection();

    echo "=== Abandoned Cart Cleanup Started at " . date('Y-m-d H:i:s') . " ===\n";

    // Find all abandoned cart items (not updated in X hours)
    $query = "
        SELECT
            sc.product_id,
            SUM(sc.quantity) as total_cart_quantity,
            COUNT(sc.id) as cart_count
        FROM shopping_cart sc
        WHERE sc.updated_at < :cutoff
        GROUP BY sc.product_id
    ";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':cutoff', $cutoffTimestamp);
    $stmt->execute();

    $abandonedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($abandonedItems)) {
        echo "No abandoned carts found.\n";
        echo "=== Cleanup Complete ===\n";
        exit(0);
    }

    echo "Found " . count($abandonedItems) . " products with abandoned cart items.\n";

    $db->beginTransaction();

    try {
        foreach ($abandonedItems as $item) {
            echo "Removing {$item['total_cart_quantity']} units from abandoned carts for product ID {$item['product_id']} ({$item['cart_count']} abandoned carts)\n";
        }

        // Delete abandoned cart items
        $deleteQuery = "
            DELETE FROM shopping_cart
            WHERE updated_at < :cutoff
        ";

        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindValue(':cutoff', $cutoffTimestamp);
        $deleteStmt->execute();

        $deletedCarts = $deleteStmt->rowCount();

        $db->commit();

        echo "\n=== Cleanup Summary ===\n";
        echo "Cart items deleted: {$deletedCarts}\n";
        echo "=== Cleanup Complete at " . date('Y-m-d H:i:s') . " ===\n";

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "=== Cleanup Failed ===\n";
    exit(1);
}
