<?php
// Checkout Page - Customer Purchase Flow
session_start();

// Include maintenance mode check
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/middleware/MaintenanceMode.php';

// Check if maintenance mode is enabled and user is not admin
if (MaintenanceMode::handle()) {
    MaintenanceMode::renderMaintenancePage();
}

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - PC Parts Central</title>
    <link rel="icon" type="image/png" href="ppc.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #0066ff;
            --accent: #00f5ff;
            --bg-primary: #0a0e27;
            --bg-secondary: #1a1f3a;
            --bg-card: #1e293b;
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: rgba(148, 163, 184, 0.2);
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 50%, var(--bg-primary) 100%);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .navbar {
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
        }

        .navbar-brand {
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-link {
            color: var(--text-primary) !important;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--accent) !important;
        }

        .dropdown-item {
            color: var(--text-primary);
        }

        .dropdown-item:hover {
            background: rgba(0, 102, 255, 0.1);
            color: var(--accent);
        }

        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .checkout-header {
            text-align: center;
            padding: 3rem 0;
            margin-bottom: 3rem;
        }

        .checkout-title {
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .checkout-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .checkout-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 3rem;
            align-items: start;
        }

        .checkout-form {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 2.5rem;
        }

        .checkout-summary {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 2rem;
            position: sticky;
            top: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent);
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 245, 255, 0.1);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        .form-select {
            width: 100%;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .form-select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .payment-section {
            margin-top: 2rem;
        }

        .payment-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-option:hover {
            border-color: var(--accent);
            background: rgba(0, 245, 255, 0.05);
        }

        .payment-option.selected {
            border-color: var(--accent);
            background: rgba(0, 245, 255, 0.1);
        }

        .payment-radio {
            display: none;
        }

        .payment-radio:checked + .payment-option {
            border-color: var(--accent);
            background: rgba(0, 245, 255, 0.1);
        }

        .payment-icon {
            width: 40px;
            height: 40px;
            margin-right: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
        }

        .payment-cash {
            background: linear-gradient(135deg, #10b981, #34d399);
        }

        .payment-card {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
        }

        .payment-info {
            flex: 1;
        }

        .payment-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .payment-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .btn-place-order {
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            border-radius: 0.5rem;
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 2rem;
        }

        .btn-place-order:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 102, 255, 0.3);
        }

        .btn-place-order:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .btn-back:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-5px);
        }

        .order-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item-image {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .order-item-details {
            flex: 1;
        }

        .order-item-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .order-item-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .order-item-price {
            font-weight: 700;
            color: var(--accent);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-row:last-child {
            border-bottom: none;
            border-top: 2px solid var(--accent);
            margin-top: 1rem;
            padding-top: 1rem;
        }

        .summary-label {
            color: var(--text-secondary);
        }

        .summary-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .summary-total .summary-label,
        .summary-total .summary-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent);
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: #86efac;
        }

        .loading {
            text-align: center;
            padding: 3rem;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .checkout-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .checkout-summary {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .checkout-container {
                padding: 1rem;
            }

            .checkout-form {
                padding: 2rem;
            }

            .checkout-summary {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-microchip me-2"></i>PC Parts Central
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-store me-1"></i>Shop
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart me-1"></i>Cart
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><span id="customer-name">Loading...</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php"><i class="fas fa-receipt me-2"></i>My Orders</a></li>
                            <li><hr class="dropdown-divider" style="border-color: var(--border-color);"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Loading State -->
    <div id="loading" class="loading" style="display: none;">
        <div class="spinner"></div>
        <p style="color: var(--text-secondary);">Processing your order...</p>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(0, 245, 255, 0.3);">
                <div class="modal-body text-center p-5">
                    <div style="width: 80px; height: 80px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, #10b981, #34d399); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-check fa-2x text-white"></i>
                    </div>
                    <h3 style="color: var(--text-primary); margin-bottom: 1rem;">Order Placed Successfully!</h3>
                    <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                        Thank you for your purchase. Your order has been placed and you'll receive a confirmation email shortly.
                    </p>
                    <button type="button" class="btn-place-order" onclick="window.location.href='index.php'" style="margin-bottom: 1rem;">
                        <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                    </button>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;">
                        <a href="#" onclick="window.location.href='orders.php'" style="color: var(--accent);">View your orders</a> to track this purchase.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="checkout-container" id="checkout-content">
        <!-- Back Button -->
        <a href="cart.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Cart</span>
        </a>

        <!-- Checkout Header -->
        <div class="checkout-header">
            <h1 class="checkout-title">
                <i class="fas fa-credit-card me-3"></i>
                Secure Checkout
            </h1>
            <p class="checkout-subtitle">Complete your purchase with confidence</p>
        </div>

        <!-- Alert Container -->
        <div id="alert-container"></div>

        <div class="checkout-content">
            <!-- Checkout Form -->
            <div class="checkout-form">
                <!-- Billing Information -->
                <div class="billing-section">
                    <h2 class="section-title">
                        <i class="fas fa-user me-2"></i>
                        Billing Information
                    </h2>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-input" id="billing_first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-input" id="billing_last_name" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-input" id="billing_email" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone *</label>
                        <input type="tel" class="form-input" id="billing_phone" required>
                    </div>
                </div>

                <!-- Shipping Information -->
                <div class="shipping-section">
                    <h2 class="section-title">
                        <i class="fas fa-truck me-2"></i>
                        Shipping Address
                    </h2>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="same_as_billing" checked>
                            <span style="margin-left: 0.5rem; color: var(--text-primary)">Same as billing address</span>
                        </label>
                    </div>

                    <div id="shipping-fields" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Address Line 1 *</label>
                            <input type="text" class="form-input" id="shipping_address_1" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Address Line 2</label>
                            <input type="text" class="form-input" id="shipping_address_2">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">City *</label>
                                    <input type="text" class="form-input" id="shipping_city" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Province *</label>
                                    <input type="text" class="form-input" id="shipping_province" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Postal Code *</label>
                                    <input type="text" class="form-input" id="shipping_postal" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Country *</label>
                                    <select class="form-select" id="shipping_country" required>
                                        <option value="PH">Philippines</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Billing Address -->
                <div id="billing-address-section">
                    <h2 class="section-title">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        Billing Address
                    </h2>

                    <div class="form-group">
                        <label class="form-label">Address Line 1 *</label>
                        <input type="text" class="form-input" id="billing_address_1" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address Line 2</label>
                        <input type="text" class="form-input" id="billing_address_2">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">City *</label>
                                <input type="text" class="form-input" id="billing_city" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Province *</label>
                                <input type="text" class="form-input" id="billing_province" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Postal Code *</label>
                                <input type="text" class="form-input" id="billing_postal" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Country *</label>
                                <select class="form-select" id="billing_country" required>
                                    <option value="PH">Philippines</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="payment-section">
                    <h2 class="section-title">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        Payment Method
                    </h2>

                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="cash" class="payment-radio" checked>
                        <div class="payment-icon payment-cash">
                            <i class="fas fa-money-bill-wave fa-lg" style="color: white;"></i>
                        </div>
                        <div class="payment-info">
                            <div class="payment-name">Cash on Delivery</div>
                            <div class="payment-description">Pay when your order arrives</div>
                        </div>
                    </label>

                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="card" class="payment-radio">
                        <div class="payment-icon payment-card">
                            <i class="fas fa-credit-card fa-lg" style="color: white;"></i>
                        </div>
                        <div class="payment-info">
                            <div class="payment-name">Credit/Debit Card</div>
                            <div class="payment-description">Secure payment via PayPal</div>
                        </div>
                    </label>
                </div>

                <!-- Order Notes -->
                <div class="form-group">
                    <label class="form-label">Order Notes (Optional)</label>
                    <textarea class="form-input" id="order_notes" rows="3" placeholder="Special delivery instructions or notes..."></textarea>
                </div>

                <!-- Place Order Button -->
                <button type="submit" class="btn-place-order" id="placeOrderBtn">
                    <i class="fas fa-shopping-cart me-2"></i>
                    <span id="orderBtnText">Place Order</span>
                </button>
            </div>

            <!-- Order Summary -->
            <div class="checkout-summary">
                <h3 class="section-title">
                    <i class="fas fa-receipt me-2"></i>
                    Order Summary
                </h3>

                <div id="order-items">
                    <!-- Order items will be loaded here -->
                </div>

                <div id="order-totals" style="border-top: 1px solid var(--border-color); margin-top: 1.5rem; padding-top: 1.5rem;">
                    <!-- Order totals will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Checkout JS -->
    <script>
        // Environment detection
        const IS_DEVELOPMENT = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        const BASE_PATH = IS_DEVELOPMENT ? '/core1' : '';
        const API_BASE = BASE_PATH + '/backend/api';

        // Show alert function
        function showAlert(message, type = 'danger') {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            `;
            alertContainer.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 5000);
        }

        // Load cart and customer data
        async function loadCheckoutData() {
            try {
                // Load cart data
                const cartResponse = await fetch(`${API_BASE}/shop/cart.php`);
                const cartData = await cartResponse.json();

                if (!cartData.success || !cartData.data.items || cartData.data.items.length === 0) {
                    showAlert('Your cart is empty. Please add items to your cart before checkout.');
                    setTimeout(() => window.location.href = 'index.php', 3000);
                    return;
                }

                // Load customer data
                const authResponse = await fetch(`${API_BASE}/shop/auth.php?action=me`);
                const authData = await authResponse.json();

                if (authData.success && authData.data.customer) {
                    populateCustomerData(authData.data.customer);
                }

                renderOrderSummary(cartData.data);
                renderOrderItems(cartData.data);

            } catch (error) {
                console.error('Error loading checkout data:', error);
                showAlert('Failed to load checkout data. Please try again.');
            }
        }

        // Populate customer data in form
        function populateCustomerData(customer) {
            document.getElementById('billing_first_name').value = customer.first_name || '';
            document.getElementById('billing_last_name').value = customer.last_name || '';
            document.getElementById('billing_email').value = customer.email || '';

            // Auto-select same as billing
            if (customer.first_name && customer.last_name) {
                document.getElementById('same_as_billing').checked = true;
                document.getElementById('shipping-fields').style.display = 'none';
            }
        }

        // Render order items
        function renderOrderItems(cartData) {
            const orderItems = document.getElementById('order-items');
            const items = cartData.items;

            // Helper to fix image URLs
            const fixImageUrl = (url) => {
                if (!url) return '';
                url = url.replace(/assets\/img\/assets\/img\//g, 'assets/img/');
                if (url.startsWith('assets/')) {
                    url = BASE_PATH + '/public/' + url;
                }
                return url;
            };

            orderItems.innerHTML = items.map(item => `
                <div class="order-item">
                    <div class="order-item-image">
                        ${item.product.image_url
                            ? `<img src="${fixImageUrl(item.product.image_url)}" alt="${item.product.name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.5rem;">`
                            : `<i class="fas fa-microchip fa-2x text-accent"></i>`
                        }
                    </div>
                    <div class="order-item-details">
                        <div class="order-item-name">${item.product.name}</div>
                        <div class="order-item-meta">Qty: ${item.quantity}</div>
                    </div>
                    <div class="order-item-price">₱${parseFloat(item.total_price).toFixed(2)}</div>
                </div>
            `).join('');
        }

        // Render order totals
        function renderOrderTotals(totals) {
            const orderTotals = document.getElementById('order-totals');

            orderTotals.innerHTML = `
                <div class="summary-row">
                    <span class="summary-label">Subtotal (${totals.total_quantity} items)</span>
                    <span class="summary-value">₱${parseFloat(totals.subtotal).toFixed(2)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Tax (8%)</span>
                    <span class="summary-value">₱${parseFloat(totals.tax_amount).toFixed(2)}</span>
                </div>
                ${totals.shipping_amount ? `
                <div class="summary-row">
                    <span class="summary-label">Shipping</span>
                    <span class="summary-value">₱${parseFloat(totals.shipping_amount).toFixed(2)}</span>
                </div>
                ` : ''}
                <div class="summary-row summary-total">
                    <span class="summary-label">Total</span>
                    <span class="summary-value">₱${parseFloat(totals.total_amount).toFixed(2)}</span>
                </div>
            `;
        }

        // Render order summary
        function renderOrderSummary(cartData) {
            renderOrderTotals(cartData.totals);
        }

        // Toggle shipping address fields
        document.getElementById('same_as_billing').addEventListener('change', function() {
            const shippingFields = document.getElementById('shipping-fields');
            shippingFields.style.display = this.checked ? 'none' : 'block';

            // Update required attributes
            const shippingInputs = shippingFields.querySelectorAll('input');
            shippingInputs.forEach(input => {
                if (this.checked) {
                    input.required = false;
                } else {
                    input.required = true;
                }
            });
        });

        // Payment method selection
        document.querySelectorAll('.payment-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.payment-option').forEach(option => {
                    option.classList.remove('selected');
                });
                if (this.checked) {
                    this.parentElement.classList.add('selected');
                }
            });
        });

        // Form submission
        document.getElementById('placeOrderBtn').addEventListener('click', async function(e) {
            e.preventDefault();

            // Validate form
            if (!validateForm()) {
                return;
            }

            const placeOrderBtn = document.getElementById('placeOrderBtn');
            const orderBtnText = document.getElementById('orderBtnText');
            const loading = document.getElementById('loading');
            const checkoutContent = document.getElementById('checkout-content');

            // Show loading
            loading.style.display = 'block';
            checkoutContent.style.display = 'none';
            placeOrderBtn.disabled = true;
            orderBtnText.textContent = 'Processing...';

            try {
                const checkoutData = collectFormData();

                const response = await fetch(`${API_BASE}/shop/checkout.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(checkoutData)
                });

                const data = await response.json();

                if (data.success) {
                    // Show success modal
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();

                    // Clear cart after successful order
                    localStorage.setItem('cart', JSON.stringify([]));
                } else {
                    // Hide loading, show error
                    loading.style.display = 'none';
                    checkoutContent.style.display = 'block';
                    showAlert(data.message || 'Failed to place order. Please try again.');
                }

            } catch (error) {
                console.error('Checkout error:', error);
                loading.style.display = 'none';
                checkoutContent.style.display = 'block';
                showAlert('An error occurred while processing your order. Please try again.');
            } finally {
                placeOrderBtn.disabled = false;
                orderBtnText.textContent = 'Place Order';
            }
        });

        // Collect form data
        function collectFormData() {
            const sameAsBilling = document.getElementById('same_as_billing').checked;
            const paymentMethodValue = document.querySelector('input[name="payment_method"]:checked').value;
            // Convert frontend value to backend expected value
            const paymentMethod = paymentMethodValue === 'cash' ? 'cash_on_delivery' : paymentMethodValue === 'card' ? 'credit_card' : paymentMethodValue;

            let billingAddress = {
                first_name: document.getElementById('billing_first_name').value,
                last_name: document.getElementById('billing_last_name').value,
                email: document.getElementById('billing_email').value,
                phone: document.getElementById('billing_phone').value,
                address_line_1: document.getElementById('billing_address_1').value,
                address_line_2: document.getElementById('billing_address_2').value,
                city: document.getElementById('billing_city').value,
                province: document.getElementById('billing_province').value,
                postal_code: document.getElementById('billing_postal').value,
                country: document.getElementById('billing_country').value
            };

            let shippingAddress = sameAsBilling ? billingAddress : {
                first_name: document.getElementById('billing_first_name').value,
                last_name: document.getElementById('billing_last_name').value,
                email: document.getElementById('billing_email').value,
                phone: document.getElementById('billing_phone').value,
                address_line_1: document.getElementById('shipping_address_1').value,
                address_line_2: document.getElementById('shipping_address_2').value,
                city: document.getElementById('shipping_city').value,
                province: document.getElementById('shipping_province').value,
                postal_code: document.getElementById('shipping_postal').value,
                country: document.getElementById('shipping_country').value
            };

            return {
                billing_address: billingAddress,
                shipping_address: shippingAddress,
                payment_method: paymentMethod,
                notes: document.getElementById('order_notes').value
            };
        }

        // Validate form
        function validateForm() {
            const requiredFields = [
                'billing_first_name', 'billing_last_name', 'billing_email',
                'billing_phone', 'billing_address_1', 'billing_city',
                'billing_province', 'billing_postal'
            ];

            let isValid = true;
            for (const field of requiredFields) {
                const element = document.getElementById(field);
                if (!element.value.trim()) {
                    element.style.borderColor = 'var(--danger)';
                    isValid = false;
                } else {
                    element.style.borderColor = 'var(--border-color)';
                }
            }

            // Email validation
            const emailField = document.getElementById('billing_email');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailField.value && !emailRegex.test(emailField.value)) {
                emailField.style.borderColor = 'var(--danger)';
                showAlert('Please enter a valid email address.');
                isValid = false;
            }

            // If not same as billing, validate shipping fields
            if (!document.getElementById('same_as_billing').checked) {
                const shippingFields = [
                    'shipping_address_1', 'shipping_city', 'shipping_province', 'shipping_postal'
                ];

                for (const field of shippingFields) {
                    const element = document.getElementById(field);
                    if (!element.value.trim()) {
                        element.style.borderColor = 'var(--danger)';
                        isValid = false;
                    }
                }
            }

            // Payment method validation
            const paymentSelected = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentSelected) {
                showAlert('Please select a payment method.');
                isValid = false;
            }

            return isValid;
        }

        // Load customer name
        async function loadCustomerName() {
            try {
                const response = await fetch(`${API_BASE}/auth/session.php`);
                const data = await response.json();

                if (data.success && data.data) {
                    document.getElementById('customer-name').textContent = data.data.first_name || 'Account';
                }
            } catch (error) {
                console.error('Error loading customer name:', error);
            }
        }

        // Logout
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                fetch(`${API_BASE}/auth/logout.php`, { method: 'POST' })
                    .then(() => window.location.href = 'login.php')
                    .catch(() => window.location.href = 'login.php');
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadCheckoutData();
            initializePaymentSelection(); // Initialize payment method selection
            loadCustomerName(); // Load customer name in navbar
        });

        // Initialize payment method selection (for pre-checked default)
        function initializePaymentSelection() {
            document.querySelectorAll('.payment-radio').forEach(radio => {
                if (radio.checked) {
                    radio.parentElement.classList.add('selected');
                }
            });
        }
    </script>
</body>
</html>
