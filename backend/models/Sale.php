<?php
/**
 * Sale Model
 */

require_once __DIR__ . '/../config/database.php';

class Sale {
    private $db;
    private $table = 'sales';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function create($data, $items) {
        try {
            $this->db->beginTransaction();

            // Insert sale
            $query = "INSERT INTO {$this->table}
                      (invoice_number, cashier_id, customer_name, customer_email, customer_phone,
                       subtotal, tax_amount, tax_rate, discount_amount, total_amount,
                       payment_method, payment_status, notes)
                      VALUES
                      (:invoice_number, :cashier_id, :customer_name, :customer_email, :customer_phone,
                       :subtotal, :tax_amount, :tax_rate, :discount_amount, :total_amount,
                       :payment_method, :payment_status, :notes)";

            $stmt = $this->db->prepare($query);

            $stmt->bindParam(':invoice_number', $data['invoice_number']);
            $stmt->bindParam(':cashier_id', $data['cashier_id']);
            $stmt->bindParam(':customer_name', $data['customer_name']);
            $stmt->bindParam(':customer_email', $data['customer_email']);
            $stmt->bindParam(':customer_phone', $data['customer_phone']);
            $stmt->bindParam(':subtotal', $data['subtotal']);
            $stmt->bindParam(':tax_amount', $data['tax_amount']);
            $stmt->bindParam(':tax_rate', $data['tax_rate']);
            $stmt->bindParam(':discount_amount', $data['discount_amount']);
            $stmt->bindParam(':total_amount', $data['total_amount']);
            $stmt->bindParam(':payment_method', $data['payment_method']);
            $stmt->bindParam(':payment_status', $data['payment_status']);
            $stmt->bindParam(':notes', $data['notes']);

            $stmt->execute();
            $saleId = $this->db->lastInsertId();

            // Insert sale items and update inventory
            $itemQuery = "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price)
                          VALUES (:sale_id, :product_id, :quantity, :unit_price)";

            $itemStmt = $this->db->prepare($itemQuery);

            $invQuery = "UPDATE inventory SET quantity_on_hand = quantity_on_hand - :quantity
                         WHERE product_id = :product_id";
            $invStmt = $this->db->prepare($invQuery);

            $movQuery = "INSERT INTO stock_movements
                         (product_id, movement_type, quantity, quantity_before, quantity_after,
                          reference_type, reference_id, performed_by)
                         VALUES
                         (:product_id, 'sale', :quantity, :quantity_before, :quantity_after,
                          'SALE', :sale_id, :performed_by)";
            $movStmt = $this->db->prepare($movQuery);

            foreach ($items as $item) {
                // Insert sale item
                $itemStmt->bindParam(':sale_id', $saleId);
                $itemStmt->bindParam(':product_id', $item['product_id']);
                $itemStmt->bindParam(':quantity', $item['quantity']);
                $itemStmt->bindParam(':unit_price', $item['unit_price']);
                $itemStmt->execute();

                // Get current inventory
                $invCheckQuery = "SELECT quantity_on_hand FROM inventory WHERE product_id = :product_id";
                $invCheckStmt = $this->db->prepare($invCheckQuery);
                $invCheckStmt->bindParam(':product_id', $item['product_id']);
                $invCheckStmt->execute();
                $inv = $invCheckStmt->fetch();
                $qtyBefore = $inv['quantity_on_hand'];
                $qtyAfter = $qtyBefore - $item['quantity'];

                // Update inventory
                $invStmt->bindParam(':quantity', $item['quantity']);
                $invStmt->bindParam(':product_id', $item['product_id']);
                $invStmt->execute();

                // Log stock movement
                $negQuantity = -$item['quantity'];
                $movStmt->bindParam(':product_id', $item['product_id']);
                $movStmt->bindParam(':quantity', $negQuantity);
                $movStmt->bindParam(':quantity_before', $qtyBefore);
                $movStmt->bindParam(':quantity_after', $qtyAfter);
                $movStmt->bindParam(':sale_id', $saleId);
                $movStmt->bindParam(':performed_by', $data['cashier_id']);
                $movStmt->execute();
            }

            $this->db->commit();
            return $this->findById($saleId);

        } catch (PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function findById($id) {
        $query = "SELECT s.*, u.full_name as cashier_name
                  FROM {$this->table} s
                  LEFT JOIN users u ON s.cashier_id = u.id
                  WHERE s.id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $sale = $stmt->fetch();

        if ($sale) {
            // Get sale items
            $itemsQuery = "SELECT si.*, p.name as product_name, p.sku
                           FROM sale_items si
                           LEFT JOIN products p ON si.product_id = p.id
                           WHERE si.sale_id = :sale_id";

            $itemsStmt = $this->db->prepare($itemsQuery);
            $itemsStmt->bindParam(':sale_id', $id, PDO::PARAM_INT);
            $itemsStmt->execute();

            $sale['items'] = $itemsStmt->fetchAll();
        }

        return $sale;
    }

    public function getAll($filters = []) {
        $query = "SELECT s.*, u.full_name as cashier_name
                  FROM {$this->table} s
                  LEFT JOIN users u ON s.cashier_id = u.id
                  WHERE 1=1";

        $params = [];

        if (isset($filters['date_from'])) {
            $query .= " AND DATE(s.sale_date) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $query .= " AND DATE(s.sale_date) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $query .= " ORDER BY s.sale_date DESC";

        if (isset($filters['limit'])) {
            $query .= " LIMIT :limit";
        }

        $stmt = $this->db->prepare($query);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if (isset($filters['limit'])) {
            $stmt->bindValue(':limit', (int)$filters['limit'], PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function generateInvoiceNumber() {
        $prefix = 'INV-' . date('Y') . '-';
        $query = "SELECT invoice_number FROM {$this->table}
                  WHERE invoice_number LIKE :prefix
                  ORDER BY id DESC LIMIT 1";

        $stmt = $this->db->prepare($query);
        $searchPrefix = $prefix . '%';
        $stmt->bindParam(':prefix', $searchPrefix);
        $stmt->execute();

        $result = $stmt->fetch();

        if ($result) {
            $lastNumber = intval(str_replace($prefix, '', $result['invoice_number']));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }
}
