<?php
/**
 * Database Configuration and Connection Class
 * PC Parts Merchandising System
 */

// Load environment variables
require_once __DIR__ . '/env.php';
Env::load();

class Database {
    private static $instance = null;
    private $conn;

    // Database configuration (with fallback defaults)
    private $db_type;
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        // Database configuration - MySQL only
        $this->db_type = 'mysql'; // Force MySQL only
        $this->host     = Env::get('DB_HOST', 'localhost');
        $this->db_name  = Env::get('DB_NAME', 'core1_core1merch');
        $this->username = Env::get('DB_USER', 'core1_karldc');
        $this->password = Env::get('DB_PASSWORD', 'karlkevin1122!!');
        $this->charset  = Env::get('DB_CHARSET', 'utf8mb4');

        try {
            // MySQL connection only
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

        } catch (PDOException $e) {
            error_log("MySQL Database Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }



    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->conn;
    }

    /**
     * Execute a query and return result
     */
    public function query($sql, $params = []) {
        if ($this->conn === null) {
            // Return mock statement when no database connection
            return new MockStatement();
        }
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $message = $e->getMessage();
            if (stripos($message, 'deleted_at') !== false) {
                // Fallback for environments missing deleted_at columns
                $fallbackSql = $sql;
                $fallbackSql = preg_replace('/\\s+AND\\s+[a-zA-Z0-9_\\.]*deleted_at\\s+IS\\s+NULL/i', '', $fallbackSql);
                $fallbackSql = preg_replace('/\\s+WHERE\\s+[a-zA-Z0-9_\\.]*deleted_at\\s+IS\\s+NULL\\s*(AND\\s*)?/i', ' WHERE ', $fallbackSql);
                $fallbackSql = preg_replace('/,\\s*deleted_at\\s*=\\s*NOW\\(\\)/i', '', $fallbackSql);
                $fallbackSql = preg_replace('/\\s+deleted_at\\s*=\\s*NOW\\(\\),/i', '', $fallbackSql);
                $fallbackSql = preg_replace('/\\s+deleted_at\\s*=\\s*NOW\\(\\)\\s*/i', '', $fallbackSql);
                $fallbackSql = preg_replace('/\\s+WHERE\\s*$/i', '', $fallbackSql);

                try {
                    $stmt = $this->conn->prepare($fallbackSql);
                    $stmt->execute($params);
                    return $stmt;
                } catch (PDOException $fallbackException) {
                    throw new Exception("Query failed: " . $message);
                }
            }
            throw new Exception("Query failed: " . $message);
        }
    }

    /**
     * Execute a query and return all results as associative array
     */
    public function fetchAll($sql, $params = []) {
        if ($this->conn === null) {
            // Return mock data based on query type
            return $this->getMockData($sql);
        }
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return single row
     */
    public function fetchOne($sql, $params = []) {
        if ($this->conn === null) {
            $results = $this->getMockData($sql);
            return $results ? $results[0] : null;
        }
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Execute a query and return single value
     */
    public function fetchValue($sql, $params = []) {
        if ($this->conn === null) {
            $row = $this->fetchOne($sql, $params);
            return $row ? reset($row) : null;
        }
        $row = $this->fetchOne($sql, $params);
        return $row ? reset($row) : null;
    }

    /**
     * Get mock data for common queries
     */
    private function getMockData($sql) {
        $sql = strtolower($sql);

        if (strpos($sql, 'select count(*)') !== false && strpos($sql, 'products') !== false) {
            return [['count' => 9]]; // Mock product count
        }

        if (strpos($sql, 'select count(*)') !== false && strpos($sql, 'categories') !== false) {
            return [['count' => 9]]; // Mock category count
        }

        if (strpos($sql, 'select count(*)') !== false && strpos($sql, 'users') !== false) {
            return [['count' => 1]]; // Mock user count
        }

        if (strpos($sql, 'select sum') !== false && strpos($sql, 'cost_price') !== false) {
            return [['total_value' => 5000.00]]; // Mock inventory value
        }

        if (strpos($sql, 'select count(*)') !== false && strpos($sql, 'sales') !== false) {
            return [['count' => 0]]; // Mock sales count
        }

        if (strpos($sql, 'select coalesce(sum') !== false) {
            return [['total' => 0]]; // Mock sales total
        }

        // Default empty result
        return [];
    }

    /**
     * Get last inserted ID
     */
    public function getLastInsertId() {
        if ($this->conn === null) {
            return 1; // Mock ID
        }
        return $this->conn->lastInsertId();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Mock Statement class for when no database connection is available
 */
class MockStatement {
    public function fetchAll() {
        return [];
    }

    public function fetch() {
        return null;
    }

    public function execute($params = []) {
        return true;
    }
}
