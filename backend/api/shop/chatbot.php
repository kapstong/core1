<?php
/**
 * Shop Chatbot API Endpoint
 * POST /backend/api/shop/chatbot.php - Generate chatbot replies for customer inquiries
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    Response::success([
        'name' => 'Shop Assistant Chatbot',
        'version' => '1.0',
        'capabilities' => [
            'faq_support',
            'product_search',
            'category_browsing',
            'checkout_guidance',
            'order_help'
        ]
    ], 'Chatbot API is ready');
}

if ($method !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $message = trim((string)($input['message'] ?? ''));
    $context = isset($input['context']) && is_array($input['context']) ? $input['context'] : [];

    if ($message === '') {
        Response::error('Message is required', 400);
    }

    if (strlen($message) > 600) {
        Response::error('Message is too long (max 600 characters)', 400);
    }

    $db = Database::getInstance()->getConnection();
    $customerId = isset($_SESSION['customer_id']) ? (int)$_SESSION['customer_id'] : null;

    $reply = buildChatbotReply($db, $message, $context, $customerId);

    Response::success($reply, 'Reply generated');
} catch (Exception $e) {
    Response::serverError('Chatbot error: ' . $e->getMessage());
}

function buildChatbotReply(PDO $db, string $message, array $context, ?int $customerId): array {
    $normalized = normalizeMessage($message);
    $intent = detectIntent($normalized);
    $productMatches = [];
    $links = [];
    $quickReplies = defaultQuickReplies();
    $replyText = '';
    $meta = [
        'intent' => $intent,
        'page' => isset($context['page']) ? (string)$context['page'] : null
    ];

    if ($intent === 'thanks') {
        $replyText = 'You\'re welcome! I can help with products, orders, checkout, shipping, returns, and payment questions.';
        $quickReplies = ['Find products', 'Shipping info', 'Return policy', 'Order help'];
    } elseif ($intent === 'greeting') {
        $replyText = 'Hi! I\'m the shop assistant. Ask me about products, stock availability, checkout, shipping, returns, or your orders.';
        $quickReplies = ['Browse categories', 'Find a product', 'Shipping info', 'Payment methods'];
        $links[] = navLink('Shop Home', 'index.php');
    } elseif ($intent === 'categories') {
        $categories = fetchCategorySummaries($db, 8);
        if (!empty($categories)) {
            $categoryNames = array_map(function ($category) {
                return $category['name'];
            }, $categories);
            $replyText = 'Available categories include: ' . implode(', ', $categoryNames) . '. You can ask me to find products in a category too.';
            foreach ($categories as $category) {
                $links[] = navLink($category['name'], 'index.php#categories');
            }
        } else {
            $replyText = 'I can help you browse categories. Open the Shop Home page and use the category filters to narrow products.';
            $links[] = navLink('Browse Categories', 'index.php#categories');
        }
        $quickReplies = ['Processors', 'Graphics cards', 'Storage', 'Cooling'];
    } elseif ($intent === 'shipping') {
        $replyText = 'We support delivery and in-store pickup. Shipping fees and delivery time depend on your location and selected shipping option during checkout.';
        $quickReplies = ['Checkout help', 'Payment methods', 'Track my order', 'Return policy'];
        $links[] = navLink('Go to Checkout', 'checkout.php');
        $links[] = navLink('My Orders', 'orders.php');
    } elseif ($intent === 'payment') {
        $replyText = 'Common payment options in the shop include cash (for pickup/COD where applicable), cards, bank transfer, and supported digital wallets. Available methods are shown during checkout.';
        $quickReplies = ['Checkout help', 'Shipping info', 'Is my order confirmed?', 'Return policy'];
        $links[] = navLink('Checkout', 'checkout.php');
    } elseif ($intent === 'returns') {
        $replyText = 'For returns/refunds, please check order eligibility and request a return from your orders page. Keep the item in good condition and include proof of purchase if requested.';
        $quickReplies = ['Warranty info', 'Order help', 'Contact support', 'My orders'];
        $links[] = navLink('My Orders', 'orders.php');
    } elseif ($intent === 'warranty') {
        $replyText = 'Most products include manufacturer warranty. Warranty coverage varies by item, so check the product page details and your order receipt.';
        $quickReplies = ['Return policy', 'Find products', 'Order details', 'Contact support'];
        $links[] = navLink('Browse Products', 'index.php#products');
    } elseif ($intent === 'cart') {
        $replyText = 'You can review items, update quantities, or remove products in your cart. When ready, click Proceed to Checkout.';
        $quickReplies = ['Go to cart', 'Checkout help', 'Find products', 'Payment methods'];
        $links[] = navLink('Cart', 'cart.php');
        $links[] = navLink('Checkout', 'checkout.php');
    } elseif ($intent === 'checkout') {
        $replyText = 'At checkout, fill in billing/shipping details, choose a payment method, review the order summary, then place your order. I can also help explain shipping or payment options.';
        $quickReplies = ['Shipping info', 'Payment methods', 'Order help', 'Cart'];
        $links[] = navLink('Checkout', 'checkout.php');
        $links[] = navLink('Cart', 'cart.php');
    } elseif ($intent === 'auth') {
        $replyText = 'For account access, you can log in, register, or reset your password from the customer account pages. If you already ordered, use the same email to track your order history.';
        $quickReplies = ['Login', 'Register', 'Reset password', 'My orders'];
        $links[] = navLink('Customer Login', 'login.php');
        $links[] = navLink('Register', 'register.php');
        $links[] = navLink('Reset Password', 'reset-password.php');
    } elseif ($intent === 'orders') {
        $orderSummary = $customerId ? fetchCustomerOrderSummary($db, $customerId) : null;
        if ($orderSummary && !empty($orderSummary['recent_orders'])) {
            $recentLines = array_map(function ($order) {
                return $order['order_number'] . ' (' . ucfirst((string)$order['status']) . ')';
            }, $orderSummary['recent_orders']);
            $replyText = 'You can manage your orders from the My Orders page. I found your recent orders: ' . implode(', ', $recentLines) . '.';
            if (!empty($orderSummary['latest_order_number'])) {
                $replyText .= ' Open order details to view items, status updates, and available actions.';
            }
            $meta['order_summary'] = [
                'total_orders' => $orderSummary['total_orders'],
                'latest_order_number' => $orderSummary['latest_order_number']
            ];
        } elseif ($customerId) {
            $replyText = 'You\'re logged in. Open My Orders to view current and past purchases, check statuses, or request supported actions.';
        } else {
            $replyText = 'To check order status, log in to your customer account and open My Orders. You can view order details, status, and supported actions there.';
            $links[] = navLink('Customer Login', 'login.php');
        }
        $quickReplies = ['My orders', 'Order details', 'Cancel an order', 'Return policy'];
        $links[] = navLink('My Orders', 'orders.php');
    } elseif ($intent === 'contact') {
        $replyText = 'For account-specific or urgent concerns, please contact the shop team directly. You can also use the help/FAQ sections while browsing products and orders.';
        $quickReplies = ['Order help', 'Return policy', 'Shipping info', 'Find products'];
        $links[] = navLink('Shop Home', 'index.php');
        $links[] = navLink('My Profile', 'profile.php');
    } elseif ($intent === 'compatibility') {
        $replyText = 'I can help you find parts, but compatibility should still be verified (socket, motherboard chipset, RAM type/speed, PSU wattage, case clearance, and GPU length). Tell me the component you need and your budget.';
        $quickReplies = ['Find CPU', 'Find GPU', 'Find motherboard', 'Find RAM'];
        $links[] = navLink('Browse Products', 'index.php#products');
    } else {
        $searchQuery = extractProductSearchTerm($message, $normalized);
        $budget = extractBudgetValue($normalized);
        $productMatches = searchProductsForChatbot($db, $searchQuery, 5, $budget);

        if (!empty($productMatches)) {
            $replyText = 'I found some products that match';
            if ($searchQuery !== '') {
                $replyText .= ' "' . $searchQuery . '"';
            }
            if ($budget !== null) {
                $replyText .= ' under ' . formatCurrency($budget);
            }
            $replyText .= '.';

            $links[] = navLink('Browse More Products', 'index.php#products');
            $quickReplies = ['Show in-stock items', 'Browse categories', 'Cart', 'Checkout'];
            $meta['budget'] = $budget;
        } else {
            if ($searchQuery !== '') {
                $replyText = 'I couldn\'t find a direct product match for "' . $searchQuery . '". Try a broader keyword (brand, category, or part type), like "RTX", "Ryzen", "SSD", or "motherboard".';
            } else {
                $replyText = 'I can help with product search, stock questions, checkout, shipping, returns, payments, and order help. Try asking: "Find SSD under 5000" or "What are your payment methods?"';
            }
            $quickReplies = ['Find SSD', 'Browse categories', 'Payment methods', 'Shipping info'];
            $links[] = navLink('Shop Home', 'index.php');
        }

        if (containsAny($normalized, ['in stock', 'available stock', 'available'])) {
            $meta['availability_query'] = true;
        }
    }

    return [
        'reply' => $replyText,
        'quick_replies' => array_values(array_unique($quickReplies)),
        'suggested_links' => dedupeLinks($links),
        'product_matches' => $productMatches,
        'meta' => $meta
    ];
}

function detectIntent(string $normalized): string {
    if ($normalized === '') {
        return 'fallback';
    }

    if (containsAny($normalized, ['thank you', 'thanks', 'salamat'])) {
        return 'thanks';
    }

    if (containsAny($normalized, ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'])) {
        return 'greeting';
    }

    if (containsAny($normalized, ['category', 'categories', 'catalog'])) {
        return 'categories';
    }

    if (containsAny($normalized, ['shipping', 'delivery', 'pickup', 'ship'])) {
        return 'shipping';
    }

    if (containsAny($normalized, ['payment', 'pay', 'gcash', 'cash', 'card', 'wallet', 'bank transfer'])) {
        return 'payment';
    }

    if (containsAny($normalized, ['return', 'refund', 'replace', 'replacement'])) {
        return 'returns';
    }

    if (containsAny($normalized, ['warranty', 'guarantee'])) {
        return 'warranty';
    }

    if (containsAny($normalized, ['cart', 'add to cart', 'remove from cart'])) {
        return 'cart';
    }

    if (containsAny($normalized, ['checkout', 'place order', 'order now', 'billing', 'shipping address'])) {
        return 'checkout';
    }

    if (containsAny($normalized, ['login', 'log in', 'register', 'sign up', 'password reset', 'forgot password', 'account'])) {
        return 'auth';
    }

    if (containsAny($normalized, ['my order', 'order status', 'track order', 'orders', 'cancel order'])) {
        return 'orders';
    }

    if (containsAny($normalized, ['contact', 'support', 'agent', 'staff', 'human'])) {
        return 'contact';
    }

    if (containsAny($normalized, ['compatible', 'compatibility', 'build', 'fit', 'bottleneck'])) {
        return 'compatibility';
    }

    if (containsAny($normalized, ['find', 'search', 'looking for', 'show me', 'recommend', 'suggest'])) {
        return 'product_search';
    }

    return 'fallback';
}

function normalizeMessage(string $message): string {
    $message = strtolower(trim($message));
    $message = preg_replace('/\s+/', ' ', $message);
    return $message ?? '';
}

function containsAny(string $text, array $needles): bool {
    foreach ($needles as $needle) {
        $normalizedNeedle = normalizeMessage((string)$needle);
        if ($normalizedNeedle === '') {
            continue;
        }

        $pattern = '/\b' . preg_replace('/\s+/', '\\s+', preg_quote($normalizedNeedle, '/')) . '\b/i';
        if (preg_match($pattern, $text)) {
            return true;
        }
    }
    return false;
}

function defaultQuickReplies(): array {
    return [
        'Browse categories',
        'Find a product',
        'Shipping info',
        'Payment methods'
    ];
}

function navLink(string $label, string $url): array {
    return [
        'label' => $label,
        'url' => $url
    ];
}

function dedupeLinks(array $links): array {
    $seen = [];
    $deduped = [];

    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $label = trim((string)($link['label'] ?? ''));
        $url = trim((string)($link['url'] ?? ''));
        if ($label === '' || $url === '') {
            continue;
        }

        $key = strtolower($label . '|' . $url);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $deduped[] = ['label' => $label, 'url' => $url];
    }

    return $deduped;
}

function fetchCategorySummaries(PDO $db, int $limit = 8): array {
    $sql = "
        SELECT
            c.id,
            c.name,
            COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p
            ON p.category_id = c.id
           AND p.is_active = 1
           AND p.deleted_at IS NULL
        WHERE c.is_active = 1
          AND c.deleted_at IS NULL
        GROUP BY c.id, c.name
        ORDER BY product_count DESC, c.name ASC
        LIMIT :limit
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', max(1, min(20, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(function ($row) {
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'product_count' => (int)$row['product_count']
        ];
    }, $rows);
}

function extractProductSearchTerm(string $originalMessage, string $normalizedMessage): string {
    $term = trim($originalMessage);

    $patterns = [
        '/(?:find|search|show me|looking for|need|recommend|suggest)\s+(?:an?|some)?\s*(.+)$/i',
        '/(?:products?|items?)\s+(?:for|with|under)\s+(.+)$/i'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $originalMessage, $matches)) {
            $term = trim($matches[1]);
            break;
        }
    }

    $term = preg_replace('/\b(in stock|available|please|thanks|thank you)\b/i', '', $term);
    $term = preg_replace('/\bunder\s+[₱$]?\s*[0-9][0-9,]*(?:\.[0-9]{1,2})?\b/i', '', $term);
    $term = preg_replace('/\s+/', ' ', $term);
    $term = trim((string)$term);

    if ($term === '' && containsAny($normalizedMessage, ['cpu', 'processor'])) {
        return 'cpu';
    }
    if ($term === '' && containsAny($normalizedMessage, ['gpu', 'graphics card'])) {
        return 'gpu';
    }
    if ($term === '' && containsAny($normalizedMessage, ['ssd', 'storage'])) {
        return 'ssd';
    }

    return $term;
}

function extractBudgetValue(string $normalized): ?float {
    $patterns = [
        '/(?:under|below|less than|budget(?: is)?|max(?:imum)?)\s*[₱$]?\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)/i',
        '/[₱$]\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)/'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $normalized, $matches)) {
            $value = (float)str_replace(',', '', $matches[1]);
            if ($value > 0) {
                return $value;
            }
        }
    }

    return null;
}

function searchProductsForChatbot(PDO $db, string $query, int $limit = 5, ?float $budget = null): array {
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $sql = "
        SELECT
            p.id,
            p.name,
            p.brand,
            p.sku,
            p.selling_price,
            p.image_url,
            c.name AS category_name,
            COALESCE(i.quantity_available, 0) AS quantity_available
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        LEFT JOIN inventory i ON i.product_id = p.id
        WHERE p.is_active = 1
          AND p.deleted_at IS NULL
          AND c.is_active = 1
          AND (
              p.name LIKE :search
              OR COALESCE(p.description, '') LIKE :search
              OR COALESCE(p.brand, '') LIKE :search
              OR c.name LIKE :search
              OR COALESCE(p.sku, '') LIKE :search
          )
    ";

    if ($budget !== null) {
        $sql .= " AND p.selling_price <= :budget ";
    }

    $sql .= "
        ORDER BY
            CASE WHEN p.name LIKE :prefix_search THEN 0 ELSE 1 END,
            CASE WHEN COALESCE(i.quantity_available, 0) > 0 THEN 0 ELSE 1 END,
            p.selling_price ASC
        LIMIT :limit
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':search', '%' . $query . '%');
    $stmt->bindValue(':prefix_search', $query . '%');
    if ($budget !== null) {
        $stmt->bindValue(':budget', $budget);
    }
    $stmt->bindValue(':limit', max(1, min(10, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(function ($row) {
        $qty = (int)$row['quantity_available'];
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'brand' => $row['brand'] ? (string)$row['brand'] : null,
            'sku' => $row['sku'] ? (string)$row['sku'] : null,
            'category' => (string)$row['category_name'],
            'price' => (float)$row['selling_price'],
            'price_formatted' => formatCurrency((float)$row['selling_price']),
            'in_stock' => $qty > 0,
            'quantity_available' => $qty,
            'url' => 'product.php?id=' . (int)$row['id']
        ];
    }, $rows);
}

function fetchCustomerOrderSummary(PDO $db, int $customerId): ?array {
    try {
        $totalStmt = $db->prepare("
            SELECT COUNT(*) AS total_orders
            FROM customer_orders
            WHERE customer_id = :customer_id
        ");
        $totalStmt->execute([':customer_id' => $customerId]);
        $totalOrders = (int)$totalStmt->fetchColumn();

        $recentStmt = $db->prepare("
            SELECT order_number, status, order_date, total_amount
            FROM customer_orders
            WHERE customer_id = :customer_id
            ORDER BY order_date DESC, id DESC
            LIMIT 3
        ");
        $recentStmt->execute([':customer_id' => $customerId]);
        $recentRows = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        $recentOrders = array_map(function ($row) {
            return [
                'order_number' => (string)$row['order_number'],
                'status' => (string)$row['status'],
                'order_date' => (string)$row['order_date'],
                'total_amount' => isset($row['total_amount']) ? (float)$row['total_amount'] : null
            ];
        }, $recentRows);

        return [
            'total_orders' => $totalOrders,
            'latest_order_number' => !empty($recentOrders) ? $recentOrders[0]['order_number'] : null,
            'recent_orders' => $recentOrders
        ];
    } catch (Exception $e) {
        error_log('Chatbot order summary failed: ' . $e->getMessage());
        return null;
    }
}

function formatCurrency(float $amount): string {
    return '₱' . number_format($amount, 2);
}
