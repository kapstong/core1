<?php
/**
 * Product Reviews API Endpoint
 * POST /backend/api/shop/reviews.php - Create review
 * GET /backend/api/shop/reviews.php - Get reviews for product
 *
 * Actions:
 * - create: Create new review
 * - list: Get reviews for product
 * - helpful: Mark review as helpful
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../models/Product.php';

CORS::handle();

// Start session for customer authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            handlePostRequest($action);
            break;

        case 'GET':
            handleGetRequest($action);
            break;

        case 'PUT':
            handlePutRequest($action);
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Review error: ' . $e->getMessage());
}

function handlePostRequest($action) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    switch ($action) {
        case 'create':
            createReview($input);
            break;

        case 'helpful':
            markHelpful($input);
            break;

        default:
            Response::error('Invalid action. Supported actions: create, helpful', 400);
    }
}

function handleGetRequest($action) {
    switch ($action) {
        case 'list':
            getProductReviews();
            break;

        default:
            Response::error('Invalid action. Supported actions: list', 400);
    }
}

function handlePutRequest($action) {
    switch ($action) {
        case 'helpful':
            $input = json_decode(file_get_contents('php://input'), true);
            markHelpful($input);
            break;

        default:
            Response::error('Invalid action for PUT method', 400);
    }
}

function createReview($data) {
    // Check if customer is authenticated
    if (!isset($_SESSION['customer_id'])) {
        Response::error('Authentication required', 401);
    }

    $customerId = $_SESSION['customer_id'];

    // Validate required fields
    $required = ['product_id', 'rating', 'review_text'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            Response::error("{$field} is required", 400);
        }
    }

    // Validate rating
    $rating = intval($data['rating']);
    if ($rating < 1 || $rating > 5) {
        Response::error('Rating must be between 1 and 5', 400);
    }

    $productId = intval($data['product_id']);

    // Check if product exists
    $productModel = new Product();
    $product = $productModel->findById($productId);
    if (!$product) {
        Response::error('Product not found', 404);
    }

    // Check if customer has already reviewed this product
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND customer_id = ?");
    $stmt->execute([$productId, $customerId]);
    if ($stmt->fetch()) {
        Response::error('You have already reviewed this product', 409);
    }

    // Check if customer has purchased this product (for verified purchase)
    $isVerifiedPurchase = false;
    $stmt = $db->prepare("
        SELECT coi.id FROM customer_order_items coi
        JOIN customer_orders co ON coi.order_id = co.id
        WHERE co.customer_id = ? AND coi.product_id = ? AND co.status IN ('delivered', 'shipped')
        LIMIT 1
    ");
    $stmt->execute([$customerId, $productId]);
    if ($stmt->fetch()) {
        $isVerifiedPurchase = true;
    }

    // Insert review
    $stmt = $db->prepare("
        INSERT INTO product_reviews (
            product_id, customer_id, rating, title, review_text,
            is_verified_purchase, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    $title = isset($data['title']) ? trim($data['title']) : null;
    $reviewText = trim($data['review_text']);

    if (!$stmt->execute([$productId, $customerId, $rating, $title, $reviewText, $isVerifiedPurchase])) {
        Response::error('Failed to create review', 500);
    }

    $reviewId = $db->lastInsertId();

    Response::success([
        'review_id' => $reviewId,
        'message' => 'Review submitted successfully and pending approval'
    ], 'Review created', 201);
}

function getProductReviews() {
    $productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
    if (!$productId) {
        Response::error('product_id parameter is required', 400);
    }

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;

    $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
    $sortOptions = [
        'newest' => 'pr.created_at DESC',
        'oldest' => 'pr.created_at ASC',
        'highest_rating' => 'pr.rating DESC',
        'lowest_rating' => 'pr.rating ASC',
        'most_helpful' => 'pr.helpful_votes DESC'
    ];
    $orderBy = $sortOptions[$sortBy] ?? $sortOptions['newest'];

    $db = Database::getInstance()->getConnection();

    // Get reviews with customer info
    $stmt = $db->prepare("
        SELECT
            pr.id,
            pr.rating,
            pr.title,
            pr.review_text,
            pr.is_verified_purchase,
            pr.helpful_votes,
            pr.status,
            pr.created_at,
            c.first_name,
            c.last_name,
            c.email
        FROM product_reviews pr
        JOIN customers c ON pr.customer_id = c.id
        WHERE pr.product_id = ? AND pr.status = 'approved'
        ORDER BY {$orderBy}
        LIMIT ? OFFSET ?
    ");

    $stmt->execute([$productId, $limit, $offset]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM product_reviews
        WHERE product_id = ? AND status = 'approved'
    ");
    $countStmt->execute([$productId]);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Calculate rating statistics
    $statsStmt = $db->prepare("
        SELECT
            COUNT(*) as total_reviews,
            AVG(rating) as average_rating,
            SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
            SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
        FROM product_reviews
        WHERE product_id = ? AND status = 'approved'
    ");
    $statsStmt->execute([$productId]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Format reviews
    foreach ($reviews as &$review) {
        $review['customer_name'] = $review['first_name'] . ' ' . substr($review['last_name'], 0, 1) . '.';
        $review['created_at_formatted'] = date('M j, Y', strtotime($review['created_at']));

        // Remove sensitive data
        unset($review['first_name'], $review['last_name'], $review['email']);
    }

    Response::success([
        'reviews' => $reviews,
        'stats' => [
            'total_reviews' => (int)$stats['total_reviews'],
            'average_rating' => round((float)$stats['average_rating'], 1),
            'rating_distribution' => [
                5 => (int)$stats['five_star'],
                4 => (int)$stats['four_star'],
                3 => (int)$stats['three_star'],
                2 => (int)$stats['two_star'],
                1 => (int)$stats['one_star']
            ]
        ],
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'total_pages' => ceil($total / $limit)
        ]
    ]);
}

function markHelpful($data) {
    // Check if customer is authenticated
    if (!isset($_SESSION['customer_id'])) {
        Response::error('Authentication required', 401);
    }

    $customerId = $_SESSION['customer_id'];

    // Validate required fields
    if (empty($data['review_id'])) {
        Response::error('review_id is required', 400);
    }

    $reviewId = intval($data['review_id']);
    $voteType = isset($data['vote_type']) ? $data['vote_type'] : 'helpful';

    if (!in_array($voteType, ['helpful', 'not_helpful'])) {
        Response::error('Invalid vote type', 400);
    }

    $db = Database::getInstance()->getConnection();

    // Check if review exists and is approved
    $stmt = $db->prepare("SELECT id FROM product_reviews WHERE id = ? AND status = 'approved'");
    $stmt->execute([$reviewId]);
    if (!$stmt->fetch()) {
        Response::error('Review not found', 404);
    }

    // Check if customer already voted on this review
    $stmt = $db->prepare("SELECT id, vote_type FROM review_helpful_votes WHERE review_id = ? AND customer_id = ?");
    $stmt->execute([$reviewId, $customerId]);
    $existingVote = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingVote) {
        if ($existingVote['vote_type'] === $voteType) {
            // Same vote, remove it
            $stmt = $db->prepare("DELETE FROM review_helpful_votes WHERE id = ?");
            $stmt->execute([$existingVote['id']]);

            // Decrease helpful count if it was a helpful vote
            if ($voteType === 'helpful') {
                $stmt = $db->prepare("UPDATE product_reviews SET helpful_votes = helpful_votes - 1 WHERE id = ?");
                $stmt->execute([$reviewId]);
            }

            Response::success(['message' => 'Vote removed']);
        } else {
            // Different vote, update it
            $stmt = $db->prepare("UPDATE review_helpful_votes SET vote_type = ? WHERE id = ?");
            $stmt->execute([$voteType, $existingVote['id']]);

            // Adjust helpful count
            if ($voteType === 'helpful') {
                $stmt = $db->prepare("UPDATE product_reviews SET helpful_votes = helpful_votes + 1 WHERE id = ?");
                $stmt->execute([$reviewId]);
            } else {
                $stmt = $db->prepare("UPDATE product_reviews SET helpful_votes = helpful_votes - 1 WHERE id = ?");
                $stmt->execute([$reviewId]);
            }

            Response::success(['message' => 'Vote updated']);
        }
    } else {
        // New vote
        $stmt = $db->prepare("INSERT INTO review_helpful_votes (review_id, customer_id, vote_type) VALUES (?, ?, ?)");
        $stmt->execute([$reviewId, $customerId, $voteType]);

        // Increase helpful count if it's a helpful vote
        if ($voteType === 'helpful') {
            $stmt = $db->prepare("UPDATE product_reviews SET helpful_votes = helpful_votes + 1 WHERE id = ?");
            $stmt->execute([$reviewId]);
        }

        Response::success(['message' => 'Vote recorded']);
    }
}
