<?php
/**
 * Customer Order Details Page
 * Shows detailed information for a specific order
 */

session_start();

// Include required files
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/Response.php';

// Check if customer is authenticated
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerId = $_SESSION['customer_id'];
$customerEmail = $_SESSION['customer_email'] ?? '';

// Get order ID from URL
$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    header('Location: orders.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - PC Parts Central</title>
    <link rel="icon" type="image/png" href="../ppc.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/main.css">

    <style>
        .order-details-hero {
            padding: 4rem 0 2rem;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, rgba(0, 245, 255, 0.05) 100%);
        }

        .order-details-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .order-header {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .order-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }

        .order-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .meta-item {
            background: rgba(0, 245, 255, 0.05);
            border: 1px solid rgba(0, 245, 255, 0.2);
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .meta-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }

        .meta-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .order-status {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .status-confirmed { background: rgba(0, 123, 255, 0.2); color: #007bff; }
        .status-processing { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .status-shipped { background: rgba(0, 123, 255, 0.2); color: #007bff; }
        .status-delivered { background: rgba(25, 135, 84, 0.2); color: #198754; }
        .status-cancelled { background: rgba(220, 53, 69, 0.2); color: #dc3545; }
        .status-returned { background: rgba(108, 117, 125, 0.2); color: #6c757d; }

        .order-section {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .item-sku {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .item-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .item-quantity, .item-price {
            font-weight: 600;
            color: var(--accent);
        }

        .address-card {
            background: rgba(0, 245, 255, 0.05);
            border: 1px solid rgba(0, 245, 255, 0.2);
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .address-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .address-line {
            margin-bottom: 0.25rem;
            color: var(--text-secondary);
        }

        .order-summary {
            background: linear-gradient(135deg, rgba(0, 245, 255, 0.1) 0%, rgba(0, 245, 255, 0.05) 100%);
            border: 1px solid rgba(0, 245, 255, 0.3);
            border-radius: 1rem;
            padding: 2rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0, 245, 255, 0.2);
        }

        .summary-row:last-child {
            border-bottom: none;
            border-top: 2px solid var(--accent);
            margin-top: 0.5rem;
            padding-top: 1rem;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent);
        }

        .summary-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .summary-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .order-actions {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            margin-top: 2rem;
        }

        .btn-outline-accent {
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-outline-accent:hover {
            background: var(--accent);
            color: var(--bg-primary);
        }

        .tracking-info {
            background: rgba(25, 135, 84, 0.1);
            border: 1px solid rgba(25, 135, 84, 0.3);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }

        .tracking-number {
            font-family: monospace;
            font-weight: 600;
            color: var(--success);
        }

        .loading {
            text-align: center;
            padding: 4rem 2rem;
        }

        .loading i {
            font-size: 2rem;
            color: var(--accent);
        }

        .error-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }

        .error-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        @media (max-width: 768px) {
            .order-meta {
                grid-template-columns: 1fr;
            }

            .order-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .item-meta {
                flex-direction: column;
                gap: 0.25rem;
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
                    <a href="orders.php" class="nav-link">
                        <i class="fas fa-arrow-left me-1"></i>
                        Back to Orders
                    </a>
                    <a href="cart.php" class="cart-button">
                        <i class="fas fa-shopping-cart"></i>
                        Cart
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Order Details Hero -->
    <section class="order-details-hero">
        <div class="container">
            <div class="text-center">
                <h1 class="display-4 mb-3">Order Details</h1>
                <p class="lead text-muted">Complete information about your order</p>
            </div>
        </div>
    </section>

    <!-- Order Details Content -->
    <section class="py-5">
        <div class="container order-details-container">
            <!-- Loading State -->
            <div id="loading-state" class="loading">
                <i class="fas fa-spinner fa-spin"></i>
                <p class="mt-2">Loading order details...</p>
            </div>

            <!-- Error State -->
            <div id="error-state" class="error-state d-none">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Order Not Found</h3>
                <p>The order you're looking for doesn't exist or you don't have permission to view it.</p>
                <a href="orders.php" class="btn btn-accent">
                    <i class="fas fa-arrow-left me-2"></i>
                    Back to Orders
                </a>
            </div>

            <!-- Order Content -->
            <div id="order-content" class="d-none">
                <!-- Order Header -->
                <div class="order-header">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="order-number" id="order-number">Loading...</div>
                            <div class="text-muted" id="order-date">Loading...</div>
                        </div>
                        <div class="order-status" id="order-status">Loading...</div>
                    </div>

                    <div class="order-meta">
                        <div class="meta-item">
                            <div class="meta-label">Payment Method</div>
                            <div class="meta-value" id="payment-method">-</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Items Ordered</div>
                            <div class="meta-value" id="items-count">-</div>
                        </div>
                        <div class="meta-item">
                            <div class="meta-label">Order Total</div>
                            <div class="meta-value" id="order-total">-</div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="order-section">
                    <h3 class="section-title">
                        <i class="fas fa-box me-2"></i>
                        Order Items
                    </h3>
                    <div id="order-items">
                        <!-- Items will be loaded here -->
                    </div>
                </div>

                <!-- Shipping & Billing -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="order-section">
                            <h3 class="section-title">
                                <i class="fas fa-truck me-2"></i>
                                Shipping Address
                            </h3>
                            <div class="address-card" id="shipping-address">
                                <!-- Shipping address will be loaded here -->
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="order-section">
                            <h3 class="section-title">
                                <i class="fas fa-credit-card me-2"></i>
                                Billing Address
                            </h3>
                            <div class="address-card" id="billing-address">
                                <!-- Billing address will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="order-section">
                    <h3 class="section-title">
                        <i class="fas fa-calculator me-2"></i>
                        Order Summary
                    </h3>
                    <div class="order-summary">
                        <div class="summary-row">
                            <span class="summary-label">Subtotal</span>
                            <span class="summary-value" id="summary-subtotal">₱0.00</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Tax (12%)</span>
                            <span class="summary-value" id="summary-tax">₱0.00</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Shipping</span>
                            <span class="summary-value" id="summary-shipping">₱0.00</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Total Amount</span>
                            <span class="summary-value" id="summary-total">₱0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Tracking Information -->
                <div id="tracking-section" class="order-section d-none">
                    <h3 class="section-title">
                        <i class="fas fa-truck me-2"></i>
                        Tracking Information
                    </h3>
                    <div class="tracking-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Tracking Number:</strong>
                                <span class="tracking-number" id="tracking-number">-</span>
                            </div>
                            <div>
                                <a href="#" class="btn btn-outline-success btn-sm" id="track-package-btn">
                                    <i class="fas fa-external-link-alt me-1"></i>
                                    Track Package
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Actions -->
                <div class="order-actions">
                    <h3 class="section-title">
                        <i class="fas fa-cogs me-2"></i>
                        Order Actions
                    </h3>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <button class="btn btn-outline-primary w-100" onclick="downloadInvoice()">
                                <i class="fas fa-download me-2"></i>
                                Download Invoice
                            </button>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-info w-100" onclick="contactSupport()">
                                <i class="fas fa-headset me-2"></i>
                                Contact Support
                            </button>
                        </div>
                        <div class="col-md-4" id="cancel-order-section">
                            <button class="btn btn-outline-danger w-100" onclick="cancelOrder()">
                                <i class="fas fa-times me-2"></i>
                                Cancel Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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

    <!-- Order Details JS -->
    <script>
        const IS_DEVELOPMENT = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        // Auto-detect base path: /core1 for local dev, empty for production
        const BASE_PATH = IS_DEVELOPMENT ? '/core1' : '';
        const API_BASE = BASE_PATH + '/backend/api';
        const customerId = <?php echo json_encode($customerId); ?>;
        const customerEmail = <?php echo json_encode($customerEmail); ?>;
        const orderId = <?php echo json_encode($orderId); ?>;

        // Load order details
        async function loadOrderDetails() {
            const loadingState = document.getElementById('loading-state');
            const errorState = document.getElementById('error-state');
            const orderContent = document.getElementById('order-content');

            try {
                const response = await fetch(`${API_BASE}/shop/orders.php?action=details&id=${orderId}`, {
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success && data.data.order) {
                    displayOrderDetails(data.data.order);
                    loadingState.style.display = 'none';
                    orderContent.classList.remove('d-none');
                } else {
                    loadingState.style.display = 'none';
                    errorState.classList.remove('d-none');
                }
            } catch (error) {
                console.error('Error loading order details:', error);
                loadingState.style.display = 'none';
                errorState.classList.remove('d-none');
            }
        }

        // Display order details
        function displayOrderDetails(order) {
            // Order header
            document.getElementById('order-number').textContent = order.order_number;
            document.getElementById('order-date').textContent = `Ordered on ${formatDate(order.order_date)}`;
            document.getElementById('order-status').className = `order-status status-${order.status.toLowerCase()}`;
            document.getElementById('order-status').textContent = formatStatus(order.status);

            // Order meta
            document.getElementById('payment-method').textContent = order.payment_method || 'N/A';
            document.getElementById('items-count').textContent = order.total_items || 0;
            document.getElementById('order-total').textContent = `₱${parseFloat(order.total_amount).toFixed(2)}`;

            // Order items
            const itemsContainer = document.getElementById('order-items');
            itemsContainer.innerHTML = order.items.map(item => `
                <div class="order-item">
                    <img src="${item.product_image || 'assets/img/no-image.png'}"
                         alt="${item.product_name}"
                         class="item-image"
                         onerror="this.src='assets/img/no-image.png'">
                    <div class="item-info">
                        <div class="item-name">${item.product_name}</div>
                        <div class="item-sku">SKU: ${item.product_sku}</div>
                        <div class="item-meta">
                            <span>Quantity: <strong>${item.quantity}</strong></span>
                            <span>Unit Price: <strong>₱${parseFloat(item.unit_price).toFixed(2)}</strong></span>
                        </div>
                    </div>
                    <div class="item-price">₱${parseFloat(item.total_price).toFixed(2)}</div>
                </div>
            `).join('');

            // Shipping address
            const shippingAddress = document.getElementById('shipping-address');
            if (order.shipping_address) {
                shippingAddress.innerHTML = `
                    <div class="address-title">${order.shipping_address.first_name} ${order.shipping_address.last_name}</div>
                    <div class="address-line">${order.shipping_address.address_line_1}</div>
                    ${order.shipping_address.address_line_2 ? `<div class="address-line">${order.shipping_address.address_line_2}</div>` : ''}
                    <div class="address-line">${order.shipping_address.city}, ${order.shipping_address.state} ${order.shipping_address.postal_code}</div>
                    <div class="address-line">${order.shipping_address.country}</div>
                `;
            } else {
                shippingAddress.innerHTML = '<div class="text-muted">No shipping address available</div>';
            }

            // Billing address
            const billingAddress = document.getElementById('billing-address');
            if (order.billing_address) {
                billingAddress.innerHTML = `
                    <div class="address-title">${order.billing_address.first_name} ${order.billing_address.last_name}</div>
                    <div class="address-line">${order.billing_address.address_line_1}</div>
                    ${order.billing_address.address_line_2 ? `<div class="address-line">${order.billing_address.address_line_2}</div>` : ''}
                    <div class="address-line">${order.billing_address.city}, ${order.billing_address.state} ${order.billing_address.postal_code}</div>
                    <div class="address-line">${order.billing_address.country}</div>
                `;
            } else {
                billingAddress.innerHTML = '<div class="text-muted">No billing address available</div>';
            }

            // Order summary
            document.getElementById('summary-subtotal').textContent = `₱${parseFloat(order.subtotal).toFixed(2)}`;
            document.getElementById('summary-tax').textContent = `₱${parseFloat(order.tax_amount).toFixed(2)}`;
            document.getElementById('summary-shipping').textContent = `₱${parseFloat(order.shipping_amount).toFixed(2)}`;
            document.getElementById('summary-total').textContent = `₱${parseFloat(order.total_amount).toFixed(2)}`;

            // Tracking information
            if (order.tracking_number) {
                document.getElementById('tracking-number').textContent = order.tracking_number;
                document.getElementById('tracking-section').classList.remove('d-none');
            }

            // Order actions based on status
            updateOrderActions(order);
        }

        // Update order actions based on status
        function updateOrderActions(order) {
            const cancelSection = document.getElementById('cancel-order-section');

            switch (order.status.toLowerCase()) {
                case 'pending':
                case 'confirmed':
                    cancelSection.style.display = 'block';
                    break;
                default:
                    cancelSection.style.display = 'none';
                    break;
            }
        }

        // Format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Format status
        function formatStatus(status) {
            const statusMap = {
                'pending': 'Pending',
                'confirmed': 'Confirmed',
                'processing': 'Processing',
                'shipped': 'Shipped',
                'delivered': 'Delivered',
                'cancelled': 'Cancelled',
                'returned': 'Returned'
            };
            return statusMap[status] || status;
        }

        // Order actions
        function printOrder() {
            window.print();
        }

        function downloadInvoice() {
            // Create a simple invoice download
            const invoiceContent = generateInvoiceHTML();
            const blob = new Blob([invoiceContent], { type: 'text/html' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `invoice-${orderId}.html`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            showNotification('Invoice downloaded successfully', 'success');
        }

        function contactSupport() {
            // Open support modal or redirect
            showNotification('Support contact functionality will be implemented', 'info');
        }

        async function cancelOrder() {
            if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) return;

            try {
                const response = await fetch(`${API_BASE}/shop/orders.php?action=cancel`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ order_id: orderId })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Order cancelled successfully', 'success');
                    setTimeout(() => {
                        window.location.href = 'orders.php';
                    }, 2000);
                } else {
                    showNotification(data.message || 'Failed to cancel order', 'error');
                }
            } catch (error) {
                console.error('Error cancelling order:', error);
                showNotification('Error cancelling order', 'error');
            }
        }

        // Generate invoice HTML
        function generateInvoiceHTML() {
            const orderNumber = document.getElementById('order-number').textContent;
            const orderDate = document.getElementById('order-date').textContent;
            const orderStatus = document.getElementById('order-status').textContent;
            const paymentMethod = document.getElementById('payment-method').textContent;
            const orderTotal = document.getElementById('summary-total').textContent;

            // Get order items data
            const items = Array.from(document.querySelectorAll('.order-item')).map(item => ({
                name: item.querySelector('.item-name').textContent,
                sku: item.querySelector('.item-sku').textContent.replace('SKU: ', ''),
                quantity: item.querySelector('.item-meta span:first-child strong').textContent,
                unitPrice: item.querySelector('.item-meta span:nth-child(2) strong').textContent,
                total: item.querySelector('.item-price').textContent
            }));

            const itemsHTML = items.map(item =>
                '<tr>' +
                    '<td>' + item.name + '</td>' +
                    '<td>' + item.sku + '</td>' +
                    '<td>' + item.quantity + '</td>' +
                    '<td>' + item.unitPrice + '</td>' +
                    '<td>' + item.total + '</td>' +
                '</tr>'
            ).join('');

            return '<!DOCTYPE html>' +
                '<html>' +
                '<head>' +
                    '<title>Invoice - ' + orderNumber + '</title>' +
                    '<style>' +
                        'body { font-family: Arial, sans-serif; margin: 20px; }' +
                        '.header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }' +
                        '.company-name { font-size: 24px; font-weight: bold; }' +
                        '.invoice-details { margin-bottom: 30px; }' +
                        '.invoice-details table { width: 100%; }' +
                        '.invoice-details td { padding: 5px; }' +
                        '.items { margin-bottom: 30px; }' +
                        '.items table { width: 100%; border-collapse: collapse; }' +
                        '.items th, .items td { border: 1px solid #ddd; padding: 8px; text-align: left; }' +
                        '.items th { background-color: #f2f2f2; }' +
                        '.total { text-align: right; font-size: 18px; font-weight: bold; }' +
                        '.footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; }' +
                    '</style>' +
                '</head>' +
                '<body>' +
                    '<div class="header">' +
                        '<div class="company-name">PC Parts Central</div>' +
                        '<div>123 Tech Street, Silicon Valley, CA 94000</div>' +
                        '<div>Phone: (555) 123-4567 | Email: info@pcpartscentral.com</div>' +
                    '</div>' +
                    '<div class="invoice-details">' +
                        '<h2>Invoice ' + orderNumber + '</h2>' +
                        '<table>' +
                            '<tr>' +
                                '<td><strong>Date:</strong> ' + orderDate + '</td>' +
                                '<td><strong>Status:</strong> ' + orderStatus + '</td>' +
                            '</tr>' +
                            '<tr>' +
                                '<td><strong>Payment Method:</strong> ' + paymentMethod + '</td>' +
                                '<td><strong>Total:</strong> ' + orderTotal + '</td>' +
                            '</tr>' +
                        '</table>' +
                    '</div>' +
                    '<div class="items">' +
                        '<h3>Order Items</h3>' +
                        '<table>' +
                            '<thead>' +
                                '<tr>' +
                                    '<th>Item</th>' +
                                    '<th>SKU</th>' +
                                    '<th>Quantity</th>' +
                                    '<th>Unit Price</th>' +
                                    '<th>Total</th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>' + itemsHTML + '</tbody>' +
                        '</table>' +
                    '</div>' +
                    '<div class="total">' +
                        'Total Amount: ' + orderTotal +
                    '</div>' +
                    '<div class="footer">' +
                        '<p>Thank you for shopping with PC Parts Central!</p>' +
                        '<p>This invoice was generated on ' + new Date().toLocaleString() + '</p>' +
                    '</div>' +
                '</body>' +
                '</html>';
        }

        // Notification system
        function showNotification(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} position-fixed top-0 start-50 translate-middle-x mt-3 shadow-lg`;
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '300px';
            alertDiv.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            document.body.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 3000);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            if (orderId) {
                loadOrderDetails();
            } else {
                document.getElementById('loading-state').style.display = 'none';
                document.getElementById('error-state').classList.remove('d-none');
            }
        });
    </script>
</body>
</html>
