<?php
class PurchaseOrder {
    private $db;
    private $table = 'purchase_orders';

    public function __construct($db) {
        $this->db = $db;
    }

    public function countBySupplierId($supplier_id, $status = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE supplier_id = :supplier_id";
        $params = [':supplier_id' => $supplier_id];

        if ($status) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }

        $result = $this->db->fetchAll($sql, $params);
        return $result[0]['count'] ?? 0;
    }

    public function getTotalAmountBySupplierId($supplier_id) {
        $sql = "SELECT SUM(total_amount) as total FROM {$this->table} WHERE supplier_id = :supplier_id";
        $params = [':supplier_id' => $supplier_id];

        $result = $this->db->fetchAll($sql, $params);
        return $result[0]['total'] ?? 0;
    }

    public function getRecentBySupplierId($supplier_id, $limit = 5) {
        $sql = "SELECT po.*, u.full_name as supplier_name
                FROM {$this->table} po
                LEFT JOIN users u ON po.supplier_id = u.id AND u.role = 'supplier'
                WHERE po.supplier_id = :supplier_id
                ORDER BY po.created_at DESC LIMIT :limit";

        return $this->db->fetchAll($sql, [
            ':supplier_id' => $supplier_id,
            ':limit' => $limit
        ]);
    }

    public function getMonthlyCountsBySupplierId($supplier_id, $months = 6) {
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM {$this->table}
                WHERE supplier_id = :supplier_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC";
        
        return $this->db->fetchAll($sql, [
            ':supplier_id' => $supplier_id,
            ':months' => $months
        ]);
    }

    public function getById($id) {
        $sql = "SELECT po.*,
                u.full_name as supplier_name,
                CONCAT('SUP-', LPAD(u.id, 5, '0')) as supplier_code,
                u.email as supplier_email,
                u.phone as supplier_phone,
                cu.full_name as created_by_name
                FROM {$this->table} po
                LEFT JOIN users u ON po.supplier_id = u.id AND u.role = 'supplier'
                LEFT JOIN users cu ON po.created_by = cu.id
                WHERE po.id = :id";

        $result = $this->db->fetchAll($sql, [':id' => $id]);
        return $result[0] ?? null;
    }

    public function getPendingBySupplierId($supplier_id) {
        $sql = "SELECT po.*,
                u.full_name as supplier_name,
                cu.full_name as created_by_name
                FROM {$this->table} po
                LEFT JOIN users u ON po.supplier_id = u.id AND u.role = 'supplier'
                LEFT JOIN users cu ON po.created_by = cu.id
                WHERE po.supplier_id = :supplier_id
                AND po.status = 'pending_approval'
                ORDER BY po.created_at DESC";

        return $this->db->fetchAll($sql, [':supplier_id' => $supplier_id]);
    }

    public function updateStatus($id, $status, $approved_by = null, $notes = null) {
        $sql = "UPDATE {$this->table} 
                SET status = :status,
                    updated_at = NOW()";
        
        if ($approved_by) {
            $sql .= ", approved_by = :approved_by";
        }
        
        if ($notes) {
            $sql .= ", notes = CASE 
                        WHEN notes IS NULL OR notes = '' THEN :notes 
                        ELSE CONCAT(notes, '\n', :notes) 
                      END";
        }
        
        $sql .= " WHERE id = :id";
        
        $params = [
            ':id' => $id,
            ':status' => $status
        ];

        if ($approved_by) {
            $params[':approved_by'] = $approved_by;
        }

        if ($notes) {
            $params[':notes'] = $notes;
        }

        return $this->db->execute($sql, $params);
    }
}
