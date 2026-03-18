<?php
/**
 * POS Reservation Cart API
 * GET/PUT/DELETE /backend/api/pos/cart.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../config/database.php';

CORS::handle();
Auth::requireAuth();

$user = Auth::user();
$allowedRoles = ['admin', 'staff', 'inventory_manager', 'purchasing_officer'];
if (!in_array((string)($user['role'] ?? ''), $allowedRoles, true)) {
    Response::error('Access denied', 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$database = Database::getInstance();
$conn = $database->getConnection();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $reservationId = trim((string)($input['reservation_id'] ?? $_GET['reservation_id'] ?? ''));
    if ($reservationId === '') {
        Response::error('reservation_id is required', 400);
    }

    $sessionId = buildPosSessionId((int)$user['id'], $reservationId);

    switch ($method) {
        case 'GET':
            Response::success([
                'items' => getCartItems($database, $conn, $sessionId),
                'reservation_id' => $reservationId,
            ]);
            break;

        case 'PUT':
            $productId = (int)($input['product_id'] ?? 0);
            $quantity = (int)($input['quantity'] ?? -1);
            if ($productId <= 0 || $quantity < 0) {
                Response::error('product_id and a non-negative quantity are required', 400);
            }

            setReservedQuantity($database, $conn, $sessionId, $productId, $quantity);

            Response::success([
                'items' => getCartItems($database, $conn, $sessionId),
                'reservation_id' => $reservationId,
            ], 'POS cart updated successfully');
            break;

        case 'DELETE':
            $clearAll = filter_var($input['clear'] ?? $_GET['clear'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($clearAll) {
                clearReservationCart($conn, $sessionId);
            } else {
                $productId = (int)($input['product_id'] ?? $_GET['product_id'] ?? 0);
                if ($productId <= 0) {
                    Response::error('product_id is required when clear is false', 400);
                }
                setReservedQuantity($database, $conn, $sessionId, $productId, 0);
            }

            Response::success([
                'items' => getCartItems($database, $conn, $sessionId),
                'reservation_id' => $reservationId,
            ], 'POS cart cleared successfully');
            break;

        default:
            Response::error('Method not allowed', 405);
    }
} catch (Throwable $e) {
    error_log('POS cart error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
    Response::error('POS cart failed: ' . $e->getMessage(), 500);
}

function buildPosSessionId($userId, $reservationId) {
    $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $reservationId);
    $sanitized = substr($sanitized, 0, 48);
    if ($sanitized === '') {
        throw new InvalidArgumentException('Invalid reservation_id');
    }

    return 'pos_' . $userId . '_' . $sanitized;
}

function getCartItems(Database $database, PDO $conn, $sessionId) {
    $productWhere = $database->columnExists('products', 'deleted_at')
        ? 'AND p.deleted_at IS NULL'
        : '';

    $stmt = $conn->prepare("
        SELECT
            sc.product_id,
            sc.quantity,
            p.name,
            p.sku,
            p.selling_price,
            p.image_url,
            COALESCE(i.quantity_available, COALESCE(i.quantity_on_hand, 0) - COALESCE(i.quantity_reserved, 0), 0) AS quantity_available
        FROM shopping_cart sc
        INNER JOIN products p ON p.id = sc.product_id {$productWhere}
        LEFT JOIN inventory i ON i.product_id = sc.product_id
        WHERE sc.session_id = :session_id
        ORDER BY sc.created_at DESC
    ");
    $stmt->execute([':session_id' => $sessionId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(static function ($row) {
        return [
            'id' => (int)$row['product_id'],
            'product_id' => (int)$row['product_id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'price' => (float)$row['selling_price'],
            'qty' => (int)$row['quantity'],
            'image_url' => $row['image_url'] ?? null,
            'quantity_available' => (int)($row['quantity_available'] ?? 0),
        ];
    }, $rows);
}

function setReservedQuantity(Database $database, PDO $conn, $sessionId, $productId, $newQuantity) {
    $productWhere = $database->columnExists('products', 'deleted_at')
        ? 'AND p.deleted_at IS NULL'
        : '';

    $productStmt = $conn->prepare("
        SELECT
            p.id,
            p.name,
            p.is_active,
            COALESCE(i.quantity_on_hand, 0) AS quantity_on_hand,
            COALESCE(i.quantity_reserved, 0) AS quantity_reserved,
            COALESCE(i.quantity_available, COALESCE(i.quantity_on_hand, 0) - COALESCE(i.quantity_reserved, 0), 0) AS quantity_available
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id
        WHERE p.id = :product_id {$productWhere}
        LIMIT 1
    ");
    $productStmt->execute([':product_id' => $productId]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product || (int)($product['is_active'] ?? 0) !== 1) {
        throw new RuntimeException('Product not found or inactive');
    }

    $currentQty = getCurrentSessionQuantity($conn, $sessionId, $productId);
    $quantityAvailable = (int)($product['quantity_available'] ?? 0);
    $effectiveAvailable = $quantityAvailable + $currentQty;

    if ($newQuantity > $effectiveAvailable) {
        throw new RuntimeException(
            'Only ' . $effectiveAvailable . ' item(s) available for ' . $product['name']
        );
    }

    $delta = $newQuantity - $currentQty;
    if ($delta === 0) {
        return;
    }

    upsertCartRow($conn, $sessionId, $productId, $newQuantity);

    $totalReserved = (int)($product['quantity_reserved'] ?? 0);
    $newReservedTotal = max(0, $totalReserved + $delta);

    $inventoryStmt = $conn->prepare("
        UPDATE inventory
        SET quantity_reserved = :quantity_reserved
        WHERE product_id = :product_id
    ");
    $inventoryStmt->execute([
        ':quantity_reserved' => $newReservedTotal,
        ':product_id' => $productId,
    ]);
}

function getCurrentSessionQuantity(PDO $conn, $sessionId, $productId) {
    $stmt = $conn->prepare("
        SELECT quantity
        FROM shopping_cart
        WHERE session_id = :session_id
          AND product_id = :product_id
        LIMIT 1
    ");
    $stmt->execute([
        ':session_id' => $sessionId,
        ':product_id' => $productId,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['quantity'] : 0;
}

function upsertCartRow(PDO $conn, $sessionId, $productId, $newQuantity) {
    $existingStmt = $conn->prepare("
        SELECT id
        FROM shopping_cart
        WHERE session_id = :session_id
          AND product_id = :product_id
        LIMIT 1
    ");
    $existingStmt->execute([
        ':session_id' => $sessionId,
        ':product_id' => $productId,
    ]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($newQuantity <= 0) {
        $deleteStmt = $conn->prepare("
            DELETE FROM shopping_cart
            WHERE session_id = :session_id
              AND product_id = :product_id
        ");
        $deleteStmt->execute([
            ':session_id' => $sessionId,
            ':product_id' => $productId,
        ]);
        return;
    }

    if ($existing) {
        $updateStmt = $conn->prepare("
            UPDATE shopping_cart
            SET quantity = :quantity,
                updated_at = NOW()
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':quantity' => $newQuantity,
            ':id' => (int)$existing['id'],
        ]);
        return;
    }

    $insertStmt = $conn->prepare("
        INSERT INTO shopping_cart (customer_id, session_id, product_id, quantity)
        VALUES (NULL, :session_id, :product_id, :quantity)
    ");
    $insertStmt->execute([
        ':session_id' => $sessionId,
        ':product_id' => $productId,
        ':quantity' => $newQuantity,
    ]);
}

function clearReservationCart(PDO $conn, $sessionId) {
    $rowsStmt = $conn->prepare("
        SELECT
            sc.product_id,
            sc.quantity,
            COALESCE(i.quantity_on_hand, 0) AS quantity_on_hand,
            COALESCE(i.quantity_reserved, 0) AS quantity_reserved
        FROM shopping_cart sc
        LEFT JOIN inventory i ON i.product_id = sc.product_id
        WHERE sc.session_id = :session_id
    ");
    $rowsStmt->execute([':session_id' => $sessionId]);
    $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($rows)) {
        $inventoryStmt = $conn->prepare("
            UPDATE inventory
            SET quantity_reserved = :quantity_reserved
            WHERE product_id = :product_id
        ");

        foreach ($rows as $row) {
            $quantityReserved = (int)($row['quantity_reserved'] ?? 0);
            $released = (int)($row['quantity'] ?? 0);
            $newReserved = max(0, $quantityReserved - $released);

            $inventoryStmt->execute([
                ':quantity_reserved' => $newReserved,
                ':product_id' => (int)$row['product_id'],
            ]);
        }
    }

    $deleteStmt = $conn->prepare("DELETE FROM shopping_cart WHERE session_id = :session_id");
    $deleteStmt->execute([':session_id' => $sessionId]);
}
