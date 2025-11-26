<?php
/**
 * Sales Update API Endpoint
 * PUT /backend/api/sales/update.php - Update sale details (payment status, notes, etc.)
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Require authentication
Auth::requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        updateSale();
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Failed to update sale: ' . $e->getMessage());
}

function updateSale() {
    $user = Auth::user();
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    if (!isset($input['id']) || empty($input['id'])) {
        Response::error('Sale ID is required', 400);
    }

    $saleId = (int)$input['id'];
    $db = Database::getInstance()->getConnection();

    // Verify sale exists
    $checkQuery = "SELECT * FROM sales WHERE id = :id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':id', $saleId, PDO::PARAM_INT);
    $checkStmt->execute();

    $sale = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$sale) {
        Response::error('Sale not found', 404);
    }

    // Check if sale is already voided
    if ($sale['status'] === 'voided') {
        Response::error('Cannot update a voided sale', 400);
    }

    // Build update query dynamically
    $updateFields = [];
    $params = [':id' => $saleId];

    // Allowed fields to update (limited to non-critical fields)
    $allowedFields = ['payment_method', 'payment_status', 'notes'];

    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "$field = :$field";
            $params[":$field"] = $input[$field];
        }
    }

    if (empty($updateFields)) {
        Response::error('No fields to update', 400);
    }

    // Update sale
    $query = "UPDATE sales SET " . implode(', ', $updateFields) . " WHERE id = :id";
    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();

    // Log activity
    $activityQuery = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description)
                      VALUES (:user_id, 'update', 'sale', :sale_id, :description)";
    $activityStmt = $db->prepare($activityQuery);
    $activityStmt->bindParam(':user_id', $user['id'], PDO::PARAM_INT);
    $activityStmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
    $description = 'Updated sale ' . $sale['invoice_number'] . ' - Fields: ' . implode(', ', array_keys($updateFields));
    $activityStmt->bindParam(':description', $description);
    $activityStmt->execute();

    // Get updated sale
    $getQuery = "SELECT s.*, u.full_name as cashier_name
                 FROM sales s
                 LEFT JOIN users u ON s.cashier_id = u.id
                 WHERE s.id = :id";

    $getStmt = $db->prepare($getQuery);
    $getStmt->bindParam(':id', $saleId, PDO::PARAM_INT);
    $getStmt->execute();

    $updatedSale = $getStmt->fetch(PDO::FETCH_ASSOC);

    Response::success([
        'message' => 'Sale updated successfully',
        'sale' => $updatedSale
    ]);
}
