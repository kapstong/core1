<?php
/**
 * Stock Adjustment Model
 * Handles stock adjustment-related database operations
 */

require_once __DIR__ . '/../config/database.php';

class StockAdjustment {
    private $db;
    private $table = 'stock_adjustments';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all stock adjustments with optional filters
     */
    public function getAll($filters = []) {
        $query = "SELECT sa.*,
                         p.name as product_name, p.sku,
                         u.full_name as performed_by_name
                  FROM {$this->table} sa
                  LEFT JOIN products p ON sa.product_id = p.id
                  LEFT JOIN users u ON sa.performed_by = u.id
                  WHERE 1=1";

        $params = [];

        if (isset($filters['product_id'])) {
            $query .= " AND sa.product_id = ?";
            $params[] = $filters['product_id'];
        }

        if (isset($filters['adjustment_type'])) {
            $query .= " AND sa.adjustment_type = ?";
            $params[] = $filters['adjustment_type'];
        }

        if (isset($filters['reason'])) {
            $query .= " AND sa.reason = ?";
            $params[] = $filters['reason'];
        }

        if (isset($filters['performed_by'])) {
            $query .= " AND sa.performed_by = ?";
            $params[] = $filters['performed_by'];
        }

        if (isset($filters['date_from'])) {
            $query .= " AND sa.adjustment_date >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (isset($filters['date_to'])) {
            $query .= " AND sa.adjustment_date <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        if (isset($filters['search'])) {
            $query .= " AND (p.name LIKE ? OR p.sku LIKE ? OR sa.adjustment_number LIKE ? OR sa.notes LIKE ?)";
            $searchValue = '%' . $filters['search'] . '%';
            $params[] = $searchValue;
            $params[] = $searchValue;
            $params[] = $searchValue;
            $params[] = $searchValue;
        }

        $query .= " ORDER BY sa.adjustment_date DESC";

        if (isset($filters['limit'])) {
            $query .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }

        if (isset($filters['offset'])) {
            $query .= " OFFSET ?";
            $params[] = (int)$filters['offset'];
        }

        $stmt = $this->db->prepare($query);

        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get stock adjustment by ID
     */
    public function findById($id) {
        $query = "SELECT sa.*,
                         p.name as product_name, p.sku,
                         u.full_name as performed_by_name
                  FROM {$this->table} sa
                  LEFT JOIN products p ON sa.product_id = p.id
                  LEFT JOIN users u ON sa.performed_by = u.id
                  WHERE sa.id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Create stock adjustment
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();

            // Generate adjustment number
            $adjustmentNumber = $this->generateAdjustmentNumber();

            // Get current stock level
            $currentStock = $this->getCurrentStock($data['product_id']);

            // Calculate new stock level
            $quantityAdjusted = (int)$data['quantity_adjusted'];
            $newStock = $currentStock;

            switch ($data['adjustment_type']) {
                case 'add':
                    $newStock = $currentStock + $quantityAdjusted;
                    break;
                case 'remove':
                    $newStock = $currentStock - $quantityAdjusted;
                    break;
                case 'recount':
                    $newStock = $quantityAdjusted;
                    $quantityAdjusted = $newStock - $currentStock; // Store the difference
                    break;
            }

            // Insert adjustment record
            $query = "INSERT INTO {$this->table}
                      (adjustment_number, product_id, adjustment_type, quantity_before,
                       quantity_adjusted, quantity_after, reason, notes, performed_by)
                      VALUES
                      (:adjustment_number, :product_id, :adjustment_type, :quantity_before,
                       :quantity_adjusted, :quantity_after, :reason, :notes, :performed_by)";

            $stmt = $this->db->prepare($query);

            // Extract notes to a variable for bindParam
            $notes = $data['notes'] ?? null;

            $stmt->bindParam(':adjustment_number', $adjustmentNumber);
            $stmt->bindParam(':product_id', $data['product_id'], PDO::PARAM_INT);
            $stmt->bindParam(':adjustment_type', $data['adjustment_type']);
            $stmt->bindParam(':quantity_before', $currentStock, PDO::PARAM_INT);
            $stmt->bindParam(':quantity_adjusted', $quantityAdjusted, PDO::PARAM_INT);
            $stmt->bindParam(':quantity_after', $newStock, PDO::PARAM_INT);
            $stmt->bindParam(':reason', $data['reason']);
            $stmt->bindParam(':notes', $notes);
            $stmt->bindParam(':performed_by', $data['performed_by'], PDO::PARAM_INT);

            $stmt->execute();
            $adjustmentId = $this->db->lastInsertId();

            // Update inventory
            $this->updateInventoryStock($data['product_id'], $newStock);

            // Log stock movement
            $this->logStockMovement($data['product_id'], 'adjustment', $quantityAdjusted, $currentStock, $newStock, $adjustmentId);

            $this->db->commit();

            return $this->findById($adjustmentId);

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Generate unique adjustment number
     */
    private function generateAdjustmentNumber() {
        $date = date('Ymd');
        $count = 1;

        do {
            $number = sprintf('ADJ-%s-%03d', $date, $count);
            $exists = $this->adjustmentNumberExists($number);
            $count++;
        } while ($exists && $count < 1000);

        return $number;
    }

    /**
     * Check if adjustment number exists
     */
    private function adjustmentNumberExists($number) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE adjustment_number = :number";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':number', $number);
        $stmt->execute();
        $result = $stmt->fetch();

        return $result['count'] > 0;
    }

    /**
     * Get current stock level for a product
     */
    private function getCurrentStock($productId) {
        $query = "SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();

        // If no inventory record exists, create one
        if (!$result) {
            $insertQuery = "INSERT INTO inventory (product_id, quantity_on_hand, quantity_reserved)
                           VALUES (:product_id, 0, 0)";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $insertStmt->execute();
            return 0;
        }

        return (int)$result['quantity_on_hand'];
    }

    /**
     * Update inventory stock level
     */
    private function updateInventoryStock($productId, $newStock) {
        // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both insert and update cases
        $query = "INSERT INTO inventory (product_id, quantity_on_hand, quantity_reserved)
                  VALUES (:product_id, :quantity, 0)
                  ON DUPLICATE KEY UPDATE quantity_on_hand = VALUES(quantity_on_hand)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindParam(':quantity', $newStock, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Log stock movement
     */
    private function logStockMovement($productId, $movementType, $quantity, $quantityBefore, $quantityAfter, $referenceId) {
        try {
            $query = "INSERT INTO stock_movements
                      (product_id, movement_type, quantity, quantity_before, quantity_after,
                       reference_type, reference_id, performed_by)
                      VALUES
                      (:product_id, :movement_type, :quantity, :quantity_before, :quantity_after,
                       'ADJUSTMENT', :reference_id, :performed_by)";

            $stmt = $this->db->prepare($query);

            // Extract session user_id to a variable for bindParam
            $performedBy = $_SESSION['user_id'] ?? null;

            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->bindParam(':movement_type', $movementType);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':quantity_before', $quantityBefore, PDO::PARAM_INT);
            $stmt->bindParam(':quantity_after', $quantityAfter, PDO::PARAM_INT);
            $stmt->bindParam(':reference_id', $referenceId, PDO::PARAM_INT);
            $stmt->bindParam(':performed_by', $performedBy, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            // If stock_movements table doesn't exist, just skip logging
            // The adjustment will still be recorded in stock_adjustments table
            error_log('Stock movement logging failed (table may not exist): ' . $e->getMessage());
        }
    }

    /**
     * Get adjustment statistics
     */
    public function getStats($filters = []) {
        $query = "SELECT
                    COUNT(*) as total_adjustments,
                    SUM(CASE WHEN adjustment_type = 'add' THEN quantity_adjusted ELSE 0 END) as total_added,
                    SUM(CASE WHEN adjustment_type = 'remove' THEN ABS(quantity_adjusted) ELSE 0 END) as total_removed,
                    AVG(quantity_adjusted) as avg_adjustment
                  FROM {$this->table} sa
                  WHERE 1=1";

        $params = [];

        if (isset($filters['date_from'])) {
            $query .= " AND sa.adjustment_date >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (isset($filters['date_to'])) {
            $query .= " AND sa.adjustment_date <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $stmt = $this->db->prepare($query);

        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }

        $stmt->execute();
        return $stmt->fetch();
    }
}
