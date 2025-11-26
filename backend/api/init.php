<?php
/**
 * API Initialization File
 * Include this at the top of all API endpoints for consistent setup
 */

// Disable error display for clean JSON responses (log errors instead)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Log errors to file
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/api_errors.log');

// Set default timezone
date_default_timezone_set('UTC');

// Start session if not already started (for authentication)
if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax'); // Changed from Strict to Lax for better compatibility

    // Use secure cookies in production (HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }

    session_start();
}

// Set JSON content type as default
header('Content-Type: application/json; charset=utf-8');

// Enable output buffering to catch any stray output
ob_start();

// Register shutdown function to clean output buffer
register_shutdown_function(function() {
    $output = ob_get_clean();

    // Check if output is valid JSON
    $decoded = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        // Valid JSON, output it
        echo $output;
    } else {
        // Invalid output, try to clean it
        // Remove any HTML or whitespace before the JSON
        if (preg_match('/(\{|\[).*$/s', $output, $matches)) {
            echo $matches[0];
        } else {
            // No JSON found, output error
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error - invalid API response'
            ]);
        }
    }
});
