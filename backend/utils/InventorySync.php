<?php
/**
 * Inventory Synchronization Utility
 * Ensures consistency between e-commerce and inventory management systems
 */

require_once __DIR__ . '/../config/database.php';

class InventorySync {

    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Synchronize inventory levels
     * Checks for discrepancies and fixes them
     */
    public function syncInventoryLevels() {
        $results = [
            'checked_products' => 0,
            'discrepancies_found' => 0,
            'discrepancies_fixed' => 0,
            'errors' => []
        ];

        try {
            // Get all products with inventory
            $query = "
                SELECT
                    p.id,
                    p.sku,
                    p.name,
                    i.quantity_on_hand,
                    i.quantity_reserved,
                    i.quantity_available,
                    COALESCE(cart_reserved.total_cart_reserved, 0) as calculated_cart_reserved,
                    COALESCE(order_reserved.total_order_reserved, 0) as calculated_order_reserved
                FROM products p
                INNER JOIN inventory i ON p.id = i.product_id
                LEFT JOIN (
                    SELECT product_id, SUM(quantity) as total_cart_reserved
                    FROM shopping_cart
                    GROUP BY product_id
                ) cart_reserved ON p.id = cart_reserved.product_id
                LEFT JOIN (
                    SELECT coi.product_id, SUM(coi.quantity) as total_order_reserved
                    FROM customer_order_items coi
                    INNER JOIN customer_orders co ON coi.order_id = co.id
                    WHERE co.status IN ('pending', 'confirmed', 'processing')
                    GROUP BY coi.product_id
                ) order_reserved ON p.id = order_reserved.product_id
                WHERE p.is_active = 1
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results['checked_products'] = count($products);

            foreach ($products as $product) {
                $productId = $product['id'];
                $sku = $product['sku'];
                $name = $product['name'];

                // Calculate what reserved should be
                $expectedReserved = $product['calculated_cart_reserved'] + $product['calculated_order_reserved'];
                $currentReserved = $product['quantity_reserved'];

                // Calculate what available should be
                $expectedAvailable = $product['quantity_on_hand'] - $expectedReserved;
                $currentAvailable = $product['quantity_available'];

                $discrepancyFound = false;

                // Check reserved quantity
                if ($currentReserved != $expectedReserved) {
                    $results['discrepancies_found']++;
                    $discrepancyFound = true;

                    // Fix reserved quantity
                    $updateQuery = "UPDATE inventory SET quantity_reserved = :reserved, quantity_available = quantity_on_hand - :reserved WHERE product_id = :product_id";
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->bindValue(':reserved', $expectedReserved, PDO::PARAM_INT);
                    $updateStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);

                    if ($updateStmt->execute()) {
                        $results['discrepancies_fixed']++;

                        // Log the correction
                        $this->logInventoryCorrection($productId, 'reserved_quantity', $currentReserved, $expectedReserved, 'Auto-sync: Reserved quantity corrected');
                    } else {
                        $results['errors'][] = "Failed to update reserved quantity for product $sku";
                    }
                }

                // Check available quantity (as additional verification)
                if ($currentAvailable != $expectedAvailable && !$discrepancyFound) {
                    $results['discrepancies_found']++;
                    $discrepancyFound = true;

                    // Fix available quantity
                    $updateQuery = "UPDATE inventory SET quantity_available = quantity_on_hand - quantity_reserved WHERE product_id = :product_id";
                    $updateStmt = $this->db->prepare($updateQuery);
                    $updateStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);

                    if ($updateStmt->execute()) {
                        $results['discrepancies_fixed']++;

                        // Log the correction
                        $this->logInventoryCorrection($productId, 'available_quantity', $currentAvailable, $expectedAvailable, 'Auto-sync: Available quantity corrected');
                    } else {
                        $results['errors'][] = "Failed to update available quantity for product $sku";
                    }
                }
            }

        } catch (Exception $e) {
            $results['errors'][] = 'Sync failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Clean up abandoned carts
     * Removes old cart items from inactive sessions
     */
    public function cleanupAbandonedCarts($daysOld = 30) {
        $results = [
            'carts_cleaned' => 0,
            'items_removed' => 0,
            'stock_released' => 0,
            'errors' => []
        ];

        try {
            // Find abandoned carts (older than specified days)
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

            $query = "
                SELECT sc.*, p.name as product_name
                FROM shopping_cart sc
                INNER JOIN products p ON sc.product_id = p.id
                WHERE sc.updated_at < :cutoff_date
                AND sc.session_id IS NOT NULL
                AND sc.customer_id IS NULL
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':cutoff_date', $cutoffDate);
            $stmt->execute();
            $abandonedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($abandonedItems)) {
                $this->db->beginTransaction();

                try {
                    // Group by session to count unique carts
                    $sessions = [];
                    foreach ($abandonedItems as $item) {
                        $sessions[$item['session_id']] = true;
                        $results['items_removed']++;
                        $results['stock_released'] += $item['quantity'];
                    }
                    $results['carts_cleaned'] = count($sessions);

                    // Delete abandoned cart items
                    $deleteQuery = "DELETE FROM shopping_cart WHERE updated_at < :cutoff_date AND session_id IS NOT NULL AND customer_id IS NULL";
                    $deleteStmt = $this->db->prepare($deleteQuery);
                    $deleteStmt->bindValue(':cutoff_date', $cutoffDate);
                    $deleteStmt->execute();

                    // Log the cleanup
                    $logQuery = "
                        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
                        VALUES (1, 'abandoned_cart_cleanup', 'system', NULL, :details, '127.0.0.1')
                    ";
                    $logStmt = $this->db->prepare($logQuery);
                    $logStmt->bindValue(':details', json_encode([
                        'carts_cleaned' => $results['carts_cleaned'],
                        'items_removed' => $results['items_removed'],
                        'stock_released' => $results['stock_released'],
                        'cutoff_date' => $cutoffDate
                    ]));
                    $logStmt->execute();

                    $this->db->commit();

                } catch (Exception $e) {
                    $this->db->rollBack();
                    $results['errors'][] = 'Failed to cleanup abandoned carts: ' . $e->getMessage();
                }
            }

        } catch (Exception $e) {
            $results['errors'][] = 'Cleanup failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Validate order inventory commitments
     * Ensures orders don't exceed available stock
     */
    public function validateOrderCommitments() {
        $results = [
            'orders_checked' => 0,
            'overcommitted_orders' => 0,
            'total_overcommitment' => 0,
            'recommendations' => [],
            'errors' => []
        ];

        try {
            // Find orders that may be overcommitted
            $query = "
                SELECT
                    co.id,
                    co.order_number,
                    co.status,
                    c.first_name,
                    c.last_name,
                    c.email,
                    order_totals.total_quantity as order_quantity,
                    GROUP_CONCAT(
                        printf('%s: %d/%d available',
                            p.name,
                            order_totals.product_quantity,
                            i.quantity_available
                        )
                    ) as product_details
                FROM customer_orders co
                INNER JOIN customers c ON co.customer_id = c.id
                INNER JOIN (
                    SELECT
                        coi.order_id,
                        SUM(coi.quantity) as total_quantity,
                        GROUP_CONCAT(printf('%d:%d', coi.product_id, coi.quantity)) as product_quantities
                    FROM customer_order_items coi
                    GROUP BY coi.order_id
                ) order_totals ON co.id = order_totals.order_id
                INNER JOIN customer_order_items coi ON co.id = coi.order_id
                INNER JOIN products p ON coi.product_id = p.id
                INNER JOIN inventory i ON coi.product_id = i.product_id
                WHERE co.status IN ('pending', 'confirmed', 'processing')
                AND coi.quantity > i.quantity_available
                GROUP BY co.id
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $overcommittedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results['orders_checked'] = count($overcommittedOrders);
            $results['overcommitted_orders'] = count($overcommittedOrders);

            foreach ($overcommittedOrders as $order) {
                $results['recommendations'][] = [
                    'order_id' => $order['id'],
                    'order_number' => $order['order_number'],
                    'customer' => $order['first_name'] . ' ' . $order['last_name'],
                    'status' => $order['status'],
                    'issue' => 'Order exceeds available inventory',
                    'details' => $order['product_details'],
                    'action' => 'Consider cancelling order or adjusting quantities'
                ];
            }

        } catch (Exception $e) {
            $results['errors'][] = 'Validation failed: ' . $e->getMessage();
        }

        return $results;
    }

    /**
     * Log inventory corrections
     */
    private function logInventoryCorrection($productId, $field, $oldValue, $newValue, $reason) {
        try {
            $logQuery = "
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
                VALUES (1, 'inventory_sync', 'product', :product_id, :details, '127.0.0.1')
            ";
            $logStmt = $this->db->prepare($logQuery);
            $logStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
            $logStmt->bindValue(':details', json_encode([
                'field' => $field,
                'old_value' => $oldValue,
                'new_value' => $newValue,
                'reason' => $reason,
                'timestamp' => date('Y-m-d H:i:s')
            ]));
            $logStmt->execute();
        } catch (Exception $e) {
            // Log failure but don't stop the sync process
            error_log('Failed to log inventory correction: ' . $e->getMessage());
        }
    }

    /**
     * Run complete synchronization suite
     */
    public function runFullSync() {
        $results = [
            'inventory_sync' => $this->syncInventoryLevels(),
            'cart_cleanup' => $this->cleanupAbandonedCarts(),
            'order_validation' => $this->validateOrderCommitments(),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $results;
    }
}
?>
