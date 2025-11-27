<?php
/**
 * User Preferences API
 * GET/POST /backend/api/user/preferences.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/Response.php';

// Require authentication
if (!Auth::check()) {
    Response::unauthorized();
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = Auth::userId();

try {
    $db = Database::getInstance()->getConnection();

    if ($method === 'GET') {
        // Get user preferences
        $query = "SELECT inactivity_timeout FROM users WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            Response::success([
                'inactivity_timeout' => $user['inactivity_timeout'] ?? 30
            ]);
        } else {
            Response::error('User not found', 404);
        }

    } elseif ($method === 'POST') {
        // Update user preferences
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['inactivity_timeout'])) {
            Response::error('inactivity_timeout is required');
        }

        $timeout = intval($input['inactivity_timeout']);

        // Validate timeout value
        if ($timeout < 0 || $timeout > 1440) {
            Response::error('Inactivity timeout must be between 0 and 1440 minutes');
        }

        // Update user's preference
        $query = "UPDATE users SET inactivity_timeout = :timeout WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':timeout', $timeout);
        $stmt->bindParam(':user_id', $userId);

        if ($stmt->execute()) {
            Response::success([
                'inactivity_timeout' => $timeout
            ], 'Preference saved successfully');
        } else {
            Response::serverError('Failed to save preference');
        }

    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    error_log('User preferences error: ' . $e->getMessage());
    Response::serverError('An error occurred');
}
