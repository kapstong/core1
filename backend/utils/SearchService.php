<?php
/**
 * Advanced Search Service
 * Provides comprehensive search functionality across multiple entities
 */

class SearchService {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function searchProducts($query, $filters = []) {
        $params = [];
        $sql = "
            SELECT 
                p.*,
                c.name as category_name,
                i.quantity_available,
                i.warehouse_location
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE 1=1
        ";

        // Full-text search on product fields
        if (!empty($query)) {
            $sql .= " AND (
                MATCH(p.name, p.description) AGAINST (:query IN BOOLEAN MODE)
                OR p.sku LIKE :sku
                OR p.brand LIKE :brand
            )";
            $params[':query'] = $query;
            $params[':sku'] = "%$query%";
            $params[':brand'] = "%$query%";
        }

        // Apply filters
        if (!empty($filters['category'])) {
            $sql .= " AND p.category_id = :category_id";
            $params[':category_id'] = $filters['category'];
        }

        if (!empty($filters['brand'])) {
            $sql .= " AND p.brand = :brand_exact";
            $params[':brand_exact'] = $filters['brand'];
        }

        if (isset($filters['price_min'])) {
            $sql .= " AND p.selling_price >= :price_min";
            $params[':price_min'] = $filters['price_min'];
        }

        if (isset($filters['price_max'])) {
            $sql .= " AND p.selling_price <= :price_max";
            $params[':price_max'] = $filters['price_max'];
        }

        if (isset($filters['in_stock'])) {
            $sql .= " AND i.quantity_available > 0";
        }

        // Add sorting
        $sql .= " ORDER BY " . $this->getSortClause($filters['sort'] ?? null);

        // Execute query
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchCustomers($query, $filters = []) {
        $params = [];
        $sql = "
            SELECT 
                c.*,
                COUNT(co.id) as total_orders,
                SUM(co.total_amount) as total_spent
            FROM customers c
            LEFT JOIN customer_orders co ON c.id = co.customer_id
            WHERE 1=1
        ";

        if (!empty($query)) {
            $sql .= " AND (
                c.first_name LIKE :name
                OR c.last_name LIKE :name
                OR c.email LIKE :email
                OR c.phone LIKE :phone
            )";
            $params[':name'] = "%$query%";
            $params[':email'] = "%$query%";
            $params[':phone'] = "%$query%";
        }

        // Apply filters
        if (!empty($filters['min_orders'])) {
            $sql .= " HAVING total_orders >= :min_orders";
            $params[':min_orders'] = $filters['min_orders'];
        }

        if (!empty($filters['min_spent'])) {
            $sql .= " HAVING total_spent >= :min_spent";
            $params[':min_spent'] = $filters['min_spent'];
        }

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchOrders($query, $filters = []) {
        $params = [];
        $sql = "
            SELECT 
                co.*,
                c.first_name,
                c.last_name,
                c.email
            FROM customer_orders co
            LEFT JOIN customers c ON co.customer_id = c.id
            WHERE 1=1
        ";

        if (!empty($query)) {
            $sql .= " AND (
                co.order_number LIKE :query
                OR c.first_name LIKE :name
                OR c.last_name LIKE :name
                OR c.email LIKE :email
            )";
            $params[':query'] = "%$query%";
            $params[':name'] = "%$query%";
            $params[':email'] = "%$query%";
        }

        // Apply filters
        if (!empty($filters['status'])) {
            $sql .= " AND co.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND co.order_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND co.order_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchInventory($query, $filters = []) {
        $params = [];
        $sql = "
            SELECT 
                i.*,
                p.name as product_name,
                p.sku,
                p.brand,
                c.name as category_name
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE 1=1
        ";

        if (!empty($query)) {
            $sql .= " AND (
                p.name LIKE :query
                OR p.sku LIKE :sku
                OR i.warehouse_location LIKE :location
            )";
            $params[':query'] = "%$query%";
            $params[':sku'] = "%$query%";
            $params[':location'] = "%$query%";
        }

        // Apply filters
        if (isset($filters['low_stock'])) {
            $sql .= " AND i.quantity_available <= p.reorder_level";
        }

        if (isset($filters['out_of_stock'])) {
            $sql .= " AND i.quantity_available = 0";
        }

        if (!empty($filters['location'])) {
            $sql .= " AND i.warehouse_location = :exact_location";
            $params[':exact_location'] = $filters['location'];
        }

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getSortClause($sort) {
        $validSorts = [
            'name_asc' => 'p.name ASC',
            'name_desc' => 'p.name DESC',
            'price_asc' => 'p.selling_price ASC',
            'price_desc' => 'p.selling_price DESC',
            'stock_asc' => 'i.quantity_available ASC',
            'stock_desc' => 'i.quantity_available DESC'
        ];

        return $validSorts[$sort] ?? 'p.name ASC';
    }
}