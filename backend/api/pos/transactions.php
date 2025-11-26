<?php
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Product.php';

header('Content-Type: application/json; charset=UTF-8');

CORS::handle();

// Check authentication using session-based auth
Auth::requireAuth();

// Get user data
$user = Auth::user();

// Check permissions - staff, admin, or inventory_manager can access POS
$allowedRoles = ['admin', 'staff', 'inventory_manager'];
if (!in_array($user['role'], $allowedRoles)) {
    Response::error('Access denied', 403);
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // List POS transactions with filtering
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;

            // Build query
            $query = "SELECT
                        t.id,
                        t.transaction_number,
                        t.customer_id,
                        t.total_amount,
                        t.payment_method,
                        t.cash_received,
                        t.change_given,
                        t.created_at,
                        t.updated_at,
                        c.name as customer_name,
                        c.email as customer_email,
                        COUNT(ti.id) as item_count
                      FROM pos_transactions t
                      LEFT JOIN customers c ON t.customer_id = c.id
                      LEFT JOIN pos_transaction_items ti ON t.id = ti.transaction_id";

            $conditions = [];
            $params = [];

            if ($dateFrom) {
                $conditions[] = "DATE(t.created_at) >= ?";
                $params[] = $dateFrom;
            }

            if ($dateTo) {
                $conditions[] = "DATE(t.created_at) <= ?";
                $params[] = $dateTo;
            }

            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }

            $query .= " GROUP BY t.id ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get details for each transaction
            foreach ($transactions as &$transaction) {
                // Get transaction items
                $itemQuery = "SELECT
                               ti.*,
                               p.name as product_name,
                               p.sku
                             FROM pos_transaction_items ti
                             JOIN products p ON ti.product_id = p.id
                             WHERE ti.transaction_id = ?
                             ORDER BY ti.created_at";
                $itemStmt = $db->prepare($itemQuery);
                $itemStmt->execute([$transaction['id']]);
                $transaction['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            Response::success('Transactions retrieved successfully', [
                'transactions' => $transactions,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::error('Database error: ' . $e->getMessage(), 500);
}
?>
