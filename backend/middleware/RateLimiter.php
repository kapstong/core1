<?php
/**
 * Rate Limiter Middleware
 * Prevents brute force attacks and API abuse
 */

class RateLimiter {
    private $db;
    private $defaultLimit;
    private $defaultWindow;

    public function __construct($limit = 60, $window = 60) {
        $this->defaultLimit = $limit; // Max requests
        $this->defaultWindow = $window; // Time window in seconds
        $this->db = Database::getInstance()->getConnection();
        $this->ensureTableExists();
    }

    /**
     * Ensure rate_limits table exists
     */
    private function ensureTableExists() {
        try {
            $query = "CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                endpoint VARCHAR(255) NOT NULL,
                request_count INT DEFAULT 1,
                window_start DATETIME NOT NULL,
                last_request DATETIME NOT NULL,
                INDEX idx_identifier (identifier),
                INDEX idx_endpoint (endpoint),
                INDEX idx_window_start (window_start)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            $this->db->exec($query);
        } catch (PDOException $e) {
            // Table might already exist, continue
        }
    }

    /**
     * Check if request is allowed
     *
     * @param string $identifier IP address or user ID
     * @param string $endpoint API endpoint
     * @param int $customLimit Custom rate limit (optional)
     * @param int $customWindow Custom time window (optional)
     * @return array Result with allowed status and remaining requests
     */
    public function checkLimit($identifier, $endpoint, $customLimit = null, $customWindow = null) {
        $limit = $customLimit ?? $this->defaultLimit;
        $window = $customWindow ?? $this->defaultWindow;

        $now = date('Y-m-d H:i:s');
        $windowStart = date('Y-m-d H:i:s', time() - $window);

        // Clean old entries first
        $this->cleanOldEntries($windowStart);

        // Get current rate limit record
        $query = "SELECT * FROM rate_limits
                  WHERE identifier = :identifier
                  AND endpoint = :endpoint
                  AND window_start >= :window_start
                  ORDER BY window_start DESC
                  LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':identifier' => $identifier,
            ':endpoint' => $endpoint,
            ':window_start' => $windowStart
        ]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            // First request in this window
            $this->createNewWindow($identifier, $endpoint);
            return [
                'allowed' => true,
                'limit' => $limit,
                'remaining' => $limit - 1,
                'reset_at' => date('Y-m-d H:i:s', time() + $window)
            ];
        }

        // Check if limit exceeded
        if ($record['request_count'] >= $limit) {
            $resetTime = strtotime($record['window_start']) + $window;
            return [
                'allowed' => false,
                'limit' => $limit,
                'remaining' => 0,
                'reset_at' => date('Y-m-d H:i:s', $resetTime),
                'retry_after' => max(0, $resetTime - time())
            ];
        }

        // Increment request count
        $this->incrementRequestCount($record['id']);

        return [
            'allowed' => true,
            'limit' => $limit,
            'remaining' => $limit - $record['request_count'] - 1,
            'reset_at' => date('Y-m-d H:i:s', strtotime($record['window_start']) + $window)
        ];
    }

    /**
     * Create new rate limit window
     */
    private function createNewWindow($identifier, $endpoint) {
        $query = "INSERT INTO rate_limits (identifier, endpoint, request_count, window_start, last_request)
                  VALUES (:identifier, :endpoint, 1, datetime('now'), datetime('now'))";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':identifier' => $identifier,
            ':endpoint' => $endpoint
        ]);
    }

    /**
     * Increment request count
     */
    private function incrementRequestCount($recordId) {
        $query = "UPDATE rate_limits
                  SET request_count = request_count + 1,
                      last_request = datetime('now')
                  WHERE id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':id' => $recordId]);
    }

    /**
     * Clean old rate limit entries
     */
    private function cleanOldEntries($windowStart) {
        $query = "DELETE FROM rate_limits WHERE window_start < :window_start";
        $stmt = $this->db->prepare($query);
        $stmt->execute([':window_start' => $windowStart]);
    }

    /**
     * Get identifier from request (IP + User Agent hash)
     */
    public static function getIdentifier() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // If user is authenticated, use user ID as part of identifier
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
            return 'user_' . $_SESSION['user_id'];
        }

        // Otherwise use IP + user agent hash
        return $ip . '_' . substr(md5($userAgent), 0, 8);
    }

    /**
     * Get current endpoint
     */
    public static function getEndpoint() {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Remove query string
        $endpoint = parse_url($uri, PHP_URL_PATH);
        // Normalize endpoint
        return $endpoint;
    }

    /**
     * Middleware function to check rate limit and respond
     *
     * @param int $limit Requests per window
     * @param int $window Time window in seconds
     * @param string $customEndpoint Custom endpoint name (optional)
     */
    public static function check($limit = 60, $window = 60, $customEndpoint = null) {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../utils/Response.php';

        $limiter = new self($limit, $window);
        $identifier = self::getIdentifier();
        $endpoint = $customEndpoint ?? self::getEndpoint();

        $result = $limiter->checkLimit($identifier, $endpoint);

        // Add rate limit headers
        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . strtotime($result['reset_at']));

        if (!$result['allowed']) {
            header('Retry-After: ' . $result['retry_after']);
            Response::error('Rate limit exceeded. Please try again later.', 429);
        }
    }

    /**
     * Apply specific rate limits for different endpoint types
     */
    public static function apply($type = 'default') {
        $limits = [
            'login' => ['limit' => 5, 'window' => 300], // 5 attempts per 5 minutes
            'api' => ['limit' => 60, 'window' => 60], // 60 requests per minute
            'api_heavy' => ['limit' => 10, 'window' => 60], // 10 requests per minute (reports, exports)
            'default' => ['limit' => 100, 'window' => 60] // 100 requests per minute
        ];

        $config = $limits[$type] ?? $limits['default'];
        self::check($config['limit'], $config['window'], $type);
    }

    /**
     * Reset rate limit for specific identifier
     */
    public function resetLimit($identifier, $endpoint = null) {
        if ($endpoint) {
            $query = "DELETE FROM rate_limits WHERE identifier = :identifier AND endpoint = :endpoint";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':identifier' => $identifier,
                ':endpoint' => $endpoint
            ]);
        } else {
            $query = "DELETE FROM rate_limits WHERE identifier = :identifier";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':identifier' => $identifier]);
        }
    }

    /**
     * Get current usage statistics
     */
    public function getUsageStats($identifier, $endpoint = null) {
        if ($endpoint) {
            $query = "SELECT * FROM rate_limits
                      WHERE identifier = :identifier
                      AND endpoint = :endpoint
                      ORDER BY window_start DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':identifier' => $identifier,
                ':endpoint' => $endpoint
            ]);
        } else {
            $query = "SELECT * FROM rate_limits
                      WHERE identifier = :identifier
                      ORDER BY window_start DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':identifier' => $identifier]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
