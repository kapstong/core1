<?php
/**
 * Authentication Check API Endpoint
 * GET /backend/api/auth/check.php
 */

// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    // Check if user is authenticated (includes suppliers)
    if (Auth::check()) {
        // Get current user data
        $user = Auth::user();
        // Return data in standard Response format
        Response::success([
            'authenticated' => true,
            'user' => $user
        ], 'User authenticated successfully');
    } else {
        // Return data in standard Response format
        Response::success([
            'authenticated' => false,
            'user' => null
        ], 'User not authenticated');
    }

} catch (Exception $e) {
    Response::serverError('An error occurred during authentication check');
}
