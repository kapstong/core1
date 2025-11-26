<?php
class PurchaseOrderItems {
    private $db;
    private $table = 'purchase_order_items';

    public function __construct($db) {
        $this->db = $db;
    }

    public function getByPurchaseOrderId($po_id) {
        $sql = "SELECT poi.*, 
                p.name as product_name,
                p.sku as product_sku,
                p.brand as product_brand
                FROM {$this->table} poi
                LEFT JOIN products p ON poi.product_id = p.id
                WHERE poi.po_id = :po_id
                ORDER BY poi.id ASC";
        
        return $this->db->fetchAll($sql, [':po_id' => $po_id]);
    }

    public function getTotalQuantityByPurchaseOrderId($po_id) {
        $sql = "SELECT SUM(quantity_ordered) as total_quantity 
                FROM {$this->table} 
                WHERE po_id = :po_id";
        
        $result = $this->db->fetchAll($sql, [':po_id' => $po_id]);
        return $result[0]['total_quantity'] ?? 0;
    }
}