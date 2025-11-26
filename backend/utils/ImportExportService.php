<?php
/**
 * Bulk Import/Export Service
 * Handles CSV import/export for various entities
 */

class ImportExportService {
    private $db;
    private $allowedEntities = ['products', 'customers', 'suppliers', 'inventory'];

    public function __construct() {
        $this->db = new Database();
    }

    public function exportToCsv($entity, $filters = []) {
        if (!in_array($entity, $this->allowedEntities)) {
            throw new Exception("Invalid entity type for export");
        }

        $method = "export" . ucfirst($entity);
        return $this->$method($filters);
    }

    public function importFromCsv($entity, $file, $options = []) {
        if (!in_array($entity, $this->allowedEntities)) {
            throw new Exception("Invalid entity type for import");
        }

        if (!file_exists($file)) {
            throw new Exception("Import file not found");
        }

        $method = "import" . ucfirst($entity);
        return $this->$method($file, $options);
    }

    private function exportProducts($filters = []) {
        $query = "
            SELECT 
                p.*,
                c.name as category_name,
                i.quantity_on_hand,
                i.warehouse_location
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN inventory i ON p.id = i.product_id
            WHERE 1=1
        ";

        // Apply filters
        $params = [];
        if (!empty($filters['category'])) {
            $query .= " AND p.category_id = :category_id";
            $params[':category_id'] = $filters['category'];
        }

        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate CSV
        $filename = 'products_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = __DIR__ . '/../../exports/' . $filename;

        $fp = fopen($filepath, 'w');
        
        // Write headers
        fputcsv($fp, array_keys($products[0]));

        // Write data
        foreach ($products as $product) {
            fputcsv($fp, $product);
        }

        fclose($fp);

        return $filepath;
    }

    private function importProducts($file, $options = []) {
        $conn = $this->db->getConnection();
        $conn->beginTransaction();

        try {
            $fp = fopen($file, 'r');
            $headers = fgetcsv($fp);
            $requiredFields = ['sku', 'name', 'category_name', 'cost_price', 'selling_price'];
            
            // Validate headers
            foreach ($requiredFields as $field) {
                if (!in_array($field, $headers)) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $results = [
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'errors' => []
            ];

            while ($row = fgetcsv($fp)) {
                $results['total']++;
                $data = array_combine($headers, $row);

                try {
                    // Get or create category
                    $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
                    $stmt->execute([$data['category_name']]);
                    $category = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$category) {
                        $stmt = $conn->prepare("
                            INSERT INTO categories (name, slug) 
                            VALUES (?, ?)
                        ");
                        $stmt->execute([
                            $data['category_name'],
                            $this->createSlug($data['category_name'])
                        ]);
                        $categoryId = $conn->lastInsertId();
                    } else {
                        $categoryId = $category['id'];
                    }

                    // Check if product exists
                    $stmt = $conn->prepare("SELECT id FROM products WHERE sku = ?");
                    $stmt->execute([$data['sku']]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($product) {
                        // Update existing product
                        $stmt = $conn->prepare("
                            UPDATE products SET
                                name = ?,
                                category_id = ?,
                                description = ?,
                                brand = ?,
                                cost_price = ?,
                                selling_price = ?,
                                reorder_level = ?,
                                updated_at = CURRENT_TIMESTAMP
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $data['name'],
                            $categoryId,
                            $data['description'] ?? null,
                            $data['brand'] ?? null,
                            $data['cost_price'],
                            $data['selling_price'],
                            $data['reorder_level'] ?? 10,
                            $product['id']
                        ]);
                        $results['updated']++;
                    } else {
                        // Create new product
                        $stmt = $conn->prepare("
                            INSERT INTO products (
                                sku, name, category_id, description, brand,
                                cost_price, selling_price, reorder_level
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $data['sku'],
                            $data['name'],
                            $categoryId,
                            $data['description'] ?? null,
                            $data['brand'] ?? null,
                            $data['cost_price'],
                            $data['selling_price'],
                            $data['reorder_level'] ?? 10
                        ]);
                        $results['created']++;
                    }
                } catch (Exception $e) {
                    $results['errors'][] = [
                        'row' => $results['total'],
                        'sku' => $data['sku'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            fclose($fp);
            $conn->commit();
            return $results;

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    private function exportInventory($filters = []) {
        $query = "
            SELECT 
                p.sku,
                p.name as product_name,
                i.quantity_on_hand,
                i.quantity_reserved,
                i.warehouse_location,
                p.reorder_level
            FROM inventory i
            JOIN products p ON i.product_id = p.id
            WHERE 1=1
        ";

        $stmt = $this->db->getConnection()->prepare($query);
        $stmt->execute();
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate CSV
        $filename = 'inventory_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = __DIR__ . '/../../exports/' . $filename;

        $fp = fopen($filepath, 'w');
        fputcsv($fp, array_keys($inventory[0]));
        foreach ($inventory as $item) {
            fputcsv($fp, $item);
        }
        fclose($fp);

        return $filepath;
    }

    private function importInventory($file, $options = []) {
        $conn = $this->db->getConnection();
        $conn->beginTransaction();

        try {
            $fp = fopen($file, 'r');
            $headers = fgetcsv($fp);
            $requiredFields = ['sku', 'quantity_on_hand', 'warehouse_location'];
            
            foreach ($requiredFields as $field) {
                if (!in_array($field, $headers)) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $results = [
                'total' => 0,
                'updated' => 0,
                'errors' => []
            ];

            while ($row = fgetcsv($fp)) {
                $results['total']++;
                $data = array_combine($headers, $row);

                try {
                    // Get product ID from SKU
                    $stmt = $conn->prepare("SELECT id FROM products WHERE sku = ?");
                    $stmt->execute([$data['sku']]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$product) {
                        throw new Exception("Product not found with SKU: " . $data['sku']);
                    }

                    // Update inventory
                    $stmt = $conn->prepare("
                        INSERT INTO inventory (
                            product_id, quantity_on_hand, warehouse_location
                        ) VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            quantity_on_hand = VALUES(quantity_on_hand),
                            warehouse_location = VALUES(warehouse_location)
                    ");
                    $stmt->execute([
                        $product['id'],
                        $data['quantity_on_hand'],
                        $data['warehouse_location']
                    ]);

                    $results['updated']++;
                } catch (Exception $e) {
                    $results['errors'][] = [
                        'row' => $results['total'],
                        'sku' => $data['sku'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            fclose($fp);
            $conn->commit();
            return $results;

        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    }

    private function createSlug($string) {
        $string = strtolower(trim($string));
        $string = preg_replace('/[^a-z0-9-]/', '-', $string);
        $string = preg_replace('/-+/', '-', $string);
        return trim($string, '-');
    }
}