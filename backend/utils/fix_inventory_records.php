<?php
/**
 * Fix Missing Inventory Records
 * Ensures all products have corresponding inventory records with correct quantity_available
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Inventory Records Fix Utility ===\n\n";

try {
    $db = Database::getInstance()->getConnection();

    // Start transaction
    $db->beginTransaction();

    // Step 1: Find products without inventory records
    echo "Step 1: Checking for products without inventory records...\n";

    $query = "
        SELECT p.id, p.sku, p.name
        FROM products p
        LEFT JOIN inventory i ON p.id = i.product_id
        WHERE i.product_id IS NULL
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $missingInventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($missingInventory) > 0) {
        echo "Found " . count($missingInventory) . " products without inventory records.\n";
        echo "Creating inventory records...\n";

        $insertQuery = "
            INSERT INTO inventory (product_id, quantity_on_hand, quantity_reserved, quantity_available)
            VALUES (:product_id, 0, 0, 0)
        ";
        $insertStmt = $db->prepare($insertQuery);

        foreach ($missingInventory as $product) {
            $insertStmt->execute([':product_id' => $product['id']]);
            echo "  - Created inventory for: {$product['sku']} - {$product['name']}\n";
        }
    } else {
        echo "All products have inventory records. ✓\n";
    }

    // Step 2: Fix quantity_available calculation
    echo "\nStep 2: Recalculating quantity_available for all products...\n";

    $updateQuery = "
        UPDATE inventory
        SET quantity_available = quantity_on_hand - quantity_reserved
        WHERE quantity_available != (quantity_on_hand - quantity_reserved)
    ";

    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute();
    $fixedCount = $updateStmt->rowCount();

    if ($fixedCount > 0) {
        echo "Fixed quantity_available for {$fixedCount} products.\n";
    } else {
        echo "All quantity_available values are correct. ✓\n";
    }

    // Step 3: Verify reserved quantities match pending orders
    echo "\nStep 3: Verifying reserved quantities...\n";

    $verifyQuery = "
        SELECT
            p.id,
            p.sku,
            p.name,
            i.quantity_reserved as current_reserved,
            COALESCE(order_reserved.total_pending, 0) as pending_total,
            COALESCE(order_reserved.total_pending, 0) as calculated_reserved
        FROM products p
        INNER JOIN inventory i ON p.id = i.product_id
        LEFT JOIN (
            SELECT
                coi.product_id,
                SUM(coi.quantity) as total_pending
            FROM customer_order_items coi
            INNER JOIN customer_orders co ON coi.order_id = co.id
            WHERE co.status = 'pending'
            GROUP BY coi.product_id
        ) order_reserved ON p.id = order_reserved.product_id
        WHERE i.quantity_reserved != COALESCE(order_reserved.total_pending, 0)
    ";

    $verifyStmt = $db->prepare($verifyQuery);
    $verifyStmt->execute();
    $mismatchedReserved = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($mismatchedReserved) > 0) {
        echo "Found " . count($mismatchedReserved) . " products with incorrect reserved quantities.\n";
        echo "Fixing reserved quantities...\n";

        $fixReservedQuery = "
            UPDATE inventory i
            LEFT JOIN (
                SELECT
                    coi.product_id,
                    SUM(coi.quantity) as total_pending
                FROM customer_order_items coi
                INNER JOIN customer_orders co ON coi.order_id = co.id
                WHERE co.status = 'pending'
                GROUP BY coi.product_id
            ) pending_orders ON i.product_id = pending_orders.product_id
            SET
                i.quantity_reserved = COALESCE(pending_orders.total_pending, 0),
                i.quantity_available = i.quantity_on_hand - COALESCE(pending_orders.total_pending, 0)
        ";

        $fixReservedStmt = $db->prepare($fixReservedQuery);
        $fixReservedStmt->execute();

        foreach ($mismatchedReserved as $product) {
            echo "  - Fixed: {$product['sku']} (was {$product['current_reserved']}, should be {$product['calculated_reserved']})\n";
        }
    } else {
        echo "All reserved quantities are correct. ✓\n";
    }

    // Step 4: Summary report
    echo "\n=== Summary Report ===\n";

    $summaryQuery = "
        SELECT
            COUNT(*) as total_products,
            SUM(CASE WHEN i.quantity_available > 0 THEN 1 ELSE 0 END) as in_stock_count,
            SUM(CASE WHEN i.quantity_available <= 0 THEN 1 ELSE 0 END) as out_of_stock_count,
            SUM(CASE WHEN i.quantity_available <= p.reorder_level THEN 1 ELSE 0 END) as low_stock_count,
            SUM(i.quantity_on_hand) as total_inventory,
            SUM(i.quantity_reserved) as total_reserved,
            SUM(i.quantity_available) as total_available
        FROM products p
        INNER JOIN inventory i ON p.id = i.product_id
        WHERE p.is_active = 1
    ";

    $summaryStmt = $db->prepare($summaryQuery);
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    echo "Total active products: {$summary['total_products']}\n";
    echo "In stock: {$summary['in_stock_count']}\n";
    echo "Out of stock: {$summary['out_of_stock_count']}\n";
    echo "Low stock: {$summary['low_stock_count']}\n";
    echo "\nInventory totals:\n";
    echo "  - On hand: {$summary['total_inventory']}\n";
    echo "  - Reserved: {$summary['total_reserved']}\n";
    echo "  - Available: {$summary['total_available']}\n";

    // Commit transaction
    $db->commit();

    echo "\n✓ All inventory records have been fixed successfully!\n";

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
