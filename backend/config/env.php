<?php
/**
 * Simple .env File Loader
 * Loads environment variables from .env file
 */

class Env {
    private static $loaded = false;
    private static $variables = [];
    private static $filePath = null;

    /**
     * Load .env file
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            // Default path: root directory
            $path = dirname(dirname(__DIR__)) . '/.env';
        }
        self::$filePath = $path;

        if (!file_exists($path)) {
            // .env file doesn't exist, use defaults or error
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Skip if key is empty (malformed line)
                if (empty($key)) {
                    continue;
                }

                // Remove quotes if present
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                // Store in static array
                self::$variables[$key] = $value;

                // Keep runtime env aligned with file values.
                // This ensures .env edits take effect even if process env already has old values.
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable
     *
     * @param string $key Variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = null) {
        // Ensure .env is loaded
        if (!self::$loaded) {
            self::load();
        }

        // Prefer values loaded from .env file.
        if (array_key_exists($key, self::$variables)) {
            return self::castValue(self::$variables[$key]);
        }

        // Fallback to process/environment values.
        if (isset($_ENV[$key])) {
            return self::castValue($_ENV[$key]);
        }

        $value = getenv($key);
        if ($value !== false) {
            return self::castValue($value);
        }

        return $default;
    }

    /**
     * Cast string values to appropriate types
     */
    private static function castValue($value) {
        if ($value === null || $value === '') {
            return $value;
        }

        // Boolean values
        $lower = strtolower($value);
        if ($lower === 'true' || $lower === '(true)') {
            return true;
        }
        if ($lower === 'false' || $lower === '(false)') {
            return false;
        }
        if ($lower === 'null' || $lower === '(null)') {
            return null;
        }

        // Numeric values
        if (is_numeric($value)) {
            return $value + 0; // Converts to int or float
        }

        return $value;
    }

    /**
     * Check if environment variable exists
     */
    public static function has($key) {
        return self::get($key) !== null;
    }

    /**
     * Get all environment variables
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        return self::$variables;
    }

    /**
     * Get currently loaded .env file path
     */
    public static function path() {
        if (!self::$loaded) {
            self::load();
        }
        return self::$filePath;
    }
}
