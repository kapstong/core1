<?php
/**
 * Inventory Synchronization API Endpoint
 * POST /backend/api/inventory/sync.php - Run inventory synchronization
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../utils/InventorySync.php';

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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Check user permissions (admin or inventory manager)
    if (!in_array($user['role'], ['admin', 'inventory_manager'])) {
        Response::error('Insufficient permissions', 403);
    }

    $syncType = $input['sync_type'] ?? 'full';
    $cleanupDays = (int)($input['cleanup_days'] ?? 30);

    $sync = new InventorySync();

    switch ($syncType) {
        case 'inventory':
            $results = $sync->syncInventoryLevels();
            break;

        case 'cleanup':
            $results = $sync->cleanupAbandonedCarts($cleanupDays);
            break;

        case 'validate':
            $results = $sync->validateOrderCommitments();
            break;

        case 'full':
        default:
            $results = $sync->runFullSync();
            break;
    }

    // Log the sync operation
    $db = Database::getInstance()->getConnection();
    $logQuery = "
        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address)
        VALUES (:user_id, 'inventory_sync_run', 'system', NULL, :details, :ip_address)
    ";
    $logStmt = $db->prepare($logQuery);
    $logStmt->bindValue(':user_id', $user['id'], PDO::PARAM_INT);
    $logStmt->bindValue(':details', json_encode([
        'sync_type' => $syncType,
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]));
    $logStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $logStmt->execute();

    Response::success([
        'message' => 'Inventory synchronization completed',
        'sync_type' => $syncType,
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    Response::serverError('Synchronization failed: ' . $e->getMessage());
}
?>
