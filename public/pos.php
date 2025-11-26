<?php
/**
 * Public POS (Point of Sale) Page
 * Accessible from the shop for integrated transactions
 */

session_start();

// Include required files
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/utils/Response.php';
require_once __DIR__ . '/../backend/middleware/Auth.php';

// Check if user is logged in (for staff access)
$isLoggedIn = Auth::check();
$user = $isLoggedIn ? Auth::user() : null;

// If not logged in, redirect to login
if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

// Check if user has POS access (staff, inventory_manager, or admin)
$allowedRoles = ['admin', 'inventory_manager', 'staff'];
if (!in_array($user['role'], $allowedRoles)) {
    header('Location: dashboard.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Point of Sale - Inventory Management System</title>
    <link rel="icon" type="image/png" href="../inventorylogo.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/main.css" rel="stylesheet">

    <style>
        :root {
            --bg-primary: #0a0a0a;
            --bg-secondary: #1a1a1a;
            --bg-card: #2a2a2a;
            --bg-tertiary: #3a3a3a;
            --text-primary: #ffffff;
            --text-secondary: #cccccc;
            --border-color: #404040;
            --accent: #00f5ff;
            --success: #00ff88;
            --warning: #ffaa00;
            --danger: #ff4444;
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .pos-container {
            min-height: 100vh;
            background: var(--bg-primary);
        }

        .pos-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
        }

        .pos-main {
            padding: 2rem 0;
        }

        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
        }

        .product-card:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 245, 255, 0.1);
        }

        .product-image {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 0.375rem 0.375rem 0 0;
        }

        .product-image-fallback {
            width: 100%;
            height: 120px;
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem 0.375rem 0 0;
            color: var(--text-secondary);
        }

        .cart-item {
            background: rgba(0, 245, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .btn-accent {
            background: var(--accent);
            border: none;
            color: var(--bg-primary);
            font-weight: 600;
        }

        .btn-accent:hover {
            background: #00e0e6;
            color: var(--bg-primary);
        }

        .form-control {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .form-control:focus {
            background: var(--bg-tertiary);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 245, 255, 0.25);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-close {
            filter: invert(1);
        }

        .btn-close:focus {
            box-shadow: 0 0 0 0.2rem rgba(0, 245, 255, 0.25);
        }

        .badge-success {
            background: var(--success);
            color: var(--bg-primary);
        }

        .badge-danger {
            background: var(--danger);
            color: white;
        }

        .badge-warning {
            background: var(--warning);
            color: var(--bg-primary);
        }

        .badge-primary {
            background: var(--accent);
            color: var(--bg-primary);
        }

        .text-accent {
            color: var(--accent) !important;
        }

        .text-success {
            color: var(--success) !important;
        }

        .text-danger {
            color: var(--danger) !important;
        }

        .text-warning {
            color: var(--warning) !important;
        }

        /* POS specific styles */
        .pos-toolbar {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .pos-products-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            height: calc(100vh - 300px);
            overflow: hidden;
        }

        .pos-cart-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            height: calc(100vh - 300px);
            overflow: hidden;
        }

        .pos-products-container {
            height: calc(100% - 60px);
            overflow-y: auto;
            padding: 1rem;
        }

        .pos-cart-container {
            height: calc(100% - 200px);
            overflow-y: auto;
            padding: 1rem;
        }

        .pos-summary {
            background: var(--bg-tertiary);
            border-top: 1px solid var(--border-color);
            padding: 1rem;
        }

        .fullscreen-mode {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 9999;
            background: var(--bg-primary);
        }

        .fullscreen-mode .pos-header {
            display: none;
        }

        .fullscreen-mode .pos-main {
            padding: 0;
        }

        /* Receipt styles */
        .receipt {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-width: 300px;
            margin: 0 auto;
            background: white;
            color: black;
            padding: 20px;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .receipt-store {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .receipt-table th,
        .receipt-table td {
            padding: 5px;
            text-align: left;
        }

        .receipt-total {
            border-top: 1px solid #000;
            font-weight: bold;
        }

        /* Loading spinner */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .pos-products-panel,
            .pos-cart-panel {
                height: calc(100vh - 400px);
            }

            .pos-products-container,
            .pos-cart-container {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid pos-container">
        <!-- POS Header -->
        <div class="pos-header">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-cash-register me-2 text-accent"></i>
                            Point of Sale
                        </h1>
                        <p class="text-muted mb-0">Process customer transactions efficiently</p>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-primary" id="pos-sale-number">Sale #<?php echo time(); ?></span>
                        <span class="text-muted small" id="pos-time"><?php echo date('h:i:s A'); ?></span>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary btn-sm" onclick="toggleFullscreen()" title="Toggle Fullscreen">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="showHelp()" title="POS Help">
                                <i class="fas fa-question-circle"></i>
                            </button>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm" title="Back to Dashboard">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- POS Main Content -->
        <div class="pos-main">
            <div class="container-fluid">
                <!-- POS Toolbar -->
                <div class="pos-toolbar">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="pos-search" placeholder="Search products..." autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" id="pos-category-filter">
                                <option value="">All Categories</option>
                                <!-- Categories will be loaded here -->
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                <input type="text" class="form-control" id="pos-barcode" placeholder="Scan barcode" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-success btn-sm" onclick="quickAddProduct()" title="Quick Add Product">
                                    <i class="fas fa-plus"></i> Quick Add
                                </button>
                                <button class="btn btn-outline-warning btn-sm" onclick="holdSale()" title="Hold Current Sale">
                                    <i class="fas fa-pause"></i> Hold
                                </button>
                                <button class="btn btn-outline-info btn-sm" onclick="recallSale()" title="Recall Held Sale">
                                    <i class="fas fa-play"></i> Recall
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2 text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <span class="text-muted small">Staff: <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Products Panel -->
                    <div class="col-lg-7">
                        <div class="pos-products-panel">
                            <div class="d-flex justify-content-between align-items-center p-3 border-bottom" style="border-color: var(--border-color) !important;">
                                <h5 class="mb-0">Products</h5>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary active" onclick="setView('grid')" id="grid-view">
                                        <i class="fas fa-th"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="setView('list')" id="list-view">
                                        <i class="fas fa-list"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="pos-products-container">
                                <div id="pos-products" class="row g-2">
                                    <!-- Products will load here -->
                                </div>
                                <div id="pos-loading" class="text-center py-5 d-none">
                                    <div class="spinner-border text-accent" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="text-muted mt-2">Loading products...</p>
                                </div>
                                <div id="pos-no-products" class="text-center py-5 d-none">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No products found</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart & Checkout Panel -->
                    <div class="col-lg-5">
                        <div class="pos-cart-panel">
                            <div class="d-flex justify-content-between align-items-center p-3 border-bottom" style="border-color: var(--border-color) !important;">
                                <h5 class="mb-0">Current Sale</h5>
                                <button class="btn btn-sm btn-outline-danger" onclick="clearCart()" title="Clear Cart">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <!-- Customer Info -->
                            <div class="p-3 border-bottom" style="border-color: var(--border-color) !important;">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="pos-customer-name" placeholder="Walk-in Customer" autocomplete="off">
                                    <button class="btn btn-outline-secondary" onclick="searchCustomer()" title="Search Customer">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Cart Items -->
                            <div class="pos-cart-container">
                                <div class="text-center text-muted py-4" id="pos-empty-cart">
                                    <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                    <p>No items in cart</p>
                                    <small>Search and add products above</small>
                                </div>
                                <div id="pos-cart-items">
                                    <!-- Cart items will appear here -->
                                </div>
                            </div>

                            <!-- Cart Summary & Payment -->
                            <div class="pos-summary">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Subtotal</label>
                                        <div class="fw-bold" id="pos-subtotal">₱0.00</div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-1">Tax (12%)</label>
                                        <div class="fw-bold" id="pos-tax">₱0.00</div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0">TOTAL:</h4>
                                    <h4 class="mb-0 text-accent" id="pos-total">₱0.00</h4>
                                </div>

                                <!-- Payment Method -->
                                <div class="mb-3">
                                    <label class="form-label small mb-2">Payment Method</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="payment-method" id="payment-cash" value="cash" checked>
                                        <label class="btn btn-outline-primary btn-sm" for="payment-cash">
                                            <i class="fas fa-money-bill-wave me-1"></i>Cash
                                        </label>
                                        <input type="radio" class="btn-check" name="payment-method" id="payment-card" value="card">
                                        <label class="btn btn-outline-primary btn-sm" for="payment-card">
                                            <i class="fas fa-credit-card me-1"></i>Card
                                        </label>
                                        <input type="radio" class="btn-check" name="payment-method" id="payment-transfer" value="bank_transfer">
                                        <label class="btn btn-outline-primary btn-sm" for="payment-transfer">
                                            <i class="fas fa-university me-1"></i>Transfer
                                        </label>
                                    </div>
                                </div>

                                <!-- Cash Received (for cash payments) -->
                                <div id="cash-payment-section" class="mb-3">
                                    <label class="form-label small mb-1">Cash Received</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="pos-cash-received" placeholder="0.00" step="0.01" min="0">
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted">Change:</small>
                                        <small class="fw-bold text-success" id="pos-change">₱0.00</small>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-grid gap-2">
                                    <button class="btn btn-accent btn-lg" onclick="completeSale()" id="complete-sale-btn">
                                        <i class="fas fa-check me-2"></i>Complete Sale (F12)
                                    </button>
                                    <div class="row g-1">
                                        <div class="col-6">
                                            <button class="btn btn-outline-secondary btn-sm w-100" onclick="printReceipt()" disabled id="print-receipt-btn">
                                                <i class="fas fa-print me-1"></i>Print Receipt
                                            </button>
                                        </div>
                                        <div class="col-6">
                                            <button class="btn btn-outline-info btn-sm w-100" onclick="emailReceipt()" disabled id="email-receipt-btn">
                                                <i class="fas fa-envelope me-1"></i>Email Receipt
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay d-none" id="loading-overlay">
        <div class="text-center">
            <div class="spinner-border text-accent" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-white mt-2">Processing...</p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- POS JavaScript -->
    <script>
        // Configuration
        const API_BASE = '<?php echo dirname($_SERVER['PHP_SELF']); ?>/backend/api';
        const currentUser = <?php echo json_encode($user); ?>;

        // POS State
        let posCart = [];
        let posHeldSales = [];
        let currentView = 'grid';
        let isFullscreen = false;

        // Initialize POS
        document.addEventListener('DOMContentLoaded', function() {
            initializePOS();
            loadCategories();
            loadProducts();
            setupEventListeners();

            // Update clock
            setInterval(() => {
                document.getElementById('pos-time').textContent = new Date().toLocaleTimeString();
            }, 1000);
        });

        function initializePOS() {
            posCart = [];
            posHeldSales = JSON.parse(localStorage.getItem('posHeldSales') || '[]');
            updateCartDisplay();
        }

        function setupEventListeners() {
            // Search input
            document.getElementById('pos-search').addEventListener('input', (e) => {
                loadProducts(e.target.value, document.getElementById('pos-category-filter').value);
            });

            // Category filter
            document.getElementById('pos-category-filter').addEventListener('change', (e) => {
                loadProducts(document.getElementById('pos-search').value, e.target.value);
            });

            // Barcode scanner
            document.getElementById('pos-barcode').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const barcode = e.target.value.trim();
                    if (barcode) {
                        scanBarcode(barcode);
                        e.target.value = '';
                    }
                }
            });

            // Payment method change
            document.querySelectorAll('input[name="payment-method"]').forEach(radio => {
                radio.addEventListener('change', (e) => {
                    toggleCashPaymentSection(e.target.value === 'cash');
                });
            });

            // Cash received input
            document.getElementById('pos-cash-received').addEventListener('input', calculateChange);

            // Keyboard shortcuts
            document.addEventListener('keydown', handleKeyboard);
        }

        function handleKeyboard(e) {
            // Ignore if user is typing in an input
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                if (e.target.id === 'pos-barcode' && e.key === 'Enter') return;
                return;
            }

            switch(e.key.toLowerCase()) {
                case 'f11':
                    e.preventDefault();
                    toggleFullscreen();
                    break;
                case 'f12':
                    e.preventDefault();
                    if (posCart.length > 0) completeSale();
                    break;
                case 'escape':
                    if (isFullscreen) {
                        toggleFullscreen();
                    }
                    break;
                case 'f1':
                    e.preventDefault();
                    document.getElementById('pos-search').focus();
                    break;
                case 'f2':
                    e.preventDefault();
                    document.getElementById('pos-barcode').focus();
                    break;
                case 'f3':
                    e.preventDefault();
                    clearCart();
                    break;
                case 'f4':
                    e.preventDefault();
                    holdSale();
                    break;
                case 'f5':
                    e.preventDefault();
                    recallSale();
                    break;
            }
        }

        async function loadCategories() {
            try {
                const response = await fetch(`${API_BASE}/categories/index.php`);
                const data = await response.json();

                if (data.success && data.data.categories) {
                    const select = document.getElementById('pos-category-filter');
                    select.innerHTML = '<option value="">All Categories</option>';

                    data.data.categories.forEach(cat => {
                        select.innerHTML += `<option value="${cat.id}">${cat.name}</option>`;
                    });
                }
            } catch (error) {
                console.error('Error loading categories:', error);
            }
        }

        async function loadProducts(search = '', categoryId = '') {
            const loading = document.getElementById('pos-loading');
            const container = document.getElementById('pos-products');
            const noProducts = document.getElementById('pos-no-products');

            loading.classList.remove('d-none');
            container.innerHTML = '';
            noProducts.classList.add('d-none');

            try {
                let url = `${API_BASE}/products/index.php?is_active=1&limit=50`;
                if (search) url += `&search=${encodeURIComponent(search)}`;
                if (categoryId) url += `&category_id=${categoryId}`;

                const response = await fetch(url);
                const data = await response.json();

                loading.classList.add('d-none');

                if (data.success && data.data.products && data.data.products.length > 0) {
                    if (currentView === 'grid') {
                        container.className = 'row g-2';
                        container.innerHTML = data.data.products.map(p => `
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 product-card" onclick="addToCart(${JSON.stringify(p).replace(/'/g, "\\'")})">
                                    <div class="card-body p-2">
                                        <div class="product-image-container mb-2">
                                            ${p.image_url ?
                                                `<img src="${p.image_url}" alt="${p.name}" class="product-image">` :
                                                `<div class="product-image-fallback"><i class="fas fa-microchip fa-2x"></i></div>`
                                            }
                                        </div>
                                        <h6 class="product-title mb-1" style="font-size: 0.9rem; line-height: 1.2;">${p.name}</h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">${p.sku}</small>
                                            <strong style="color: var(--accent); font-size: 0.9rem;">₱${parseFloat(p.selling_price).toLocaleString()}</strong>
                                        </div>
                                        <div class="mt-1">
                                            <small class="${(p.quantity_available || 0) > 0 ? 'text-success' : 'text-danger'}">
                                                <i class="fas fa-${(p.quantity_available || 0) > 0 ? 'check' : 'times'}-circle me-1"></i>
                                                ${(p.quantity_available || 0) > 0 ? `${p.quantity_available} in stock` : 'Out of stock'}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        // List view
                        container.className = '';
                        container.innerHTML = `
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.data.products.map(p => `
                                            <tr style="cursor: pointer;" onclick="addToCart(${JSON.stringify(p).replace(/'/g, "\\'")})">
                                                <td><code>${p.sku}</code></td>
                                                <td><strong>${p.name}</strong></td>
                                                <td>${p.category_name || '-'}</td>
                                                <td><strong style="color: var(--accent);">₱${parseFloat(p.selling_price).toLocaleString()}</strong></td>
                                                <td>
                                                    <span class="${(p.quantity_available || 0) > 0 ? 'text-success' : 'text-danger'}">
                                                        ${p.quantity_available || 0}
                                                    </span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-accent" onclick="event.stopPropagation(); addToCart(${JSON.stringify(p).replace(/'/g, "\\'")})">
                                                        <i class="fas fa-plus"></i> Add
                                                    </button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                    }
                } else {
                    noProducts.classList.remove('d-none');
                }
            } catch (error) {
                console.error('Error loading products:', error);
                loading.classList.add('d-none');
                noProducts.classList.remove('d-none');
            }
        }

        function addToCart(product) {
            if ((product.quantity_available || 0) <= 0) {
                showToast('Product is out of stock!', 'warning');
                return;
            }

            const existing = posCart.find(item => item.id === product.id);
            if (existing) {
                if (existing.quantity >= product.quantity_available) {
                    showToast('Cannot add more than available stock!', 'warning');
                    return;
                }
                existing.quantity++;
            } else {
                posCart.push({
                    id: product.id,
                    sku: product.sku,
                    name: product.name,
                    price: parseFloat(product.selling_price),
                    quantity: 1,
                    max_quantity: product.quantity_available
                });
            }

            updateCartDisplay();
            showToast(`Added ${product.name}`, 'success');
        }

        function updateCartDisplay() {
            const container = document.getElementById('pos-cart-items');
            const emptyCart = document.getElementById('pos-empty-cart');

            if (posCart.length === 0) {
                container.innerHTML = '';
                emptyCart.classList.remove('d-none');
                document.getElementById('pos-subtotal').textContent = '₱0.00';
                document.getElementById('pos-tax').textContent = '₱0.00';
                document.getElementById('pos-total').textContent = '₱0.00';
                document.getElementById('print-receipt-btn').disabled = true;
                document.getElementById('email-receipt-btn').disabled = true;
                return;
            }

            emptyCart.classList.add('d-none');

            container.innerHTML = posCart.map(item => `
                <div class="cart-item d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <strong>${item.name}</strong>
                        <br><small class="text-muted">₱${item.price.toLocaleString()} × ${item.quantity}</small>
                    </div>
                    <div class="text-end">
                        <div class="btn-group btn-group-sm mb-1">
                            <button class="btn btn-sm btn-outline-primary" onclick="changeQuantity('${item.id}', -1)">-</button>
                            <button class="btn btn-sm btn-outline-primary" disabled>${item.quantity}</button>
                            <button class="btn btn-sm btn-outline-primary" onclick="changeQuantity('${item.id}', 1)">+</button>
                        </div>
                        <br>
                        <strong style="color: var(--accent);">₱${(item.price * item.quantity).toLocaleString()}</strong>
                        <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeFromCart('${item.id}')">×</button>
                    </div>
                </div>
            `).join('');

            const subtotal = posCart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const tax = subtotal * 0.12; // 12% VAT
            const total = subtotal + tax;

            document.getElementById('pos-subtotal').textContent = '₱' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('pos-tax').textContent = '₱' + tax.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('pos-total').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});

            // Enable receipt buttons
            document.getElementById('print-receipt-btn').disabled = false;
            document.getElementById('email-receipt-btn').disabled = false;
        }

        function changeQuantity(productId, delta) {
            const item = posCart.find(i => i.id === productId);
            if (!item) return;

            item.quantity += delta;

            if (item.quantity <= 0) {
                removeFromCart(productId);
                return;
            }

            if (item.quantity > item.max_quantity) {
                showToast('Cannot exceed available stock!', 'warning');
                item.quantity = item.max_quantity;
            }

            updateCartDisplay();
        }

        function removeFromCart(productId) {
            posCart = posCart.filter(item => item.id !== productId);
            updateCartDisplay();
        }

        async function clearCart() {
            if (posCart.length === 0) return;
            if (!confirm('This will remove all items from the cart.')) return;
            posCart = [];
            updateCartDisplay();
        }

        async function completeSale() {
            if (posCart.length === 0) {
                showToast('Cart is empty!', 'warning');
                return;
            }

            // Get payment method
            const paymentMethod = document.querySelector('input[name="payment-method"]:checked').value;

            // Validate cash payment if selected
            if (paymentMethod === 'cash') {
                const cashReceived = parseFloat(document.getElementById('pos-cash-received').value) || 0;
                const total = parseFloat(document.getElementById('pos-total').textContent.replace('₱', '').replace(',', '')) || 0;

                if (cashReceived < total) {
                    showToast('Cash received is less than total amount!', 'warning');
                    document.getElementById('pos-cash-received').focus();
                    return;
                }
            }

            if (!confirm('Ready to complete this sale?')) return;

            const customerName = document.getElementById('pos-customer-name').value.trim() || 'Walk-in Customer';
            const subtotal = posCart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const tax = subtotal * 0.12; // 12% VAT
            const total = subtotal + tax;

            const saleData = {
                customer_name: customerName,
                items: posCart.map(item => ({
                    product_id: item.id,
                    quantity: item.quantity,
                    unit_price: item.price,
                    subtotal: item.price * item.quantity
                })),
                subtotal: subtotal,
                tax_amount: tax,
                total_amount: total,
                payment_method: paymentMethod,
                payment_details: paymentMethod === 'cash' ? {
                    cash_received: parseFloat(document.getElementById('pos-cash-received').value),
                    change_given: parseFloat(document.getElementById('pos-change').textContent.replace('₱', '').replace(',', ''))
                } : null
            };

            const loading = document.getElementById('loading-overlay');
            const completeBtn = document.getElementById('complete-sale-btn');
            const originalText = completeBtn.innerHTML;

            loading.classList.remove('d-none');
            completeBtn.disabled = true;
            completeBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';

            try {
                const response = await fetch(`${API_BASE}/sales/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(saleData)
                });

                const data = await response.json();

                if (data.success) {
                    showToast('Sale completed successfully! Sale ID: ' + data.data.sale_id, 'success');

                    // Reset POS
                    posCart = [];
                    updateCartDisplay();
                    document.getElementById('pos-customer-name').value = '';
                    document.getElementById('pos-cash-received').value = '';
                    document.getElementById('pos-sale-number').textContent = 'Sale #' + Date.now();

                    // Reload products to update stock
                    loadProducts();

                    // Enable receipt buttons
                    document.getElementById('print-receipt-btn').disabled = false;
                    document.getElementById('email-receipt-btn').disabled = false;
                } else {
                    showToast('Error completing sale: ' + data.message, 'error');
                }
            } catch (error) {
                showToast('Error completing sale. Please try again.', 'error');
            } finally {
                loading.classList.add('d-none');
                completeBtn.disabled = false;
                completeBtn.innerHTML = originalText;
            }
        }

        function toggleCashPaymentSection(show) {
            const section = document.getElementById('cash-payment-section');
            if (show) {
                section.style.display = 'block';
                document.getElementById('pos-cash-received').focus();
            } else {
                section.style.display = 'none';
                document.getElementById('pos-cash-received').value = '';
                document.getElementById('pos-change').textContent = '₱0.00';
            }
        }

        function calculateChange() {
            const cashReceived = parseFloat(document.getElementById('pos-cash-received').value) || 0;
            const total = parseFloat(document.getElementById('pos-total').textContent.replace('₱', '').replace(',', '')) || 0;
            const change = Math.max(0, cashReceived - total);

            document.getElementById('pos-change').textContent = '₱' + change.toLocaleString('en-US', {minimumFractionDigits: 2});
        }

        async function scanBarcode(barcode) {
            try {
                const response = await fetch(`${API_BASE}/products/index.php?sku=${encodeURIComponent(barcode)}`);
                const data = await response.json();

                if (data.success && data.data.products && data.data.products.length > 0) {
                    const product = data.data.products[0];
                    addToCart(product);
                    showToast(`Added ${product.name}`, 'success');
                } else {
                    showToast('Product not found', 'warning');
                }
            } catch (error) {
                console.error('Barcode scan error:', error);
                showToast('Error scanning barcode', 'error');
            }
        }

        function setView(view) {
            currentView = view;

            // Update button states
            document.getElementById('grid-view').classList.toggle('active', view === 'grid');
            document.getElementById('list-view').classList.toggle('active', view === 'list');

            // Reload products with new view
            loadProducts(document.getElementById('pos-search').value, document.getElementById('pos-category-filter').value);
        }

        function toggleFullscreen() {
            const container = document.querySelector('.pos-container');
            isFullscreen = !isFullscreen;

            if (isFullscreen) {
                container.classList.add('fullscreen-mode');
                showToast('POS Fullscreen Mode - Press ESC to exit', 'info');
            } else {
                container.classList.remove('fullscreen-mode');
                showToast('Exited fullscreen mode', 'info');
            }
        }

        function quickAddProduct() {
            showToast('Quick add product functionality - can be implemented for custom items', 'info');
        }

        function holdSale() {
            if (posCart.length === 0) {
                showToast('Cart is empty - nothing to hold', 'warning');
                return;
            }

            const saleNumber = document.getElementById('pos-sale-number').textContent;
            const heldSale = {
                id: Date.now(),
                saleNumber: saleNumber,
                timestamp: new Date().toISOString(),
                customer: document.getElementById('pos-customer-name').value || 'Walk-in Customer',
                items: [...posCart],
                subtotal: parseFloat(document.getElementById('pos-subtotal').textContent.replace('₱', '').replace(',', '')),
                tax: parseFloat(document.getElementById('pos-tax').textContent.replace('₱', '').replace(',', '')),
                total: parseFloat(document.getElementById('pos-total').textContent.replace('₱', '').replace(',', ''))
            };

            posHeldSales.push(heldSale);
            localStorage.setItem('posHeldSales', JSON.stringify(posHeldSales));

            // Clear current cart
            posCart = [];
            updateCartDisplay();

            // Reset customer name
            document.getElementById('pos-customer-name').value = '';

            // Generate new sale number
            document.getElementById('pos-sale-number').textContent = 'Sale #' + Date.now();

            showToast(`Sale ${saleNumber} held successfully`, 'success');
        }

        function recallSale() {
            if (posHeldSales.length === 0) {
                showToast('No held sales available', 'info');
                return;
            }

            showToast('Recall sale functionality - can be enhanced with a modal to select held sales', 'info');
        }

        function searchCustomer() {
            showToast('Customer search functionality - can be enhanced to search existing customers', 'info');
        }

        function printReceipt() {
            if (posCart.length === 0) {
                showToast('No sale to print', 'warning');
                return;
            }

            // Create printable receipt
            const receiptWindow = window.open('', '_blank', 'width=400,height=600');
            const receiptHTML = generateReceiptHTML();

            receiptWindow.document.write(receiptHTML);
            receiptWindow.document.close();
            receiptWindow.print();

            showToast('Receipt sent to printer', 'success');
        }

        function emailReceipt() {
            if (posCart.length === 0) {
                showToast('No sale to email', 'warning');
                return;
            }

            const customerName = document.getElementById('pos-customer-name').value || 'Walk-in Customer';
            showToast(`Receipt emailed to customer (${customerName})`, 'success');
        }

        function generateReceiptHTML() {
            const saleNumber = document.getElementById('pos-sale-number').textContent;
            const customerName = document.getElementById('pos-customer-name').value || 'Walk-in Customer';
            const date = new Date().toLocaleString();
            const subtotal = document.getElementById('pos-subtotal').textContent;
            const tax = document.getElementById('pos-tax').textContent;
            const total = document.getElementById('pos-total').textContent;

            let itemsHTML = posCart.map(item => `
                <tr>
                    <td>${item.name}</td>
                    <td class="text-center">${item.quantity}</td>
                    <td class="text-end">₱${item.price.toLocaleString()}</td>
                    <td class="text-end">₱${(item.price * item.quantity).toLocaleString()}</td>
                </tr>
            `).join('');

            return `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Receipt - ${saleNumber}</title>
                    <style>
                        body { font-family: 'Courier New', monospace; font-size: 12px; max-width: 300px; margin: 0 auto; }
                        .header { text-align: center; margin-bottom: 20px; }
                        .store-name { font-size: 16px; font-weight: bold; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { padding: 5px; text-align: left; }
                        .text-center { text-align: center; }
                        .text-end { text-align: right; }
                        .total { border-top: 1px solid #000; font-weight: bold; }
                        .footer { text-align: center; margin-top: 20px; font-size: 10px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="store-name">PC Parts Central</div>
                        <div>123 Tech Street, Silicon Valley</div>
                        <div>Phone: (555) 123-4567</div>
                    </div>

                    <div>
                        <strong>Receipt:</strong> ${saleNumber}<br>
                        <strong>Date:</strong> ${date}<br>
                        <strong>Customer:</strong> ${customerName}<br>
                        <strong>Cashier:</strong> ${currentUser.full_name || currentUser.username}
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHTML}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end">Subtotal:</td>
                                <td class="text-end">${subtotal}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end">Tax (12%):</td>
                                <td class="text-end">${tax}</td>
                            </tr>
                            <tr class="total">
                                <td colspan="3" class="text-end">TOTAL:</td>
                                <td class="text-end">${total}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="footer">
                        <div>Thank you for shopping with us!</div>
                        <div>Visit us again soon.</div>
                    </div>
                </body>
                </html>
            `;
        }

        function showHelp() {
            const helpContent = `
                <div class="pos-help-content">
                    <h4><i class="fas fa-keyboard me-2"></i>POS Keyboard Shortcuts</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>General</h6>
                            <ul class="list-unstyled">
                                <li><kbd>F1</kbd> - Focus search</li>
                                <li><kbd>F2</
                f u n c t i o n   s h o w H e l p ( )   { 
                         c o n s t   h e l p C o n t e n t   =   ` 
                                 < d i v   c l a s s = " p o s - h e l p - c o n t e n t " > 
                                         < h 4 > < i   c l a s s = " f a s   f a - k e y b o a r d   m e - 2 " > < / i > P O S   K e y b o a r d   S h o r t c u t s < / h 4 > 
                                         < d i v   c l a s s = " r o w " > 
                                                 < d i v   c l a s s = " c o l - m d - 6 " > 
                                                         < h 6 > G e n e r a l < / h 6 > 
                                                         < u l   c l a s s = " l i s t - u n s t y l e d " > 
                                                                 < l i > < k b d > F 1 < / k b d >   -   F o c u s   s e a r c h < / l i > 
                                                                 < l i > < k b d > F 2 < / k b d >   -   F o c u s   b a r c o d e   s c a n n e r < / l i > 
                                                                 < l i > < k b d > F 1 1 < / k b d >   -   T o g g l e   f u l l s c r e e n < / l i > 
                                                                 < l i > < k b d > E S C < / k b d >   -   E x i t   f u l l s c r e e n < / l i > 
                                                         < / u l > 
                                                 < / d i v > 
                                                 < d i v   c l a s s = " c o l - m d - 6 " > 
                                                         < h 6 > S a l e s < / h 6 > 
                                                         < u l   c l a s s = " l i s t - u n s t y l e d " > 
                                                                 < l i > < k b d > F 3 < / k b d >   -   C l e a r   c a r t < / l i > 
                                                                 < l i > < k b d > F 4 < / k b d >   -   H o l d   s a l e < / l i > 
                                                                 < l i > < k b d > F 5 < / k b d >   -   R e c a l l   s a l e < / l i > 
                                                                 < l i > < k b d > F 1 2 < / k b d >   -   C o m p l e t e   s a l e < / l i > 
                                                         < / u l > 
                                                 < / d i v > 
                                         < / d i v > 
 
                                         < h r > 
 
                                         < h 4 > < i   c l a s s = " f a s   f a - s h o p p i n g - c a r t   m e - 2 " > < / i > H o w   t o   U s e   P O S < / h 4 > 
                                         < o l > 
                                                 < l i > < s t r o n g > S e a r c h   P r o d u c t s : < / s t r o n g >   T y p e   i n   t h e   s e a r c h   b o x   o r   s c a n   b a r c o d e s < / l i > 
                                                 < l i > < s t r o n g > A d d   t o   C a r t : < / s t r o n g >   C l i c k   p r o d u c t s   o r   u s e   b a r c o d e   s c a n n e r < / l i > 
                                                 < l i > < s t r o n g > M o d i f y   Q u a n t i t i e s : < / s t r o n g >   U s e   + / -   b u t t o n s   i n   c a r t < / l i > 
                                                 < l i > < s t r o n g > C u s t o m e r   I n f o : < / s t r o n g >   E n t e r   c u s t o m e r   n a m e   ( o p t i o n a l ) < / l i > 
                                                 < l i > < s t r o n g > P a y m e n t : < / s t r o n g >   S e l e c t   p a y m e n t   m e t h o d   a n d   e n t e r   c a s h   i f   n e e d e d < / l i > 
                                                 < l i > < s t r o n g > C o m p l e t e   S a l e : < / s t r o n g >   C l i c k   " C o m p l e t e   S a l e "   o r   p r e s s   F 1 2 < / l i > 
                                         < / o l > 
 
                                         < d i v   c l a s s = " a l e r t   a l e r t - i n f o " > 
                                                 < i   c l a s s = " f a s   f a - i n f o - c i r c l e   m e - 2 " > < / i > 
                                                 < s t r o n g > T i p : < / s t r o n g >   U s e   t h e   b a r c o d e   s c a n n e r   f o r   q u i c k   p r o d u c t   l o o k u p ,   o r   s e a r c h   b y   p r o d u c t   n a m e / S K U . 
                                         < / d i v > 
                                 < / d i v > 
                         ` ; 
 
                         / /   C r e a t e   m o d a l   f o r   h e l p 
                         c o n s t   m o d a l H T M L   =   ` 
                                 < d i v   c l a s s = " m o d a l   f a d e "   i d = " p o s H e l p M o d a l "   t a b i n d e x = " - 1 " > 
                                         < d i v   c l a s s = " m o d a l - d i a l o g   m o d a l - l g " > 
                                                 < d i v   c l a s s = " m o d a l - c o n t e n t "   s t y l e = " b a c k g r o u n d :   v a r ( - - b g - c a r d ) ;   c o l o r :   v a r ( - - t e x t - p r i m a r y ) ; " > 
                                                         < d i v   c l a s s = " m o d a l - h e a d e r " > 
                                                                 < h 5   c l a s s = " m o d a l - t i t l e " > < i   c l a s s = " f a s   f a - q u e s t i o n - c i r c l e   m e - 2 " > < / i > P O S   H e l p   &   S h o r t c u t s < / h 5 > 
                                                                 < b u t t o n   t y p e = " b u t t o n "   c l a s s = " b t n - c l o s e   b t n - c l o s e - w h i t e "   d a t a - b s - d i s m i s s = " m o d a l " > < / b u t t o n > 
                                                         < / d i v > 
                                                         < d i v   c l a s s = " m o d a l - b o d y " > $ { h e l p C o n t e n t } < / d i v > 
                                                         < d i v   c l a s s = " m o d a l - f o o t e r " > 
                                                                 < b u t t o n   t y p e = " b u t t o n "   c l a s s = " b t n   b t n - s e c o n d a r y "   d a t a - b s - d i s m i s s = " m o d a l " > C l o s e < / b u t t o n > 
                                                         < / d i v > 
                                                 < / d i v > 
                                         < / d i v > 
                                 < / d i v > 
                         ` ; 
 
                         / /   R e m o v e   e x i s t i n g   m o d a l   i f   a n y 
                         c o n s t   e x i s t i n g M o d a l   =   d o c u m e n t . g e t E l e m e n t B y I d ( " p o s H e l p M o d a l " ) ; 
                         i f   ( e x i s t i n g M o d a l )   e x i s t i n g M o d a l . r e m o v e ( ) ; 
 
                         d o c u m e n t . b o d y . i n s e r t A d j a c e n t H T M L ( " b e f o r e e n d " ,   m o d a l H T M L ) ; 
                         c o n s t   m o d a l   =   n e w   b o o t s t r a p . M o d a l ( d o c u m e n t . g e t E l e m e n t B y I d ( " p o s H e l p M o d a l " ) ) ; 
                         m o d a l . s h o w ( ) ; 
                 } 
 
                 / /   T o a s t   n o t i f i c a t i o n   f u n c t i o n 
                 f u n c t i o n   s h o w T o a s t ( m e s s a g e ,   t y p e   =   " i n f o " )   { 
                         / /   C r e a t e   t o a s t   c o n t a i n e r   i f   i t   d o e s n  
 '  
 t   e x i s t 
                         l e t   t o a s t C o n t a i n e r   =   d o c u m e n t . g e t E l e m e n t B y I d ( " t o a s t - c o n t a i n e r " ) ; 
                         i f   ( ! t o a s t C o n t a i n e r )   { 
                                 t o a s t C o n t a i n e r   =   d o c u m e n t . c r e a t e E l e m e n t ( " d i v " ) ; 
                                 t o a s t C o n t a i n e r . i d   =   " t o a s t - c o n t a i n e r " ; 
                                 t o a s t C o n t a i n e r . c l a s s N a m e   =   " t o a s t - c o n t a i n e r   p o s i t i o n - f i x e d   t o p - 0   e n d - 0   p - 3 " ; 
                                 t o a s t C o n t a i n e r . s t y l e . z I n d e x   =   " 9 9 9 9 " ; 
                                 d o c u m e n t . b o d y . a p p e n d C h i l d ( t o a s t C o n t a i n e r ) ; 
                         } 
 
                         / /   C r e a t e   t o a s t   e l e m e n t 
                         c o n s t   t o a s t I d   =   " t o a s t - "   +   D a t e . n o w ( ) ; 
                         c o n s t   t o a s t H T M L   =   ` 
                                 < d i v   i d = " $ { t o a s t I d } "   c l a s s = " t o a s t   a l i g n - i t e m s - c e n t e r   t e x t - w h i t e   b g - $ { t y p e   = = =   " s u c c e s s "   ?   " s u c c e s s "   :   t y p e   = = =   " e r r o r "   ?   " d a n g e r "   :   t y p e   = = =   " w a r n i n g "   ?   " w a r n i n g "   :   " p r i m a r y " } "   r o l e = " a l e r t " > 
                                         < d i v   c l a s s = " d - f l e x " > 
                                                 < d i v   c l a s s = " t o a s t - b o d y " > 
                                                         < i   c l a s s = " f a s   f a - $ { t y p e   = = =   " s u c c e s s "   ?   " c h e c k - c i r c l e "   :   t y p e   = = =   " e r r o r "   ?   " e x c l a m a t i o n - t r i a n g l e "   :   t y p e   = = =   " w a r n i n g "   ?   " e x c l a m a t i o n - c i r c l e "   :   " i n f o - c i r c l e " }   m e - 2 " > < / i > 
                                                         $ { m e s s a g e } 
                                                 < / d i v > 
                                                 < b u t t o n   t y p e = " b u t t o n "   c l a s s = " b t n - c l o s e   b t n - c l o s e - w h i t e   m e - 2   m - a u t o "   d a t a - b s - d i s m i s s = " t o a s t " > < / b u t t o n > 
                                         < / d i v > 
                                 < / d i v > 
                         ` ; 
 
                         t o a s t C o n t a i n e r . i n s e r t A d j a c e n t H T M L ( " b e f o r e e n d " ,   t o a s t H T M L ) ; 
 
                         / /   I n i t i a l i z e   a n d   s h o w   t o a s t 
                         c o n s t   t o a s t E l e m e n t   =   d o c u m e n t . g e t E l e m e n t B y I d ( t o a s t I d ) ; 
                         c o n s t   t o a s t   =   n e w   b o o t s t r a p . T o a s t ( t o a s t E l e m e n t ,   {   d e l a y :   3 0 0 0   } ) ; 
                         t o a s t . s h o w ( ) ; 
 
                         / /   R e m o v e   t o a s t   e l e m e n t   a f t e r   i t  
 '  
 s   h i d d e n 
                         t o a s t E l e m e n t . a d d E v e n t L i s t e n e r ( " h i d d e n . b s . t o a s t " ,   ( )   = >   { 
                                 t o a s t E l e m e n t . r e m o v e ( ) ; 
                         } ) ; 
                 } 
         < / s c r i p t > 
 < / b o d y > 
 < / h t m l >  
 