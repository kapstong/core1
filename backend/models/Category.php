<?php
/**
 * Category Model
 */

require_once __DIR__ . '/../config/database.php';

class Category {
    private $db;
    private $table = 'categories';

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

    public function findBySlug($slug) {
        $query = "SELECT * FROM {$this->table} WHERE slug = :slug";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':slug', $slug);
        $stmt->execute();
        return $stmt->fetch();
    }

    public function create($data) {
        $query = "INSERT INTO {$this->table}
                  (name, slug, description, icon, is_active, sort_order)
                  VALUES
                  (:name, :slug, :description, :icon, :is_active, :sort_order)";

        $stmt = $this->db->prepare($query);

        $isActive = $data['is_active'] ?? 1;
        $sortOrder = $data['sort_order'] ?? 0;

        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':slug', $data['slug']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':icon', $data['icon']);
        $stmt->bindParam(':is_active', $isActive);
        $stmt->bindParam(':sort_order', $sortOrder);

        if ($stmt->execute()) {
            return $this->findById($this->db->lastInsertId());
        }

        return false;
    }

    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        foreach (['name', 'slug', 'description', 'icon', 'is_active', 'sort_order'] as $field) {
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
