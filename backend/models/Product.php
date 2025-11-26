<?php
/**
 * Product Model
 * Handles product-related database operations
 */

require_once __DIR__ . '/../config/database.php';

class Product {
    private $db;
    private $table = 'products';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get all products with optional filters
     */
    public function getAll($filters = []) {
        $query = "SELECT p.*, c.name as category_name,
                         i.quantity_on_hand, i.quantity_reserved, i.quantity_available, i.warehouse_location
                  FROM {$this->table} p
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN inventory i ON p.id = i.product_id
                  WHERE 1=1";

        $params = [];

        if (isset($filters['category_id'])) {
            $query .= " AND p.category_id = ?";
            $params[] = $filters['category_id'];
        }

        // Note: No default is_active filter - show all products
        if (isset($filters['is_active'])) {
            $query .= " AND p.is_active = ?";
            $params[] = $filters['is_active'];
        }

        if (isset($filters['brand'])) {
            $query .= " AND p.brand = ?";
            $params[] = $filters['brand'];
        }

        if (isset($filters['search'])) {
            $query .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $searchValue = '%' . $filters['search'] . '%';
            $params[] = $searchValue;
            $params[] = $searchValue;
            $params[] = $searchValue;
        }

        if (isset($filters['low_stock']) && $filters['low_stock']) {
            $query .= " AND i.quantity_available <= p.reorder_level";
        }

        // Order low stock products first (only if not specifically filtering by low stock), then by created date
        if (!isset($filters['low_stock']) || !$filters['low_stock']) {
            $query .= " ORDER BY CASE WHEN i.quantity_available <= p.reorder_level THEN 0 ELSE 1 END ASC, p.created_at DESC";
        } else {
            $query .= " ORDER BY p.created_at DESC";
        }

        $limit = null;
        $offset = null;

        if (isset($filters['limit'])) {
            $query .= " LIMIT ?";
            $limit = (int)$filters['limit'];
            $params[] = $limit;
        }

        if (isset($filters['offset'])) {
            $query .= " OFFSET ?";
            $offset = (int)$filters['offset'];
            $params[] = $offset;
        }

        $stmt = $this->db->prepare($query);

        foreach ($params as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get product by ID
     */
    public function findById($id) {
        $query = "SELECT p.*, c.name as category_name,
                         i.quantity_on_hand, i.quantity_reserved, i.quantity_available, i.warehouse_location
                  FROM {$this->table} p
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN inventory i ON p.id = i.product_id
                  WHERE p.id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get product by SKU
     */
    public function findBySKU($sku) {
        $query = "SELECT p.*, c.name as category_name,
                         i.quantity_on_hand, i.quantity_reserved, i.quantity_available
                  FROM {$this->table} p
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN inventory i ON p.id = i.product_id
                  WHERE p.sku = :sku";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sku', $sku);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Get product by name and category
     */
    public function findByName($name, $categoryId = null) {
        $query = "SELECT p.*, c.name as category_name,
                         i.quantity_on_hand, i.quantity_reserved, i.quantity_available
                  FROM {$this->table} p
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN inventory i ON p.id = i.product_id
                  WHERE p.name = :name AND p.is_active = 1";

        if ($categoryId) {
            $query .= " AND p.category_id = :category_id";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':name', $name);
        if ($categoryId) {
            $stmt->bindParam(':category_id', $categoryId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Create product
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();

            $query = "INSERT INTO {$this->table}
                      (sku, name, category_id, description, brand, specifications,
                       cost_price, selling_price, reorder_level, is_active, image_url, warranty_months)
                      VALUES
                      (:sku, :name, :category_id, :description, :brand, :specifications,
                       :cost_price, :selling_price, :reorder_level, :is_active, :image_url, :warranty_months)";

            $stmt = $this->db->prepare($query);

            $specs = isset($data['specifications']) ? json_encode($data['specifications']) : null;
            $isActive = $data['is_active'] ?? 1;
            $warrantyMonths = $data['warranty_months'] ?? 12;
            $description = $data['description'] ?? null;
            $brand = $data['brand'] ?? null;
            $reorderLevel = $data['reorder_level'] ?? 10;
            $imageUrl = $data['image_url'] ?? null;

            $stmt->bindParam(':sku', $data['sku'], PDO::PARAM_STR);
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindParam(':category_id', $data['category_id'], PDO::PARAM_INT);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':brand', $brand, PDO::PARAM_STR);
            $stmt->bindParam(':specifications', $specs, PDO::PARAM_STR);
            $stmt->bindParam(':cost_price', $data['cost_price'], PDO::PARAM_STR);
            $stmt->bindParam(':selling_price', $data['selling_price'], PDO::PARAM_STR);
            $stmt->bindParam(':reorder_level', $reorderLevel, PDO::PARAM_INT);
            $stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);
            $stmt->bindParam(':image_url', $imageUrl, PDO::PARAM_STR);
            $stmt->bindParam(':warranty_months', $warrantyMonths, PDO::PARAM_INT);

            $stmt->execute();
            $productId = $this->db->lastInsertId();

            // Create inventory record with initial stock if provided (quantity_available is auto-calculated)
            $initialStock = isset($data['stock_quantity']) ? (int)$data['stock_quantity'] : 0;
            $invQuery = "INSERT INTO inventory (product_id, quantity_on_hand, quantity_reserved)
                         VALUES (:product_id, :quantity, 0)";

            $invStmt = $this->db->prepare($invQuery);
            $invStmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $invStmt->bindParam(':quantity', $initialStock, PDO::PARAM_INT);
            $invStmt->execute();

            $this->db->commit();

            return $this->findById($productId);

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update product
     */
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['sku', 'name', 'category_id', 'description', 'brand',
                          'cost_price', 'selling_price', 'reorder_level', 'is_active',
                          'image_url', 'warranty_months'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (isset($data['specifications'])) {
            $fields[] = "specifications = :specifications";
            $params[':specifications'] = json_encode($data['specifications']);
        }

        if (empty($fields) && !isset($data['stock_quantity'])) {
            return false;
        }

        try {
            $this->db->beginTransaction();

            // Update product table if there are fields to update
            if (!empty($fields)) {
                $query = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
                $stmt = $this->db->prepare($query);
                $stmt->execute($params);
            }

            // Update inventory if stock_quantity is provided
            if (isset($data['stock_quantity'])) {
                $stockQty = (int)$data['stock_quantity'];

                // Check if inventory record exists
                $checkQuery = "SELECT id FROM inventory WHERE product_id = :product_id";
                $checkStmt = $this->db->prepare($checkQuery);
                $checkStmt->bindParam(':product_id', $id, PDO::PARAM_INT);
                $checkStmt->execute();

                if ($checkStmt->fetch()) {
                    // Update existing inventory (quantity_available is auto-calculated)
                    $invQuery = "UPDATE inventory
                                SET quantity_on_hand = :quantity
                                WHERE product_id = :product_id";
                } else {
                    // Create new inventory record (quantity_available is auto-calculated)
                    $invQuery = "INSERT INTO inventory (product_id, quantity_on_hand, quantity_reserved)
                                VALUES (:product_id, :quantity, 0)";
                }

                $invStmt = $this->db->prepare($invQuery);
                $invStmt->bindParam(':product_id', $id, PDO::PARAM_INT);
                $invStmt->bindParam(':quantity', $stockQty, PDO::PARAM_INT);
                $invStmt->execute();
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete product (hard delete)
     */
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Check if SKU exists (only for active products)
     */
    public function skuExists($sku, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE sku = :sku AND is_active = 1";

        if ($excludeId) {
            $query .= " AND id != :id";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':sku', $sku);

        if ($excludeId) {
            $stmt->bindParam(':id', $excludeId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $result = $stmt->fetch();

        return $result['count'] > 0;
    }

    /**
     * Get low stock products
     */
    public function getLowStock() {
        $query = "SELECT p.*, c.name as category_name,
                         i.quantity_on_hand, i.quantity_reserved, i.quantity_available
                  FROM {$this->table} p
                  LEFT JOIN categories c ON p.category_id = c.id
                  LEFT JOIN inventory i ON p.id = i.product_id
                  WHERE p.is_active = 1 AND i.quantity_available <= p.reorder_level
                  ORDER BY i.quantity_available ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
