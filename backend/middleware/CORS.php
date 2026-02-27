<?php
/**
 * CORS Middleware
 * Handle Cross-Origin Resource Sharing with security controls
 */

class CORS {
    /**
     * Build same-site origin candidates from runtime host.
     */
    private static function getRuntimeOriginCandidates() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $host = trim((string)$host);
        if ($host === '') {
            return [];
        }

        $isHttps = false;
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            $isHttps = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $isHttps = true;
        }

        $primaryScheme = $isHttps ? 'https' : 'http';
        $alternateScheme = $isHttps ? 'http' : 'https';

        $origins = [
            $primaryScheme . '://' . $host
        ];

        // Add alternate scheme as fallback when proxy/server reports inconsistent HTTPS flags.
        $origins[] = $alternateScheme . '://' . $host;

        return array_values(array_unique($origins));
    }

    /**
     * Get allowed origins from configuration or defaults
     */
    private static function getAllowedOrigins() {
        // 1. Check environment variables first (.env file)
        if (file_exists(__DIR__ . '/../config/env.php')) {
            require_once __DIR__ . '/../config/env.php';
            $envOrigins = Env::get('CORS_ALLOWED_ORIGINS');

            if ($envOrigins) {
                // Support comma-separated list in .env
                $origins = array_map('trim', explode(',', $envOrigins));
                return array_filter($origins); // Remove empty values
            }
        }

        // 2. Check database configuration
        try {
            if (class_exists('Database')) {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'allowed_origins' LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($result && !empty($result['setting_value'])) {
                    return explode(',', $result['setting_value']);
                }
            }
        } catch (Exception $e) {
            // Database not available, continue to defaults
        }

        // 3. Default allowed origins for development
        // IMPORTANT: In production, set CORS_ALLOWED_ORIGINS in .env file
        // Example: CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

        // For localhost development plus same-site origin fallback.
        $defaults = [
            'http://localhost',
            'http://localhost:3000',
            'http://localhost:8000',
            'http://127.0.0.1',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8000'
        ];

        return array_values(array_unique(array_merge($defaults, self::getRuntimeOriginCandidates())));
    }

    /**
     * Check if origin is allowed
     */
    private static function isOriginAllowed($origin) {
        $allowedOrigins = self::getAllowedOrigins();

        // If wildcard is present, allow all
        if (in_array('*', $allowedOrigins)) {
            return true;
        }

        // Check if origin is in whitelist
        return in_array($origin, $allowedOrigins);
    }

    /**
     * Set CORS headers with security controls
     *
     * @param bool $allowCredentials Whether to allow credentials
     * @param array $customOrigins Custom allowed origins (optional)
     */
    public static function handle($allowCredentials = true, $customOrigins = null) {
        $allowedOrigins = $customOrigins ?? self::getAllowedOrigins();

        // Handle origin validation
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];

            // Check if wildcard or specific origin is allowed
            if (in_array('*', $allowedOrigins)) {
                // Development mode - allow all origins
                header("Access-Control-Allow-Origin: $origin");
            } elseif (in_array($origin, $allowedOrigins)) {
                // Production mode - only allow whitelisted origins
                header("Access-Control-Allow-Origin: $origin");
            } else {
                // Origin not allowed - don't set CORS headers
                // The browser will block the request
                header('HTTP/1.1 403 Forbidden');
                echo json_encode([
                    'success' => false,
                    'message' => 'Origin not allowed by CORS policy'
                ]);
                exit;
            }

            if ($allowCredentials) {
                header('Access-Control-Allow-Credentials: true');
            }

            header('Access-Control-Max-Age: 86400'); // cache for 1 day
        }

        // Handle preflight OPTIONS requests
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                // Only allow specific methods
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            }

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                // Allow specific headers
                $allowedHeaders = [
                    'Content-Type',
                    'Authorization',
                    'X-Requested-With',
                    'Accept',
                    'Origin',
                    'X-CSRF-Token'
                ];

                $requestedHeaders = explode(',', $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
                $requestedHeaders = array_map('trim', $requestedHeaders);

                // Only allow whitelisted headers
                $validHeaders = array_intersect($requestedHeaders, $allowedHeaders);

                if (!empty($validHeaders)) {
                    header("Access-Control-Allow-Headers: " . implode(', ', $validHeaders));
                }
            }

            http_response_code(204); // No Content
            exit(0);
        }

        // Add security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Strict CORS mode - only allow specific origins, no wildcard
     *
     * @param array $allowedOrigins Array of allowed origin URLs
     */
    public static function handleStrict($allowedOrigins) {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = $_SERVER['HTTP_ORIGIN'];

            if (in_array($origin, $allowedOrigins)) {
                header("Access-Control-Allow-Origin: $origin");
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 86400');
            } else {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode([
                    'success' => false,
                    'message' => 'Origin not allowed'
                ]);
                exit;
            }
        }

        // Handle preflight
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }
            http_response_code(204);
            exit(0);
        }

        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
    }

    /**
     * Set JSON content type
     */
    public static function json() {
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * Disable CORS (for same-origin only APIs)
     */
    public static function disable() {
        // Don't set any CORS headers - same origin only
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
    }
}
