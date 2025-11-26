<?php
/**
 * Notifications Count API Endpoint
 * GET /backend/api/notifications/count.php - Get unread notification count
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication first
if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

// Get user data
$user = Auth::user();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get unread count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM notifications
        WHERE user_id = :user_id AND is_read = 0
    ");
    $stmt->execute([':user_id' => $user['id']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'unread_count' => (int)$data['unread_count']
    ]);

} catch (Exception $e) {
    Response::serverError('Failed to fetch notification count: ' . $e->getMessage());
}
