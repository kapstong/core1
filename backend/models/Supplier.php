<?php
/**
 * Supplier Model
 */

require_once __DIR__ . '/../config/database.php';

class Supplier {
    private $db;
    private $table = 'suppliers';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($activeOnly = false) {
        $query = "SELECT * FROM {$this->table}";

        if ($activeOnly) {
            $query .= " WHERE is_active = 1";
        }

        $query .= " ORDER BY name ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function create($data) {
        $query = "INSERT INTO {$this->table}
                  (code, name, contact_person, email, phone, address, payment_terms, is_active, rating, notes)
                  VALUES
                  (:code, :name, :contact_person, :email, :phone, :address, :payment_terms, :is_active, :rating, :notes)";

        $stmt = $this->db->prepare($query);

        $isActive = $data['is_active'] ?? 1;
        $rating = $data['rating'] ?? 0.00;

        $stmt->bindParam(':code', $data['code']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':contact_person', $data['contact_person']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':payment_terms', $data['payment_terms']);
        $stmt->bindParam(':is_active', $isActive);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':notes', $data['notes']);

        if ($stmt->execute()) {
            return $this->findById($this->db->lastInsertId());
        }

        return false;
    }

    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = ['code', 'name', 'contact_person', 'email', 'phone',
                          'address', 'payment_terms', 'is_active', 'rating', 'notes'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) return false;

        $query = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
