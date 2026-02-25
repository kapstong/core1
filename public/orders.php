<?php
// Customer Orders Page
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
    header('Location: login.php?redirect=orders.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - PC Parts Central</title>
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

        .orders-header {
            background: linear-gradient(135deg, rgba(0, 102, 255, 0.1), rgba(0, 245, 255, 0.1));
            border-bottom: 1px solid var(--border-color);
            padding: 3rem 0 2rem;
            margin-bottom: 3rem;
        }

        .orders-container {
            padding: 0 0 4rem;
        }

        .order-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            border-color: var(--accent);
            box-shadow: 0 0 30px rgba(0, 245, 255, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .order-number {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--accent);
        }

        .order-date {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .order-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .status-completed, .status-success {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        /* Legacy status styles for backward compatibility */
        .status-confirmed, .status-processing, .status-shipped, .status-delivered {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .order-items {
            margin-top: 1rem;
        }

        .order-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.1);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item-name {
            flex: 1;
            font-weight: 500;
        }

        .order-item-quantity {
            color: var(--text-secondary);
            margin: 0 1rem;
        }

        .order-item-price {
            font-weight: 600;
            color: var(--accent);
        }

        .order-total {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid var(--border-color);
            font-size: 1.2rem;
            font-weight: 700;
        }

        .order-total-label {
            margin-right: 1rem;
            color: var(--text-secondary);
        }

        .order-total-amount {
            color: var(--accent);
        }

        .btn-view-details {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-view-details:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 245, 255, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .loading {
            text-align: center;
            padding: 4rem 2rem;
        }

        .spinner {
            width: 50px;
            height: 50px;
            margin: 0 auto 1rem;
            border: 4px solid rgba(0, 245, 255, 0.2);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .order-item {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="ppc.png" alt="PC Parts Central Logo" style="height: 32px; width: auto; margin-right: 8px;">
                <span style="font-weight: 700;">PC Parts Central</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
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

    <!-- Orders Header -->
    <div class="orders-header">
        <div class="container">
            <div class="text-center">
                <h1 style="font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;">
                    <i class="fas fa-receipt me-2"></i>My Orders
                </h1>
                <p style="color: var(--text-secondary);">Track and manage your purchases</p>
            </div>
        </div>
    </div>

    <!-- Orders Container -->
    <div class="container orders-container">
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p style="color: var(--text-secondary);">Loading your orders...</p>
        </div>

        <div id="orders-content" style="display: none;"></div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const API_BASE = '../backend/api';

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

        // Load orders
        async function loadOrders() {
            try {
                const response = await fetch(`${API_BASE}/shop/orders.php?action=list`);
                const data = await response.json();

                console.log('Orders API response:', data);

                document.getElementById('loading').style.display = 'none';
                document.getElementById('orders-content').style.display = 'block';

                if (data.success && data.data && data.data.orders && data.data.orders.length > 0) {
                    console.log('Rendering', data.data.orders.length, 'orders');
                    renderOrders(data.data.orders);
                } else {
                    console.log('No orders found or error:', data);
                    renderEmptyState();
                }
            } catch (error) {
                console.error('Error loading orders:', error);
                document.getElementById('loading').style.display = 'none';
                document.getElementById('orders-content').style.display = 'block';
                document.getElementById('orders-content').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Failed to load orders. Please try again later.
                    </div>
                `;
            }
        }

        // Render orders
        function renderOrders(orders) {
            const content = orders.map(order => {
                const statusClass = `status-${order.status.toLowerCase()}`;
                const totalAmount = parseFloat(order.total_amount);

                return `
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-number">${order.order_number}</div>
                                <div class="order-date">
                                    <i class="far fa-calendar me-1"></i>
                                    ${new Date(order.order_date).toLocaleDateString('en-US', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric'
                                    })}
                                </div>
                            </div>
                            <div class="order-status ${statusClass}">
                                ${order.status}
                            </div>
                        </div>

                        <div class="order-items">
                            ${order.items ? order.items.map(item => `
                                <div class="order-item">
                                    <div class="order-item-name">${item.product_name}</div>
                                    <div class="order-item-quantity">x${item.quantity}</div>
                                    <div class="order-item-price">₱${parseFloat(item.unit_price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                                </div>
                            `).join('') : ''}
                        </div>

                        <div class="order-total">
                            <span class="order-total-label">Total:</span>
                            <span class="order-total-amount">₱${totalAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>

                        <div class="text-end mt-3">
                            <button class="btn-view-details" onclick="viewOrderDetails('${order.id}')">
                                <i class="fas fa-eye me-2"></i>View Details
                            </button>
                        </div>
                    </div>
                `;
            }).join('');

            document.getElementById('orders-content').innerHTML = content;
        }

        // Render empty state
        function renderEmptyState() {
            document.getElementById('orders-content').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Orders Yet</h3>
                    <p>You haven't placed any orders. Start shopping to see your orders here!</p>
                    <a href="index.php" class="btn-view-details">
                        <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                    </a>
                </div>
            `;
        }

        // View order details
        function viewOrderDetails(orderId) {
            window.location.href = `order-details.php?id=${orderId}`;
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
            loadCustomerName();
            loadOrders();
        });
    </script>
<?php include __DIR__ . '/includes/shop-chatbot.php'; ?>
</body>
</html>
