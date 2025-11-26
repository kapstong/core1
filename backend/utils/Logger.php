<?php
/**
 * Activity Logger Utility Class
 * Logs all system activities for audit trails and security monitoring
 */

require_once __DIR__ . '/../config/database.php';

class Logger {
    private $db;
    private $userId;
    private $userAgent;
    private $ipAddress;

    public function __construct($userId = null) {
        $this->db = Database::getInstance()->getConnection();
        $this->userId = $userId;
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->ipAddress = $this->getClientIPInstance();
    }

    /**
     * Log an activity
     *
     * @param string $action The action performed
     * @param string $entityType The type of entity (user, product, sale, etc.)
     * @param int $entityId The ID of the entity
     * @param array $details Additional details as JSON
     * @param string $level Log level (info, warning, error, critical)
     */
    public function log($action, $entityType = null, $entityId = null, $details = [], $level = 'info') {
        try {
            $query = "INSERT INTO activity_logs
                      (user_id, action, entity_type, entity_id, details, ip_address, user_agent, created_at)
                      VALUES
                      (:user_id, :action, :entity_type, :entity_id, :details, :ip_address, :user_agent, NOW())";

            $stmt = $this->db->prepare($query);
            $detailsJson = json_encode($details);
            $stmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':entity_type', $entityType);
            $stmt->bindParam(':entity_id', $entityId, PDO::PARAM_INT);
            $stmt->bindParam(':details', $detailsJson);
            $stmt->bindParam(':ip_address', $this->ipAddress);
            $stmt->bindParam(':user_agent', $this->userAgent);

            $stmt->execute();

            // Also log critical errors to PHP error log
            if ($level === 'critical' || $level === 'error') {
                error_log("[$level] $action: " . json_encode($details));
            }

        } catch (Exception $e) {
            // Fallback to PHP error log if database logging fails
            error_log("Logger Error: " . $e->getMessage());
        }
    }

    /**
     * Log user authentication events
     */
    public function logAuth($action, $userId = null, $details = []) {
        $this->userId = $userId; // Override with actual user ID
        $this->log($action, 'user', $userId, $details, 'info');
    }

    /**
     * Log product-related activities
     */
    public function logProduct($action, $productId, $details = []) {
        $this->log($action, 'product', $productId, $details, 'info');
    }

    /**
     * Log sales/order activities
     */
    public function logSale($action, $saleId, $details = []) {
        $this->log($action, 'sale', $saleId, $details, 'info');
    }

    /**
     * Log inventory/stock activities
     */
    public function logInventory($action, $productId, $details = []) {
        $this->log($action, 'inventory', $productId, $details, 'info');
    }

    /**
     * Log supplier activities
     */
    public function logSupplier($action, $supplierId, $details = []) {
        $this->log($action, 'supplier', $supplierId, $details, 'info');
    }

    /**
     * Log purchase order activities
     */
    public function logPurchaseOrder($action, $poId, $details = []) {
        $this->log($action, 'purchase_order', $poId, $details, 'info');
    }

    /**
     * Log customer activities (for shop)
     */
    public function logCustomer($action, $customerId, $details = []) {
        $this->log($action, 'customer', $customerId, $details, 'info');
    }

    /**
     * Log system/admin activities
     */
    public function logSystem($action, $details = []) {
        $this->log($action, 'system', null, $details, 'info');
    }

    /**
     * Log security events
     */
    public function logSecurity($action, $details = []) {
        $this->log($action, 'security', null, $details, 'warning');
    }

    /**
     * Log errors (instance method)
     */
    public function logErrorInstance($action, $error, $details = []) {
        $details['error'] = $error;
        $this->log($action, 'error', null, $details, 'error');
    }

    /**
     * Get recent activities
     */
    public function getRecentActivities($limit = 50, $userId = null, $entityType = null, $startDate = null, $endDate = null) {
        $query = "SELECT al.*, u.username, u.full_name
                  FROM activity_logs al
                  LEFT JOIN users u ON al.user_id = u.id
                  WHERE 1=1";

        $params = [];

        if ($userId) {
            $query .= " AND al.user_id = :user_id";
            $params[':user_id'] = $userId;
        }

        if ($entityType) {
            $query .= " AND al.entity_type = :entity_type";
            $params[':entity_type'] = $entityType;
        }

        if ($startDate) {
            $query .= " AND date(al.created_at) >= :start_date";
            $params[':start_date'] = $startDate;
        }

        if ($endDate) {
            $query .= " AND date(al.created_at) <= :end_date";
            $params[':end_date'] = $endDate;
        }

        $query .= " ORDER BY al.created_at DESC LIMIT :limit";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get client IP address (instance method)
     */
    private function getClientIPInstance() {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (like X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get client IP address (static method for API use)
     */
    public static function getClientIP() {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (like X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get user agent (static method)
     */
    public static function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Log error (static method for API use)
     */
    public static function logError($action, $details = [], $userId = null) {
        $logger = new self($userId);
        $logger->log($action, 'error', null, $details, 'error');
    }

    /**
     * Clean old logs (optional maintenance function)
     */
    public function cleanOldLogs($daysToKeep = 90) {
        $query = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':days', $daysToKeep, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
