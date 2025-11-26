<?php
/**
 * Shipping API Endpoint
 * GET /backend/api/shipping/index.php - Get shipping rates
 * POST /backend/api/shipping/index.php - Create shipping label
 * PUT /backend/api/shipping/index.php?tracking={number} - Track shipment
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/ShippingProvider.php';
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

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            isset($_GET['tracking']) ? trackShipment() : getShippingRates();
            break;

        case 'POST':
            createShippingLabel();
            break;

        case 'PUT':
            trackShipment();
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Shipping operation failed: ' . $e->getMessage());
}

function getShippingRates() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_GET;
    }

    // Validate required fields
    $required = ['from_address', 'to_address', 'package_details'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            Response::error("{$field} is required", 400);
        }
    }

    $fromAddress = $input['from_address'];
    $toAddress = $input['to_address'];
    $packageDetails = $input['package_details'];
    $provider = $input['provider'] ?? 'fedex'; // Default to FedEx

    // Initialize shipping provider
    $shippingProvider = new ShippingProvider($provider, true); // true for test mode

    // Calculate rates
    $result = $shippingProvider->calculateRates($fromAddress, $toAddress, $packageDetails);

    if (!$result['success']) {
        Response::error($result['error'], 400);
    }

    Response::success([
        'rates' => $result['services'],
        'provider' => $result['provider'],
        'from_address' => $fromAddress,
        'to_address' => $toAddress,
        'package_details' => $packageDetails
    ]);
}

function createShippingLabel() {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    $required = ['shipment_details', 'provider'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            Response::error("{$field} is required", 400);
        }
    }

    $shipmentDetails = $input['shipment_details'];
    $provider = $input['provider'];

    // Initialize shipping provider
    $shippingProvider = new ShippingProvider($provider, true);

    if (!$shippingProvider->isConfigured()) {
        Response::error('Shipping provider not configured', 400);
    }

    // Create label
    $result = $shippingProvider->createLabel($shipmentDetails);

    if (!$result['success']) {
        Response::error($result['error'], 400);
    }

    // Log shipping activity
    $logger = new Logger($user['id']);
    $logger->log('shipping_label_created', 'shipping', null, [
        'tracking_number' => $result['tracking_number'],
        'provider' => $result['provider'],
        'service_type' => $result['service_type']
    ]);

    Response::success([
        'label' => $result,
        'shipment_details' => $shipmentDetails
    ], 201);
}

function trackShipment() {
    $trackingNumber = isset($_GET['tracking']) ? $_GET['tracking'] : '';

    if (empty($trackingNumber)) {
        Response::error('Tracking number is required', 400);
    }

    // Determine provider from tracking number format
    $provider = determineProviderFromTracking($trackingNumber);

    // Initialize shipping provider
    $shippingProvider = new ShippingProvider($provider, true);

    // Track shipment
    $result = $shippingProvider->trackShipment($trackingNumber);

    if (!$result['success']) {
        Response::error($result['error'], 400);
    }

    Response::success([
        'tracking' => $result
    ]);
}

function determineProviderFromTracking($trackingNumber) {
    // Determine shipping provider based on tracking number format
    if (preg_match('/^FEDEX/i', $trackingNumber)) {
        return 'fedex';
    } elseif (preg_match('/^1Z/i', $trackingNumber)) {
        return 'ups';
    } elseif (preg_match('/^9[0-9]{3}/', $trackingNumber)) {
        return 'usps';
    } else {
        return 'fallback';
    }
}
