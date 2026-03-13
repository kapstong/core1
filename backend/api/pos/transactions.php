<?php
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

CORS::handle();
Auth::requireAuth();

$user = Auth::user();
$allowedRoles = ['admin', 'staff', 'inventory_manager', 'purchasing_officer'];
if (!in_array((string)($user['role'] ?? ''), $allowedRoles, true)) {
    Response::error('Access denied', 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
    Response::error('Method not allowed', 405);
}

try {
    $db = Database::getInstance()->getConnection();

    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));

    $query = "
        SELECT
            s.id,
            s.invoice_number AS transaction_number,
            s.total_amount,
            s.payment_method,
            s.created_at,
            s.updated_at,
            COALESCE(NULLIF(s.customer_name, ''), 'Walk-in Customer') AS customer_name,
            s.customer_email AS customer_email,
            COUNT(si.id) AS item_count
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        WHERE 1=1
    ";

    $params = [];

    if ($dateFrom !== '') {
        $query .= " AND DATE(s.created_at) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $query .= " AND DATE(s.created_at) <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    $query .= "
        GROUP BY
            s.id, s.invoice_number, s.total_amount, s.payment_method,
            s.created_at, s.updated_at, s.customer_name, s.customer_email
        ORDER BY s.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($transactions)) {
        $itemStmt = $db->prepare("
            SELECT
                si.id,
                si.product_id,
                si.quantity,
                si.unit_price,
                (si.quantity * si.unit_price) AS total_price,
                p.name AS product_name,
                p.sku
            FROM sale_items si
            INNER JOIN products p ON si.product_id = p.id
            WHERE si.sale_id = :sale_id
            ORDER BY si.id ASC
        ");

        foreach ($transactions as &$transaction) {
            $itemStmt->bindValue(':sale_id', (int)$transaction['id'], PDO::PARAM_INT);
            $itemStmt->execute();
            $transaction['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($transaction);
    }

    Response::success([
        'transactions' => $transactions,
        'limit' => $limit,
        'offset' => $offset
    ], 'Transactions retrieved successfully');
} catch (Exception $e) {
    Response::error('Database error: ' . $e->getMessage(), 500);
}

