<?php
/**
 * Maintenance Mode Check
 */
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/middleware/MaintenanceMode.php';

// Check if maintenance mode is enabled and user is not admin
if (MaintenanceMode::handle()) {
    MaintenanceMode::renderMaintenancePage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - PC Parts Central</title>
    <link rel="icon" type="image/png" href="../ppc.png">

    <?php
    $localBootstrapCss = __DIR__ . '/assets/vendor/bootstrap.min.css';
    $localFaCss = __DIR__ . '/assets/vendor/fontawesome.min.css';
    if (file_exists($localBootstrapCss)) {
        echo '<link rel="stylesheet" href="assets/vendor/bootstrap.min.css">';
    } else {
        echo '<link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.rel=\'stylesheet\'">';
        echo '<noscript><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></noscript>';
    }
    if (file_exists($localFaCss)) {
        echo '<link rel="stylesheet" href="assets/vendor/fontawesome.min.css">';
    } else {
        echo '<link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.rel=\'stylesheet\'">';
        echo '<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>';
    }
    // Custom Premium CSS
    echo '<link rel="stylesheet" href="assets/css/main.css">';
    ?>

    <style>
        /* Cart-specific styles */
        .cart-header {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, rgba(0, 245, 255, 0.1) 100%);
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .cart-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 900;
            text-align: center;
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .cart-subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 1.1rem;
        }

        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .cart-item {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            border-color: var(--accent);
            box-shadow: 0 0 30px rgba(0, 245, 255, 0.2);
            transform: translateY(-2px);
        }

        .cart-item-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--bg-glass) 0%, rgba(0, 245, 255, 0.1) 100%);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .cart-item-meta {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .cart-item-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent);
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: var(--accent);
            border-color: var(--accent);
            color: var(--bg-primary);
        }

        .quantity-input {
            width: 60px;
            height: 35px;
            text-align: center;
            border: 1px solid var(--border-color);
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 0.5rem;
            font-weight: 600;
        }

        .cart-item-total {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent);
            text-align: right;
        }

        .remove-btn {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: rgba(255, 51, 102, 0.1);
            color: #ff3366;
        }

        .cart-summary {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            position: sticky;
            top: 2rem;
        }

        .summary-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 0.5rem 0;
        }

        .summary-label {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .summary-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .summary-total {
            border-top: 2px solid var(--border-accent);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .summary-total .summary-label {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .summary-total .summary-value {
            font-size: 1.4rem;
            font-weight: 900;
            color: var(--accent);
        }

        .checkout-btn {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            border: none;
            border-radius: 0.75rem;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 1.5rem;
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0, 245, 255, 0.4);
        }

        .checkout-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .continue-shopping {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .continue-shopping a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .continue-shopping a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .empty-cart {
            text-align: center;
            padding: 5rem 2rem;
        }

        .empty-cart-icon {
            font-size: 5rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            opacity: 0.5;
        }

        .empty-cart-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .empty-cart-text {
            color: var(--text-muted);
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }

        .loading {
            text-align: center;
            padding: 3rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
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

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }

        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #93c5fd;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cart-container {
                padding: 0 1rem;
            }

            .cart-item {
                padding: 1rem;
            }

            .cart-item-image {
                width: 60px;
                height: 60px;
            }

            .quantity-controls {
                margin: 0.5rem 0;
            }

            .cart-summary {
                position: static;
                margin-top: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center w-100">
                <a class="navbar-brand d-flex align-items-center" href="index.php">
                    <img src="../ppc.png" alt="PC Parts Central Logo" style="height: 32px; width: auto; margin-right: 8px;">
                    PC Parts Central
                </a>
                <div class="d-flex align-items-center gap-3">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-arrow-left"></i>
                        Continue Shopping
                    </a>

                    <!-- User Menu (shown when authenticated) -->
                    <li class="nav-item dropdown" id="user-menu" style="display: none;">
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

                    <!-- Login Button (shown when not authenticated) -->
                    <a href="login.php" class="btn btn-outline-light btn-sm" id="login-btn">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        <span class="d-none d-md-inline">Login</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Cart Header -->
    <section class="cart-header">
        <div class="container">
            <h1 class="cart-title">
                <i class="fas fa-shopping-cart me-2"></i>
                Shopping Cart
            </h1>
            <p class="cart-subtitle">Review your items and proceed to checkout</p>
        </div>
    </section>

    <!-- Cart Content -->
    <section class="py-5">
        <div class="container cart-container">
            <div id="alert-container"></div>

            <!-- Loading State -->
            <div id="loading" class="loading" style="display: none;">
                <div class="spinner"></div>
                <p class="text-muted">Loading your cart...</p>
            </div>

            <!-- Empty Cart State -->
            <div id="empty-cart" class="empty-cart" style="display: none;">
                <i class="fas fa-shopping-cart empty-cart-icon"></i>
                <h2 class="empty-cart-title">Your cart is empty</h2>
                <p class="empty-cart-text">Looks like you haven't added any items to your cart yet.</p>
                <a href="index.php" class="btn btn-accent btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i>
                    Start Shopping
                </a>
            </div>

            <!-- Cart Items and Summary -->
            <div id="cart-content" style="display: none;">
                <div class="row">
                    <!-- Cart Items -->
                    <div class="col-lg-8">
                        <div id="cart-items">
                            <!-- Cart items will be loaded here -->
                        </div>
                    </div>

                    <!-- Cart Summary -->
                    <div class="col-lg-4">
                        <div class="cart-summary">
                            <h3 class="summary-title">
                                <i class="fas fa-receipt me-2"></i>
                                Order Summary
                            </h3>

                            <div id="summary-content">
                                <!-- Summary will be loaded here -->
                            </div>

                            <button id="checkout-btn" class="checkout-btn" disabled>
                                <i class="fas fa-credit-card me-2"></i>
                                Proceed to Checkout
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="delete-confirm-modal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-glass); backdrop-filter: blur(20px); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" id="deleteConfirmModalLabel" style="color: var(--text-primary);">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Confirm Removal
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="color: var(--text-primary);">
                    <p class="mb-0">Are you sure you want to remove this item from your cart?</p>
                    <p class="text-muted mt-2 mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirm-delete-btn" onclick="confirmDelete()">
                        <i class="fas fa-trash me-1"></i>
                        Remove Item
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container text-center">
            <div class="mb-3">
                <i class="fas fa-microchip fa-2x text-accent"></i>
            </div>
            <h5 class="text-primary mb-2">PC Parts Central</h5>
            <p class="text-muted mb-3">
                Your trusted source for premium PC components
            </p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Cart JS -->
    <script>
        const IS_DEVELOPMENT = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        const API_BASE = '/core1/backend/api';

        // Show alert function
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                ${message}
            `;
            alertContainer.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 5000);
        }

        // Load cart function
        async function loadCart() {
            const loading = document.getElementById('loading');
            const emptyCart = document.getElementById('empty-cart');
            const cartContent = document.getElementById('cart-content');

            loading.style.display = 'block';
            emptyCart.style.display = 'none';
            cartContent.style.display = 'none';

            try {
                const response = await fetch(`${API_BASE}/shop/cart.php`);
                const data = await response.json();

                loading.style.display = 'none';

                if (data.success && data.data.items && data.data.items.length > 0) {
                    renderCart(data.data);
                    cartContent.style.display = 'block';
                } else {
                    emptyCart.style.display = 'block';
                }
            } catch (error) {
                loading.style.display = 'none';
                showAlert('Failed to load cart. Please try again.', 'danger');
                if (IS_DEVELOPMENT) console.error('Error loading cart:', error);
            }
        }

        // Render cart items
        function renderCart(cartData) {
            const cartItems = document.getElementById('cart-items');
            const summaryContent = document.getElementById('summary-content');
            const checkoutBtn = document.getElementById('checkout-btn');

            // Render cart items
            cartItems.innerHTML = cartData.items.map(item => `
                <div class="cart-item" data-product-id="${item.product_id}">
                    <div class="d-flex align-items-center">
                        <div class="cart-item-image">
                            ${item.product.image_url
                                ? `<img src="${item.product.image_url}" alt="${item.product.name}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 0.5rem;">`
                                : `<i class="fas fa-microchip fa-2x text-accent"></i>`
                            }
                        </div>
                        <div class="cart-item-details">
                            <h5 class="cart-item-title">${item.product.name}</h5>
                            <div class="cart-item-meta">
                                <span class="badge bg-${item.in_stock ? 'success' : 'danger'} me-2">
                                    ${item.in_stock ? 'In Stock' : 'Out of Stock'}
                                </span>
                                ${!item.sufficient_stock && item.in_stock ? `<span class="badge bg-warning text-dark me-2">Only ${item.available_stock} available</span>` : ''}
                                Unit Price: ₱${parseFloat(item.unit_price).toFixed(2)}
                            </div>
                            ${!item.sufficient_stock && item.in_stock ? `<div class="alert alert-warning mt-2 mb-0 py-1 px-2" style="font-size: 0.85rem;"><i class="fas fa-exclamation-triangle me-1"></i>You have ${item.quantity} in cart, but only ${item.available_stock} available. Please reduce quantity.</div>` : ''}
                            <div class="quantity-controls">
                                <button class="quantity-btn" onclick="updateQuantity(${item.product_id}, ${item.quantity - 1})">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="quantity-input" value="${item.quantity}"
                                       min="1" max="99" onchange="updateQuantity(${item.product_id}, this.value)">
                                <button class="quantity-btn" onclick="updateQuantity(${item.product_id}, ${item.quantity + 1})">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="ms-auto text-end">
                            <div class="cart-item-total">₱${parseFloat(item.total_price).toFixed(2)}</div>
                            <button class="remove-btn" onclick="removeItem(${item.product_id})" title="Remove item">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');

            // Render summary
            const totals = cartData.totals;
            summaryContent.innerHTML = `
                <div class="summary-row">
                    <span class="summary-label">Items (${totals.total_quantity})</span>
                    <span class="summary-value">₱${parseFloat(totals.subtotal).toFixed(2)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Tax (8%)</span>
                    <span class="summary-value">₱${parseFloat(totals.tax_amount).toFixed(2)}</span>
                </div>
                <div class="summary-row summary-total">
                    <span class="summary-label">Total</span>
                    <span class="summary-value">₱${parseFloat(totals.total_amount).toFixed(2)}</span>
                </div>
            `;

            // Enable/disable checkout button and show warnings
            const hasValidItems = cartData.items.some(item => item.in_stock && item.sufficient_stock);
            const allItemsValid = cartData.items.every(item => item.in_stock && item.sufficient_stock);
            const hasInsufficientStock = cartData.items.some(item => !item.sufficient_stock && item.in_stock);

            checkoutBtn.disabled = !allItemsValid;
            if (!hasValidItems) {
                checkoutBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Items Out of Stock';
            } else if (hasInsufficientStock) {
                checkoutBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Reduce Quantities to Checkout';
            } else {
                checkoutBtn.innerHTML = '<i class="fas fa-credit-card me-2"></i>Proceed to Checkout';
            }
        }

        // Update quantity
        async function updateQuantity(productId, newQuantity) {
            if (newQuantity < 1) return;

            try {
                const response = await fetch(`${API_BASE}/shop/cart.php`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        quantity: parseInt(newQuantity)
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Update localStorage to match database update
                    const cartItems = JSON.parse(localStorage.getItem('cart') || '[]');
                    const itemIndex = cartItems.findIndex(item => parseInt(item.id) === parseInt(productId));
                    if (itemIndex !== -1) {
                        cartItems[itemIndex].quantity = parseInt(newQuantity);
                        localStorage.setItem('cart', JSON.stringify(cartItems));
                    }

                    loadCart(); // Reload cart
                } else {
                    showAlert(data.message || 'Failed to update quantity', 'danger');
                }
            } catch (error) {
                showAlert('Failed to update quantity. Please try again.', 'danger');
                if (IS_DEVELOPMENT) console.error('Error updating quantity:', error);
            }
        }

        // Remove item
        async function removeItem(productId) {
            // Store the product ID for the modal
            document.getElementById('confirm-delete-btn').dataset.productId = productId;

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('delete-confirm-modal'));
            modal.show();
        }

        // Confirm delete from modal
        async function confirmDelete() {
            const productId = document.getElementById('confirm-delete-btn').dataset.productId;

            try {
                const response = await fetch(`${API_BASE}/shop/cart.php?product_id=${productId}`, {
                    method: 'DELETE'
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('Item removed from cart', 'success');

                    // Update localStorage to match database removal
                    const cartItems = JSON.parse(localStorage.getItem('cart') || '[]');
                    const updatedCart = cartItems.filter(item => parseInt(item.id) !== parseInt(productId));
                    localStorage.setItem('cart', JSON.stringify(updatedCart));

                    loadCart(); // Reload cart
                    // Hide the modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('delete-confirm-modal'));
                    modal.hide();
                } else {
                    showAlert(data.message || 'Failed to remove item', 'danger');
                }
            } catch (error) {
                showAlert('Failed to remove item. Please try again.', 'danger');
                if (IS_DEVELOPMENT) console.error('Error removing item:', error);
            }
        }

        // Clear cart
        async function clearCart() {
            if (!confirm('Are you sure you want to clear your entire cart?')) return;

            try {
                const response = await fetch(`${API_BASE}/shop/cart.php?clear=true`, {
                    method: 'DELETE'
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('Cart cleared successfully', 'success');

                    // Clear localStorage to match database
                    localStorage.setItem('cart', JSON.stringify([]));

                    loadCart(); // Reload cart
                } else {
                    showAlert(data.message || 'Failed to clear cart', 'danger');
                }
            } catch (error) {
                showAlert('Failed to clear cart. Please try again.', 'danger');
                if (IS_DEVELOPMENT) console.error('Error clearing cart:', error);
            }
        }

        // Checkout handler
        document.getElementById('checkout-btn').addEventListener('click', function() {
            if (!this.disabled) {
                // Redirect to checkout page
                window.location.href = 'checkout.php';
            }
        });

        // Authentication check
        let isAuthenticated = false;

        // Check if customer is authenticated
        async function checkAuthentication() {
            try {
                const response = await fetch(`${API_BASE}/shop/auth.php?action=me`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data.success) {
                    // Check if this is a staff user
                    if (data.data && data.data.is_staff) {
                        // Redirect staff to the employee dashboard
                        window.location.href = 'dashboard.php';
                        return null;
                    }
                    isAuthenticated = true;
                    return data.data.customer;
                } else {
                    isAuthenticated = false;
                    return null;
                }
            } catch (error) {
                if (IS_DEVELOPMENT) console.error('Auth check error:', error);
                isAuthenticated = false;
                return null;
            }
        }

        // Initialize cart authentication and user menu
        async function initCartAuth() {
            const user = await checkAuthentication();
            const userMenu = document.getElementById('user-menu');
            const loginBtn = document.getElementById('login-btn');
            const customerName = document.getElementById('customer-name');

            if (isAuthenticated && user) {
                // User is authenticated, show user menu
                userMenu.style.display = 'block';
                loginBtn.style.display = 'none';
                customerName.textContent = user.first_name || 'User';
            } else {
                // User not authenticated, show login button
                userMenu.style.display = 'none';
                loginBtn.style.display = 'inline-block';
            }
        }

        // Logout function
        async function logout() {
            try {
                await fetch(`${API_BASE}/shop/auth.php?action=logout`, {
                    method: 'DELETE',
                    credentials: 'same-origin'
                });
            } catch (error) {
                if (IS_DEVELOPMENT) console.error('Logout error:', error);
            }
            window.location.href = 'login.php';
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initCartAuth(); // Check authentication first
            loadCart();
        });
    </script>
</body>
</html>
