<?php
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Logger.php';

header('Content-Type: application/json; charset=UTF-8');

CORS::handle();
Auth::requireAuth();

$user = Auth::user();
$allowedRoles = ['admin', 'staff', 'inventory_manager', 'purchasing_officer'];
if (!in_array((string)($user['role'] ?? ''), $allowedRoles, true)) {
    Response::error('Access denied', 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

function buildPosReservationSessionId($userId, $reservationId) {
    $reservationId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$reservationId);
    $reservationId = substr($reservationId, 0, 48);
    if ($reservationId === '') {
        return null;
    }
    return 'pos_' . $userId . '_' . $reservationId;
}

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        Response::error('Invalid JSON data', 400);
    }

    foreach (['items', 'payment_method'] as $field) {
        if (!isset($data[$field])) {
            Response::error("Missing required field: {$field}", 400);
        }
    }

    if (!is_array($data['items']) || empty($data['items'])) {
        Response::error('Items must be a non-empty array', 400);
    }

    $paymentMethod = strtolower(trim((string)$data['payment_method']));
    if ($paymentMethod === 'transfer') {
        $paymentMethod = 'bank_transfer';
    }

    $allowedPaymentMethods = ['cash', 'card', 'bank_transfer', 'digital_wallet'];
    if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
        Response::error('Invalid payment method', 400);
    }

    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();
    $posReservationSessionId = buildPosReservationSessionId((int)$user['id'], $data['pos_reservation_id'] ?? null);
    $reservationQuantities = [];
    if ($posReservationSessionId !== null) {
        $reservationStmt = $db->prepare("
            SELECT product_id, quantity
            FROM shopping_cart
            WHERE session_id = :session_id
        ");
        $reservationStmt->execute([':session_id' => $posReservationSessionId]);
        foreach ($reservationStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $reservationQuantities[(int)$row['product_id']] = (int)$row['quantity'];
        }
    }

    $customerName = trim((string)($data['customer_name'] ?? ''));
    if ($customerName === '') {
        $customerName = 'Walk-in Customer';
    }
    $customerEmail = null;
    $customerPhone = null;

    $customerId = isset($data['customer_id']) ? (int)$data['customer_id'] : 0;
    if ($customerId > 0) {
        $customerStmt = $db->prepare("
            SELECT first_name, last_name, email, phone
            FROM customers
            WHERE id = :id
            LIMIT 1
        ");
        $customerStmt->bindValue(':id', $customerId, PDO::PARAM_INT);
        $customerStmt->execute();
        $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
        if ($customer) {
            $fullName = trim(((string)($customer['first_name'] ?? '')) . ' ' . ((string)($customer['last_name'] ?? '')));
            if ($fullName !== '') {
                $customerName = $fullName;
            }
            $customerEmail = $customer['email'] ?? null;
            $customerPhone = $customer['phone'] ?? null;
        }
    }

    $resolvedItems = [];
    $calculatedSubtotal = 0.0;

    $productStmt = $db->prepare("
        SELECT
            p.id,
            p.name,
            p.selling_price,
            p.is_active,
            COALESCE(i.quantity_on_hand, 0) AS quantity_on_hand,
            COALESCE(i.quantity_reserved, 0) AS quantity_reserved,
            COALESCE(i.quantity_available, COALESCE(i.quantity_on_hand, 0) - COALESCE(i.quantity_reserved, 0), 0) AS quantity_available
        FROM products p
        LEFT JOIN inventory i ON i.product_id = p.id
        WHERE p.id = :product_id
        LIMIT 1
    ");

    foreach ($data['items'] as $item) {
        if (!is_array($item) || !isset($item['product_id']) || !isset($item['quantity'])) {
            throw new Exception('Each item must include product_id and quantity');
        }

        $productId = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];
        if ($productId <= 0 || $quantity <= 0) {
            throw new Exception('Invalid product_id or quantity');
        }

        $productStmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $productStmt->execute();
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || (int)$product['is_active'] !== 1) {
            throw new Exception("Product {$productId} not found or inactive");
        }

        $available = (int)($product['quantity_available'] ?? 0);
        $ownReserved = (int)($reservationQuantities[$productId] ?? 0);
        $effectiveAvailable = $available + $ownReserved;
        if ($effectiveAvailable < $quantity) {
            throw new Exception(
                "Insufficient stock for {$product['name']}. Available: {$effectiveAvailable}, requested: {$quantity}"
            );
        }

        $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : (float)$product['selling_price'];
        $lineTotal = isset($item['total_price']) ? (float)$item['total_price'] : ($unitPrice * $quantity);
        $calculatedSubtotal += $lineTotal;

        $resolvedItems[] = [
            'product_id' => $productId,
            'name' => $product['name'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'quantity_before' => (int)$product['quantity_on_hand'],
            'quantity_after' => (int)$product['quantity_on_hand'] - $quantity,
            'quantity_reserved_before' => (int)$product['quantity_reserved'],
            'reserved_release' => min($ownReserved, $quantity),
        ];
    }

    $subtotal = isset($data['subtotal']) ? (float)$data['subtotal'] : $calculatedSubtotal;
    $taxAmount = isset($data['tax_amount']) ? (float)$data['tax_amount'] : round($subtotal * 0.12, 2);
    $discountAmount = isset($data['discount_amount']) ? (float)$data['discount_amount'] : 0.0;
    $totalAmount = isset($data['total_amount'])
        ? (float)$data['total_amount']
        : ($subtotal + $taxAmount - $discountAmount);

    $transactionNumber = generateUniqueTransactionNumber($db);

    $saleStmt = $db->prepare("
        INSERT INTO sales (
            invoice_number,
            cashier_id,
            customer_name,
            customer_email,
            customer_phone,
            subtotal,
            tax_amount,
            tax_rate,
            discount_amount,
            total_amount,
            payment_method,
            payment_status,
            notes,
            created_at,
            updated_at
        ) VALUES (
            :invoice_number,
            :cashier_id,
            :customer_name,
            :customer_email,
            :customer_phone,
            :subtotal,
            :tax_amount,
            :tax_rate,
            :discount_amount,
            :total_amount,
            :payment_method,
            'paid',
            :notes,
            NOW(),
            NOW()
        )
    ");

    $saleStmt->bindValue(':invoice_number', $transactionNumber);
    $saleStmt->bindValue(':cashier_id', (int)$user['id'], PDO::PARAM_INT);
    $saleStmt->bindValue(':customer_name', $customerName);
    if ($customerEmail !== null && $customerEmail !== '') {
        $saleStmt->bindValue(':customer_email', $customerEmail);
    } else {
        $saleStmt->bindValue(':customer_email', null, PDO::PARAM_NULL);
    }
    if ($customerPhone !== null && $customerPhone !== '') {
        $saleStmt->bindValue(':customer_phone', $customerPhone);
    } else {
        $saleStmt->bindValue(':customer_phone', null, PDO::PARAM_NULL);
    }
    $saleStmt->bindValue(':subtotal', $subtotal);
    $saleStmt->bindValue(':tax_amount', $taxAmount);
    $saleStmt->bindValue(':tax_rate', $subtotal > 0 ? ($taxAmount / $subtotal) : 0);
    $saleStmt->bindValue(':discount_amount', $discountAmount);
    $saleStmt->bindValue(':total_amount', $totalAmount);
    $saleStmt->bindValue(':payment_method', $paymentMethod);
    $saleStmt->bindValue(':notes', isset($data['notes']) ? (string)$data['notes'] : null, isset($data['notes']) ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $saleStmt->execute();

    $saleId = (int)$db->lastInsertId();

    $itemStmt = $db->prepare("
        INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, created_at)
        VALUES (:sale_id, :product_id, :quantity, :unit_price, NOW())
    ");
    $inventoryStmt = $db->prepare("
        UPDATE inventory
        SET quantity_on_hand = :quantity_on_hand,
            quantity_reserved = :quantity_reserved,
            quantity_available = :quantity_available
        WHERE product_id = :product_id
    ");
    $movementStmt = $db->prepare("
        INSERT INTO stock_movements (
            product_id,
            movement_type,
            quantity,
            quantity_before,
            quantity_after,
            reference_type,
            reference_id,
            performed_by,
            notes,
            created_at
        ) VALUES (
            :product_id,
            'sale',
            :quantity_negative,
            :quantity_before,
            :quantity_after,
            'SALE',
            :reference_id,
            :performed_by,
            :notes,
            NOW()
        )
    ");

    foreach ($resolvedItems as $resolvedItem) {
        $itemStmt->bindValue(':sale_id', $saleId, PDO::PARAM_INT);
        $itemStmt->bindValue(':product_id', $resolvedItem['product_id'], PDO::PARAM_INT);
        $itemStmt->bindValue(':quantity', $resolvedItem['quantity'], PDO::PARAM_INT);
        $itemStmt->bindValue(':unit_price', $resolvedItem['unit_price']);
        $itemStmt->execute();

        $newOnHand = max(0, (int)$resolvedItem['quantity_before'] - (int)$resolvedItem['quantity']);
        $newReserved = max(0, (int)$resolvedItem['quantity_reserved_before'] - (int)$resolvedItem['reserved_release']);
        $newAvailable = max(0, $newOnHand - $newReserved);

        $inventoryStmt->bindValue(':quantity_on_hand', $newOnHand, PDO::PARAM_INT);
        $inventoryStmt->bindValue(':quantity_reserved', $newReserved, PDO::PARAM_INT);
        $inventoryStmt->bindValue(':quantity_available', $newAvailable, PDO::PARAM_INT);
        $inventoryStmt->bindValue(':product_id', $resolvedItem['product_id'], PDO::PARAM_INT);
        $inventoryStmt->execute();

        $movementStmt->bindValue(':product_id', $resolvedItem['product_id'], PDO::PARAM_INT);
        $movementStmt->bindValue(':quantity_negative', -$resolvedItem['quantity'], PDO::PARAM_INT);
        $movementStmt->bindValue(':quantity_before', $resolvedItem['quantity_before'], PDO::PARAM_INT);
        $movementStmt->bindValue(':quantity_after', $resolvedItem['quantity_after'], PDO::PARAM_INT);
        $movementStmt->bindValue(':reference_id', $saleId, PDO::PARAM_INT);
        $movementStmt->bindValue(':performed_by', (int)$user['id'], PDO::PARAM_INT);
        $movementStmt->bindValue(':notes', 'POS transaction ' . $transactionNumber);
        $movementStmt->execute();
    }

    if ($posReservationSessionId !== null) {
        $clearReservationStmt = $db->prepare("DELETE FROM shopping_cart WHERE session_id = :session_id");
        $clearReservationStmt->execute([':session_id' => $posReservationSessionId]);
    }

    $db->commit();

    Logger::info('POS transaction created', [
        'transaction_id' => $saleId,
        'transaction_number' => $transactionNumber,
        'cashier_id' => (int)$user['id'],
        'total_amount' => $totalAmount,
        'item_count' => count($resolvedItems)
    ], (int)$user['id']);

    Response::success([
        'transaction_id' => $saleId,
        'sale_id' => $saleId,
        'transaction_number' => $transactionNumber,
        'total_amount' => $totalAmount,
        'item_count' => count($resolvedItems),
        'payment_method' => $paymentMethod
    ], 'POS transaction created successfully');
} catch (Exception $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }

    Logger::error('POS transaction error', [
        'error' => $e->getMessage(),
        'user_id' => isset($user['id']) ? (int)$user['id'] : null
    ], isset($user['id']) ? (int)$user['id'] : null);

    Response::error('Transaction failed: ' . $e->getMessage(), 500);
}

function generateUniqueTransactionNumber(PDO $db) {
    do {
        $number = 'POS-' . date('Ymd') . '-' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM sales WHERE invoice_number = :invoice_number");
        $checkStmt->bindValue(':invoice_number', $number);
        $checkStmt->execute();
        $exists = (int)$checkStmt->fetchColumn() > 0;
    } while ($exists);

    return $number;
}
