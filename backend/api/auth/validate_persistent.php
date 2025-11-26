<?php
/**
 * Validate Persistent Login Token API Endpoint
 * POST /backend/api/auth/validate_persistent.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Allow both GET and POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    Response::error('Method not allowed', 405);
}

try {
    // Get token from either GET parameter or POST body
    $token = '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = isset($_GET['token']) ? trim($_GET['token']) : '';
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = isset($input['token']) ? trim($input['token']) : '';
    }

    if (empty($token)) {
        Response::error('Token is required', 400);
    }

    // Validate the persistent token
    $isValid = Auth::validatePersistentToken($token);

    if ($isValid) {
        Response::success(['message' => 'Token validated successfully']);
    } else {
        Response::error('Invalid or expired token', 401);
    }

} catch (Exception $e) {
    error_log('Persistent token validation error: ' . $e->getMessage());
    Response::serverError('An error occurred during token validation');
}
?>
