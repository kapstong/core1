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

// Check if user has POS access (ONLY staff members can manage customer orders)
$allowedRoles = ['staff'];
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
        /* POS page inherits colors from main.css
           Dashboard color scheme is used for consistency */

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

        /* Tab Navigation Styling */
        .nav-tabs .nav-link {
            background: transparent;
            border: 1px solid transparent;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }

        .nav-tabs .nav-link:hover {
            color: var(--accent);
            border-color: transparent;
            background: rgba(0, 245, 255, 0.05);
        }

        .nav-tabs .nav-link.active {
            background: var(--bg-secondary);
            border-color: var(--border-color) var(--border-color) transparent;
            color: var(--accent);
        }

        .tab-content {
            background: transparent;
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
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-3" role="tablist" style="border-bottom: 1px solid var(--border-color);">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pos-sales-tab" data-bs-toggle="tab" data-bs-target="#pos-sales-panel" type="button" role="tab">
                            <i class="fas fa-cash-register me-2"></i>POS Sales
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pending-orders-tab" data-bs-toggle="tab" data-bs-target="#pending-orders-panel" type="button" role="tab" onclick="loadPendingOrders()">
                            <i class="fas fa-clock me-2"></i>Pending Orders
                            <span class="badge bg-warning text-dark ms-2" id="pending-orders-count">0</span>
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- POS Sales Tab -->
                    <div class="tab-pane fade show active" id="pos-sales-panel" role="tabpanel">
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
                    <!-- End POS Sales Tab -->

                    <!-- Pending Orders Tab -->
                    <div class="tab-pane fade" id="pending-orders-panel" role="tabpanel">
                        <div class="row">
                            <div class="col-12">
                                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                                    <div class="card-header d-flex justify-content-between align-items-center" style="background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);">
                                        <h5 class="mb-0">
                                            <i class="fas fa-clock me-2 text-warning"></i>
                                            Pending Customer Orders
                                        </h5>
                                        <button class="btn btn-sm btn-outline-primary" onclick="loadPendingOrders()">
                                            <i class="fas fa-sync me-1"></i>Refresh
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div id="pending-orders-loading" class="text-center py-5 d-none">
                                            <div class="spinner-border text-accent" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p class="text-muted mt-2">Loading pending orders...</p>
                                        </div>

                                        <div id="pending-orders-empty" class="text-center py-5 d-none">
                                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                            <h5>No Pending Orders</h5>
                                            <p class="text-muted">All customer orders have been processed</p>
                                        </div>

                                        <div id="pending-orders-list" class="row g-3">
                                            <!-- Pending orders will load here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End Pending Orders Tab -->
                </div>
                <!-- End Tab Content -->
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt me-2"></i>Order Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="order-details-content">
                    <!-- Order details will load here -->
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="reject-order-btn" onclick="rejectOrder()">
                        <i class="fas fa-times me-1"></i>Reject Order
                    </button>
                    <button type="button" class="btn btn-success" id="approve-order-btn" onclick="approveOrder()">
                        <i class="fas fa-check me-1"></i>Approve Order
                    </button>
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

    <!-- Pending Orders JavaScript -->
    <script src="assets/js/pos-pending-orders.js"></script>

    <!-- POS JavaScript -->
    <script>
        // Configuration
        const API_BASE = '../backend/api';
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
            showToast('Receipt emailed to ' + customerName, 'success');
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
                                <td colspan="3" class="text-end">Tax:</td>
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
            alert('POS Help: Use F1 for search, F12 to complete sale, F3 to clear cart');
        }
    </script>
</body>
</html>
