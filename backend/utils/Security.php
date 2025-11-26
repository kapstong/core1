<?php
/**
 * Security Utility Class
 * Provides security enhancements including rate limiting, input sanitization, and threat detection
 */

class Security {
    private static $db;
    private static $rateLimits = [];

    public static function init() {
        self::$db = Database::getInstance()->getConnection();
    }

    /**
     * Rate limiting check
     */
    public static function checkRateLimit($identifier, $action, $maxRequests = 100, $timeWindow = 3600) {
        if (!self::$db) self::init();

        $windowStart = date('Y-m-d H:i:s', time() - $timeWindow);

        // Clean old rate limit entries
        $cleanQuery = "DELETE FROM rate_limits WHERE created_at < :window_start";
        $cleanStmt = self::$db->prepare($cleanQuery);
        $cleanStmt->bindParam(':window_start', $windowStart);
        $cleanStmt->execute();

        // Check current request count
        $checkQuery = "SELECT COUNT(*) as request_count FROM rate_limits
                      WHERE identifier = :identifier AND action = :action AND created_at >= :window_start";
        $checkStmt = self::$db->prepare($checkQuery);
        $checkStmt->bindParam(':identifier', $identifier);
        $checkStmt->bindParam(':action', $action);
        $checkStmt->bindParam(':window_start', $windowStart);
        $checkStmt->execute();

        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $requestCount = (int)$result['request_count'];

        if ($requestCount >= $maxRequests) {
            // Log rate limit violation
            $logger = new Logger();
            $logger->logSecurity('rate_limit_exceeded', [
                'identifier' => $identifier,
                'action' => $action,
                'request_count' => $requestCount,
                'max_requests' => $maxRequests,
                'time_window' => $timeWindow
            ]);

            return [
                'allowed' => false,
                'remaining_requests' => 0,
                'reset_time' => time() + $timeWindow
            ];
        }

        // Log this request
        $logQuery = "INSERT INTO rate_limits (identifier, action, ip_address, user_agent, created_at)
                    VALUES (:identifier, :action, :ip_address, :user_agent, datetime('now'))";
        $logStmt = self::$db->prepare($logQuery);
        $logStmt->bindParam(':identifier', $identifier);
        $logStmt->bindParam(':action', $action);
        $logStmt->bindParam(':ip_address', self::getClientIP());
        $logStmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $logStmt->execute();

        return [
            'allowed' => true,
            'remaining_requests' => $maxRequests - $requestCount - 1,
            'reset_time' => time() + $timeWindow
        ];
    }

    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $data);
        }

        if ($data === null) {
            return null;
        }

        switch ($type) {
            case 'email':
                return filter_var(trim($data), FILTER_SANITIZE_EMAIL);

            case 'url':
                return filter_var(trim($data), FILTER_SANITIZE_URL);

            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);

            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

            case 'html':
                return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');

            case 'string':
            default:
                // Remove null bytes, strip tags, trim whitespace
                $sanitized = str_replace("\0", '', $data);
                $sanitized = strip_tags($sanitized);
                $sanitized = trim($sanitized);
                return $sanitized;
        }
    }

    /**
     * Validate input data
     */
    public static function validateInput($data, $rules) {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            foreach ($fieldRules as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleParam = $ruleParts[1] ?? null;

                switch ($ruleName) {
                    case 'required':
                        if (empty($value) && $value !== '0' && $value !== 0) {
                            $errors[$field][] = 'This field is required';
                        }
                        break;

                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = 'Invalid email format';
                        }
                        break;

                    case 'min':
                        if (!empty($value) && strlen($value) < $ruleParam) {
                            $errors[$field][] = "Minimum length is {$ruleParam} characters";
                        }
                        break;

                    case 'max':
                        if (!empty($value) && strlen($value) > $ruleParam) {
                            $errors[$field][] = "Maximum length is {$ruleParam} characters";
                        }
                        break;

                    case 'numeric':
                        if (!empty($value) && !is_numeric($value)) {
                            $errors[$field][] = 'Must be a number';
                        }
                        break;

                    case 'alpha':
                        if (!empty($value) && !ctype_alpha($value)) {
                            $errors[$field][] = 'Must contain only letters';
                        }
                        break;

                    case 'alphanum':
                        if (!empty($value) && !ctype_alnum($value)) {
                            $errors[$field][] = 'Must contain only letters and numbers';
                        }
                        break;
                }
            }
        }

        return $errors;
    }

    /**
     * Generate secure token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64MB
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Check password strength
     */
    public static function checkPasswordStrength($password) {
        $score = 0;
        $errors = [];

        // Length check
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        } else {
            $score += 25;
        }

        // Uppercase check
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        } else {
            $score += 25;
        }

        // Lowercase check
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        } else {
            $score += 25;
        }

        // Number check
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        } else {
            $score += 25;
        }

        return [
            'score' => $score,
            'strength' => $score >= 75 ? 'strong' : ($score >= 50 ? 'medium' : 'weak'),
            'errors' => $errors
        ];
    }

    /**
     * Detect suspicious activity
     */
    public static function detectSuspiciousActivity($userId = null) {
        if (!self::$db) self::init();

        $ip = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check for rapid requests from same IP
        $recentQuery = "SELECT COUNT(*) as recent_requests FROM rate_limits
                       WHERE ip_address = :ip AND created_at >= DATE_SUB(datetime('now'), INTERVAL 1 MINUTE)";
        $recentStmt = self::$db->prepare($recentQuery);
        $recentStmt->bindParam(':ip', $ip);
        $recentStmt->execute();

        $recent = $recentStmt->fetch(PDO::FETCH_ASSOC);

        if ($recent['recent_requests'] > 30) { // More than 30 requests per minute
            $logger = new Logger($userId);
            $logger->logSecurity('suspicious_activity_detected', [
                'type' => 'high_request_rate',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'request_count' => $recent['recent_requests']
            ]);

            return true;
        }

        // Check for suspicious user agents
        $suspiciousPatterns = [
            'sqlmap',
            'nmap',
            'nikto',
            'dirbuster',
            'gobuster',
            'masscan'
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                $logger = new Logger($userId);
                $logger->logSecurity('suspicious_activity_detected', [
                    'type' => 'suspicious_user_agent',
                    'ip_address' => $ip,
                    'user_agent' => $userAgent,
                    'pattern_detected' => $pattern
                ]);

                return true;
            }
        }

        return false;
    }

    /**
     * Log security event
     */
    public static function logSecurityEvent($event, $details = [], $userId = null) {
        $logger = new Logger($userId);
        $logger->logSecurity($event, array_merge($details, [
            'ip_address' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }

    /**
     * Get client IP address (improved version)
     */
    public static function getClientIP() {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx
            'HTTP_X_FORWARDED_FOR',  // Standard proxy
            'HTTP_X_FORWARDED',      // Alternative proxy
            'HTTP_X_CLUSTER_CLIENT_IP', // Alternative proxy
            'HTTP_FORWARDED_FOR',    // RFC 7239
            'HTTP_FORWARDED',        // RFC 7239
            'REMOTE_ADDR'            // Fallback
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (multiple proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP and check if it's not a private/reserved range
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * CSRF token generation and validation
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateToken(32);
        }

        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Encrypt sensitive data
     */
    public static function encrypt($data, $key = null) {
        if ($key === null) {
            $key = self::getEncryptionKey();
        }

        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     */
    public static function decrypt($encryptedData, $key = null) {
        if ($key === null) {
            $key = self::getEncryptionKey();
        }

        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get encryption key
     */
    private static function getEncryptionKey() {
        // In production, this should be loaded from environment variables or a secure key store
        $key = getenv('ENCRYPTION_KEY') ?: 'default_encryption_key_change_in_production';

        // Ensure key is 32 bytes for AES-256
        return substr(hash('sha256', $key), 0, 32);
    }

    /**
     * Clean input data recursively
     */
    public static function cleanInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'cleanInput'], $data);
        }

        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);

            // Remove potentially dangerous characters but keep basic punctuation
            $data = filter_var($data, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

            return trim($data);
        }

        return $data;
    }
}

// Initialize security on load
Security::init();
