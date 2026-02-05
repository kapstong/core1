<?php
/**
 * Shop Customer Returns API Endpoint
 * GET /backend/api/shop/returns.php - List customer returns
 * GET /backend/api/shop/returns.php?id=123 - Get specific return details
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get customer ID
$customerId = $_SESSION['customer_id'] ?? null;

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if (!$customerId) {
            Response::error('Customer authentication required', 401);
        }

        if (isset($_GET['id'])) {
            getReturnDetails($customerId, (int)$_GET['id']);
        } else {
            getCustomerReturns($customerId);
        }
    } else {
        Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Failed to retrieve returns: ' . $e->getMessage());
}

function getCustomerReturns($customerId) {
    $db = Database::getInstance()->getConnection();

    $query = "SELECT
                cr.*,
                co.order_number,
                COUNT(cri.id) as item_count,
                SUM(cri.quantity) as total_items
              FROM customer_returns cr
              INNER JOIN customer_orders co ON cr.order_id = co.id
              LEFT JOIN customer_return_items cri ON cr.id = cri.return_id
              WHERE cr.customer_id = :customer_id
              GROUP BY cr.id
              ORDER BY cr.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();

    $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success([
        'returns' => $returns,
        'total' => count($returns)
    ]);
}

function getReturnDetails($customerId, $returnId) {
    $db = Database::getInstance()->getConnection();

    // Get return with order info
    $query = "SELECT
                cr.*,
                co.order_number,
                co.order_date
              FROM customer_returns cr
              INNER JOIN customer_orders co ON cr.order_id = co.id
              WHERE cr.id = :return_id AND cr.customer_id = :customer_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':return_id', $returnId, PDO::PARAM_INT);
    $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();

    $return = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$return) {
        Response::error('Return not found', 404);
    }

    // Get return items
    $itemsQuery = "SELECT
                    cri.*,
                    p.name as current_product_name,
                    p.sku as current_product_sku,
                    p.image_url
                   FROM customer_return_items cri
                   LEFT JOIN products p ON cri.product_id = p.id
                   WHERE cri.return_id = :return_id
                   ORDER BY cri.id";

    $itemsStmt = $db->prepare($itemsQuery);
    $itemsStmt->bindParam(':return_id', $returnId, PDO::PARAM_INT);
    $itemsStmt->execute();

    $return['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success(['return' => $return]);
}

