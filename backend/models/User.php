<?php
/**
 * User Model
 * Handles user-related database operations
 */

require_once __DIR__ . '/../config/database.php';

class User {
    private $db;
    private $table = 'users';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Find user by ID
     */
    public function findById($id) {
        try {
            $query = "SELECT u.id, u.username, u.email, u.role, u.full_name, u.is_active, u.last_login, u.created_at, s.id as supplier_id
                      FROM {$this->table} u
                      LEFT JOIN suppliers s ON s.user_id = u.id
                      WHERE u.id = :id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch();
        } catch (PDOException $e) {
            // If suppliers table doesn't exist, try without the join
            $query = "SELECT u.id, u.username, u.email, u.role, u.full_name, u.is_active, u.last_login, u.created_at, NULL as supplier_id
                      FROM {$this->table} u
                      WHERE u.id = :id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch();
        }
    }

    /**
     * Find user by username
     */
    public function findByUsername($username) {
        try {
            $query = "SELECT u.*, s.id as supplier_id
                      FROM {$this->table} u
                      LEFT JOIN suppliers s ON s.user_id = u.id
                      WHERE u.username = :username";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            return $stmt->fetch();
        } catch (PDOException $e) {
            // If suppliers table doesn't exist, try without the join
            $query = "SELECT u.*, NULL as supplier_id
                      FROM {$this->table} u
                      WHERE u.username = :username";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            return $stmt->fetch();
        }
    }

    /**
     * Find user by email
     */
    public function findByEmail($email) {
        $query = "SELECT * FROM {$this->table}
                  WHERE email = :email";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Authenticate user
     */
    public function authenticate($username, $password) {
        $user = $this->findByUsername($username);

        if (!$user) {
            return false;
        }

        if (!$user['is_active']) {
            return false;
        }

        if (password_verify($password, $user['password_hash'])) {
            // Update last login
            $this->updateLastLogin($user['id']);
            return $user;
        }

        return false;
    }

    /**
     * Create new user
     */
    public function create($data) {
        $query = "INSERT INTO {$this->table}
                  (username, email, password_hash, role, full_name, is_active)
                  VALUES
                  (:username, :email, :password_hash, :role, :full_name, :is_active)";

        $stmt = $this->db->prepare($query);

        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        $isActive = $data['is_active'] ?? 1;

        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password_hash', $passwordHash);
        $stmt->bindParam(':role', $data['role']);
        $stmt->bindParam(':full_name', $data['full_name']);
        $stmt->bindParam(':is_active', $isActive);

        if ($stmt->execute()) {
            return $this->findById($this->db->lastInsertId());
        }

        return false;
    }

    /**
     * Update user
     */
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['username'])) {
            $fields[] = "username = :username";
            $params[':username'] = $data['username'];
        }

        if (isset($data['email'])) {
            $fields[] = "email = :email";
            $params[':email'] = $data['email'];
        }

        if (isset($data['full_name'])) {
            $fields[] = "full_name = :full_name";
            $params[':full_name'] = $data['full_name'];
        }

        if (isset($data['role'])) {
            $fields[] = "role = :role";
            $params[':role'] = $data['role'];
        }

        if (isset($data['is_active'])) {
            $fields[] = "is_active = :is_active";
            $params[':is_active'] = $data['is_active'];
        }

        if (isset($data['password'])) {
            $fields[] = "password_hash = :password_hash";
            $params[':password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (empty($fields)) {
            return false;
        }

        $query = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Delete user (hard delete)
     */
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Get all users
     */
    public function getAll($filters = []) {
        $query = "SELECT id, username, email, role, full_name, is_active, last_login, created_at
                  FROM {$this->table}
                  WHERE 1=1";

        $params = [];

        if (isset($filters['role'])) {
            $query .= " AND role = :role";
            $params[':role'] = $filters['role'];
        }

        // Note: No default is_active filter - show all users
        if (isset($filters['is_active'])) {
            $query .= " AND is_active = :is_active";
            $params[':is_active'] = $filters['is_active'];
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin($id) {
        $query = "UPDATE {$this->table} SET last_login = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE username = :username";

        if ($excludeId) {
            $query .= " AND id != :id";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);

        if ($excludeId) {
            $stmt->bindParam(':id', $excludeId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $result = $stmt->fetch();

        return $result['count'] > 0;
    }

    /**
     * Update user password
     */
    public function updatePassword($id, $hashedPassword) {
        $query = "UPDATE {$this->table} SET password_hash = :password_hash WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':password_hash', $hashedPassword);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE email = :email";

        if ($excludeId) {
            $query .= " AND id != :id";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);

        if ($excludeId) {
            $stmt->bindParam(':id', $excludeId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $result = $stmt->fetch();

        return $result['count'] > 0;
    }
}
