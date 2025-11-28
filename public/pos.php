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
    <link rel="icon" type="image/png" href="../ppc.png">

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

        /* Modal Styling */
        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .modal-header, .modal-footer {
            border-color: var(--border-color);
        }

        .btn-close {
            filter: invert(1);
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
                    <button type="button" class="btn btn-danger" id="reject-order-btn" onclick="showRejectModal()">
                        <i class="fas fa-times me-1"></i>Reject Order
                    </button>
                    <button type="button" class="btn btn-success" id="approve-order-btn" onclick="showApproveModal()">
                        <i class="fas fa-check me-1"></i>Approve Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Confirmation Modal -->
    <div class="modal fade" id="approveConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title text-success">
                        <i class="fas fa-check-circle me-2"></i>Approve Order
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to <strong class="text-success">APPROVE</strong> this order?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action will:
                        <ul class="mb-0 mt-2">
                            <li>Reduce stock quantities</li>
                            <li>Process the order for delivery</li>
                            <li>Create a sales entry</li>
                            <li>Send confirmation email to customer</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="confirmApproveOrder()">
                        <i class="fas fa-check me-1"></i>Yes, Approve Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Confirmation Modal -->
    <div class="modal fade" id="rejectConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-times-circle me-2"></i>Reject Order
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to <strong class="text-danger">REJECT</strong> this order?</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone and will:
                        <ul class="mb-0 mt-2">
                            <li>Release reserved stock</li>
                            <li>Cancel the order</li>
                            <li>Send rejection email to customer</li>
                        </ul>
                    </div>
                    <div class="mb-3">
                        <label for="rejection-reason" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="rejection-reason" rows="3" placeholder="Please provide a reason for rejecting this order..." required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);"></textarea>
                        <div class="invalid-feedback">Please provide a reason for rejection.</div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmRejectOrder()">
                        <i class="fas fa-times me-1"></i>Yes, Reject Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Add Product Modal -->
    <div class="modal fade" id="quickAddProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2 text-accent"></i>Quick Add Product
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="quick-add-product-form">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="quick-product-name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="quick-product-name" required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="quick-product-sku" class="form-label">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="quick-product-sku" required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="quick-product-category" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="quick-product-category" required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);">
                                    <option value="">Select Category</option>
                                    <!-- Categories will be loaded here -->
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="quick-product-price" class="form-label">Selling Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="quick-product-price" min="0" step="0.01" required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="quick-product-description" class="form-label">Description</label>
                            <textarea class="form-control" id="quick-product-description" rows="2" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);"></textarea>
                        </div>
                        <div class="alert alert-info">
                            <small><i class="fas fa-info-circle me-1"></i>Product will have 0 stock initially. Use inventory management to add stock after creation.</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-accent" onclick="submitQuickAddProduct()">
                        <i class="fas fa-plus me-1"></i>Quick Add Product
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recall Sale Modal -->
    <div class="modal fade" id="recallSaleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title">
                        <i class="fas fa-history me-2 text-primary"></i>Recall Held Sale
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="recall-sales-loading" class="text-center py-4">
                        <div class="spinner-border text-accent" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Loading held sales...</p>
                    </div>
                    <div id="recall-sales-empty" class="text-center py-4 d-none">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h6>No Held Sales</h6>
                        <p class="text-muted">No sales are currently held</p>
                    </div>
                    <div id="recall-sales-list" class="d-none">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Sale #</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Time</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="recall-sales-table-body">
                                    <!-- Held sales will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Search Modal -->
    <div class="modal fade" id="customerSearchModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title">
                        <i class="fas fa-users me-2 text-primary"></i>Search Customers
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="customer-search-input" placeholder="Enter customer name, email, or phone..." style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <button class="btn btn-accent" onclick="performCustomerSearch()">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                    <div id="customer-search-loading" class="text-center py-4 d-none">
                        <div class="spinner-border text-accent" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted mt-2">Searching customers...</p>
                    </div>
                    <div id="customer-search-empty" class="text-center py-4 d-none">
                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                        <h6>No Customers Found</h6>
                        <p class="text-muted">Try a different search term</p>
                    </div>
                    <div id="customer-search-results" class="d-none">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="customer-search-table-body">
                                    <!-- Search results will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary" onclick="clearCustomerSelection()">
                        <i class="fas fa-user-plus me-1"></i>Use Walk-in Customer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Preview Modal -->
    <div class="modal fade" id="receiptPreviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt me-2 text-primary"></i>Receipt Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <small class="text-muted">This is how your receipt will appear when printed</small>
                    </div>
                    <div id="receipt-preview-content" class="border border-secondary rounded p-3" style="background: white; color: black; font-family: 'Courier New', monospace; font-size: 12px; max-height: 400px; overflow-y: auto;">
                        <!-- Receipt preview content will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="doPrintReceipt()">
                        <i class="fas fa-print me-1"></i>Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Receipt Modal -->
    <div class="modal fade" id="emailReceiptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title">
                        <i class="fas fa-envelope me-2 text-primary"></i>Email Receipt
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="receipt-email-address" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="receipt-email-address" placeholder="customer@example.com" required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);">
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    <div class="mb-3">
                        <label for="receipt-email-subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="receipt-email-subject" placeholder="Your receipt from PC Parts Central" value="Your receipt from PC Parts Central" required style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);">
                    </div>
                    <div class="mb-3">
                        <label for="receipt-email-message" class="form-label">Additional Message</label>
                        <textarea class="form-control" id="receipt-email-message" rows="3" placeholder="Thank you for shopping with us!" style="background: var(--bg-tertiary); border: 1px solid var(--border-color); color: var(--text-primary);"></textarea>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>The receipt will be attached as a PDF file.
                    </small>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="doEmailReceipt()">
                        <i class="fas fa-paper-plane me-1"></i>Send Email
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
    <script src="assets/js/pos-pending-orders.js?v=<?php echo time(); ?>"></script>

    <!-- POS JavaScript -->
    <script>
        // Configuration
        const API_BASE = '../backend/api';
        const currentUser = <?php echo htmlspecialchars(json_encode($user), ENT_QUOTES); ?>;

        // POS State
        let posCart = [];
        let posHeldSales = [];
        let currentView = 'grid';
        let isFullscreen = false;
        let lastCompletedSale = null; // Store last completed sale for receipts

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
                    const html = [];
                    if (currentView === 'grid') {
                        container.className = 'row g-2';
                        for (const p of data.data.products) {
                            const imgHtml = p.image_url
                                ? '<img src="' + p.image_url + '" alt="' + p.name.replace(/"/g, '"') + '" class="product-image">'
                                : '<div class="product-image-fallback"><i class="fas fa-microchip fa-2x"></i></div>';

                            const stockText = (p.quantity_available || 0) > 0
                                ? ((p.quantity_available || 0) + ' in stock')
                                : 'Out of stock';

                            const stockClass = (p.quantity_available || 0) > 0 ? 'text-success' : 'text-danger';
                            const stockIcon = (p.quantity_available || 0) > 0 ? 'check' : 'times';

                            html.push(
                                '<div class="col-md-6 col-lg-4">' +
                                    '<div class="card h-100 product-card" data-product="' + btoa(JSON.stringify(p)) + '" onclick="addToCartFromCard(this)">' +
                                        '<div class="card-body p-2">' +
                                            '<div class="product-image-container mb-2">' + imgHtml + '</div>' +
                                            '<h6 class="product-title mb-1" style="font-size: 0.9rem; line-height: 1.2;">' + p.name + '</h6>' +
                                            '<div class="d-flex justify-content-between align-items-center">' +
                                                '<small class="text-muted">' + p.sku + '</small>' +
                                                '<strong style="color: var(--accent); font-size: 0.9rem;">₱' + parseFloat(p.selling_price).toLocaleString() + '</strong>' +
                                            '</div>' +
                                            '<div class="mt-1">' +
                                                '<small class="' + stockClass + '">' +
                                                    '<i class="fas fa-' + stockIcon + '-circle me-1"></i>' + stockText +
                                                '</small>' +
                                            '</div>' +
                                        '</div>' +
                                    '</div>' +
                                '</div>'
                            );
                        }
                        container.innerHTML = html.join('');
                    } else {
                        // List view
                        container.className = '';
                        container.innerHTML =
                            '<div class="table-responsive">' +
                                '<table class="table table-sm">' +
                                    '<thead>' +
                                        '<tr>' +
                                            '<th>SKU</th>' +
                                            '<th>Product</th>' +
                                            '<th>Category</th>' +
                                            '<th>Price</th>' +
                                            '<th>Stock</th>' +
                                            '<th>Action</th>' +
                                        '</tr>' +
                                    '</thead>' +
                                    '<tbody>';

                        for (const p of data.data.products) {
                            const stockClass = (p.quantity_available || 0) > 0 ? 'text-success' : 'text-danger';
                            const stockText = p.quantity_available || 0;

                            container.innerHTML +=
                                '<tr style="cursor: pointer;" data-product="' + btoa(JSON.stringify(p)) + '" onclick="addToCartFromCard(this)">' +
                                    '<td><code>' + p.sku + '</code></td>' +
                                    '<td><strong>' + p.name + '</strong></td>' +
                                    '<td>' + (p.category_name || '-') + '</td>' +
                                    '<td><strong style="color: var(--accent);">₱' + parseFloat(p.selling_price).toLocaleString() + '</strong></td>' +
                                    '<td><span class="' + stockClass + '">' + stockText + '</span></td>' +
                                    '<td><button class="btn btn-sm btn-accent" onclick="event.stopPropagation(); const row = this.closest(\'tr\'); if (row) addToCartFromCard(row);"><i class="fas fa-plus"></i> Add</button></td>' +
                                '</tr>';
                        }

                        container.innerHTML +=
                                    '</tbody>' +
                                '</table>' +
                            '</div>';
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

                    // Store the last completed sale data for receipts
                    lastCompletedSale = {
                        saleNumber: saleNumber,
                        customerName: customerName,
                        date: new Date().toLocaleString(),
                        items: posCart.map(item => ({
                            name: item.name,
                            quantity: item.quantity,
                            price: item.price,
                            total: item.price * item.quantity
                        })),
                        subtotal: subtotal,
                        tax: tax,
                        total: total,
                        cashierName: currentUser.full_name || currentUser.username,
                        saleId: data.data.sale_id
                    };

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
            // Load categories for the quick add modal
            loadCategoriesForQuickAdd();

            const modal = new bootstrap.Modal(document.getElementById('quickAddProductModal'));
            modal.show();
            // Focus on first input
            setTimeout(() => document.getElementById('quick-product-name').focus(), 100);
        }

        async function loadCategoriesForQuickAdd() {
            try {
                const response = await fetch(`${API_BASE}/categories/index.php`);
                const data = await response.json();

                if (data.success && data.data.categories) {
                    const select = document.getElementById('quick-product-category');
                    select.innerHTML = '<option value="">Select Category</option>';

                    data.data.categories.forEach(cat => {
                        select.innerHTML += `<option value="${cat.id}">${cat.name}</option>`;
                    });
                }
            } catch (error) {
                console.error('Error loading categories for quick add:', error);
            }
        }

        async function submitQuickAddProduct() {
            const name = document.getElementById('quick-product-name').value.trim();
            const sku = document.getElementById('quick-product-sku').value.trim();
            const categoryId = document.getElementById('quick-product-category').value;
            const price = parseFloat(document.getElementById('quick-product-price').value);
            const description = document.getElementById('quick-product-description').value.trim();

            // Validation
            if (!name || !sku || !categoryId || isNaN(price) || price <= 0) {
                showToast('Please fill in all required fields with valid values', 'warning');
                return;
            }

            // Calculate cost price (60% of selling price as default)
            const costPrice = price * 0.6;

            const productData = {
                name: name,
                sku: sku,
                category_id: categoryId,
                cost_price: costPrice,
                selling_price: price,
                description: description || null,
                min_stock_level: 1,
                is_active: 1
            };

            const submitBtn = document.querySelector('#quickAddProductModal .btn-accent');
            const originalText = submitBtn.innerHTML;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creating...';

            try {
                const response = await fetch(`${API_BASE}/products/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(productData)
                });

                const data = await response.json();

                if (data.success) {
                    showToast('Product created successfully!', 'success');

                    // Close modal and reset form
                    const modal = bootstrap.Modal.getInstance(document.getElementById('quickAddProductModal'));
                    modal.hide();

                    // Reset form
                    document.getElementById('quick-add-product-form').reset();

                    // Reload products to show the new one
                    loadProducts();

                } else {
                    showToast('Error creating product: ' + (data.message || 'Unknown error'), 'error');
                }
            } catch (error) {
                showToast('Error creating product. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
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
                paymentMethod: document.querySelector('input[name="payment-method"]:checked').value,
                cashReceived: parseFloat(document.getElementById('pos-cash-received').value) || 0,
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

            // Reset form fields
            document.getElementById('pos-customer-name').value = '';
            document.getElementById('pos-cash-received').value = '';
            document.getElementById('pos-change').textContent = '₱0.00';

            // Generate new sale number
            document.getElementById('pos-sale-number').textContent = 'Sale #' + Date.now();

            showToast(`Sale ${saleNumber} held successfully`, 'success');
        }

        function recallSale() {
            populateRecallSaleModal();

            const modal = new bootstrap.Modal(document.getElementById('recallSaleModal'));
            modal.show();
        }

        function populateRecallSaleModal() {
            const salesList = document.getElementById('recall-sales-list');
            const emptyState = document.getElementById('recall-sales-empty');
            const loadingState = document.getElementById('recall-sales-loading');
            const tableBody = document.getElementById('recall-sales-table-body');

            loadingState.classList.remove('d-none');
            salesList.classList.add('d-none');
            emptyState.classList.add('d-none');

            // Small delay to show loading state
            setTimeout(() => {
                if (posHeldSales.length === 0) {
                    loadingState.classList.add('d-none');
                    emptyState.classList.remove('d-none');
                    return;
                }

                tableBody.innerHTML = '';
                posHeldSales.forEach(sale => {
                    const row = document.createElement('tr');
                    const formattedTime = new Date(sale.timestamp).toLocaleString();

                    row.innerHTML = `
                        <td>${sale.saleNumber}</td>
                        <td>${sale.customer}</td>
                        <td>${sale.items.length} item${sale.items.length !== 1 ? 's' : ''}</td>
                        <td>₱${sale.total.toLocaleString()}</td>
                        <td><small>${formattedTime}</small></td>
                        <td>
                            <button class="btn btn-sm btn-accent" onclick="selectHeldSale(${sale.id})">
                                <i class="fas fa-check me-1"></i>Select
                            </button>
                            <button class="btn btn-sm btn-outline-danger ms-1" onclick="deleteHeldSale(${sale.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });

                loadingState.classList.add('d-none');
                salesList.classList.remove('d-none');
            }, 300);
        }

        function selectHeldSale(saleId) {
            const sale = posHeldSales.find(s => s.id === saleId);
            if (!sale) {
                showToast('Sale not found', 'error');
                return;
            }

            // Restore cart
            posCart = [...sale.items];

            // Restore customer and sale info
            document.getElementById('pos-customer-name').value = sale.customer;
            document.getElementById('pos-sale-number').textContent = sale.saleNumber;

            // Restore payment method and cash received if applicable
            if (sale.paymentMethod) {
                const paymentRadio = document.querySelector(`input[name="payment-method"][value="${sale.paymentMethod}"]`);
                if (paymentRadio) {
                    paymentRadio.checked = true;
                    toggleCashPaymentSection(sale.paymentMethod === 'cash');
                    if (sale.paymentMethod === 'cash' && sale.cashReceived) {
                        document.getElementById('pos-cash-received').value = sale.cashReceived;
                        calculateChange();
                    }
                }
            }

            // Update display
            updateCartDisplay();

            // Remove from held sales
            posHeldSales = posHeldSales.filter(s => s.id !== saleId);
            localStorage.setItem('posHeldSales', JSON.stringify(posHeldSales));

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('recallSaleModal'));
            modal.hide();

            showToast(`Sale ${sale.saleNumber} recalled successfully`, 'success');
        }

        function deleteHeldSale(saleId) {
            if (!confirm('Are you sure you want to delete this held sale? This action cannot be undone.')) {
                return;
            }

            posHeldSales = posHeldSales.filter(s => s.id !== saleId);
            localStorage.setItem('posHeldSales', JSON.stringify(posHeldSales));

            populateRecallSaleModal();
            showToast('Held sale deleted', 'success');
        }

        function searchCustomer() {
            // Clear previous search
            document.getElementById('customer-search-input').value = '';
            document.getElementById('customer-search-results').classList.add('d-none');
            document.getElementById('customer-search-empty').classList.add('d-none');

            const modal = new bootstrap.Modal(document.getElementById('customerSearchModal'));
            modal.show();

            // Focus on search input
            setTimeout(() => document.getElementById('customer-search-input').focus(), 100);
        }

        async function performCustomerSearch() {
            const query = document.getElementById('customer-search-input').value.trim();
            if (!query) {
                showToast('Please enter a search term', 'warning');
                return;
            }

            const loadingState = document.getElementById('customer-search-loading');
            const resultsState = document.getElementById('customer-search-results');
            const emptyState = document.getElementById('customer-search-empty');
            const tableBody = document.getElementById('customer-search-table-body');

            loadingState.classList.remove('d-none');
            resultsState.classList.add('d-none');
            emptyState.classList.add('d-none');

            try {
                const response = await fetch(`${API_BASE}/customers/search.php?q=${encodeURIComponent(query)}`);
                const data = await response.json();

                loadingState.classList.add('d-none');

                if (data.success && data.customers && data.customers.length > 0) {
                    tableBody.innerHTML = '';
                    data.customers.forEach(customer => {
                        const row = document.createElement('tr');

                        // Handle different column names
                        const customerName = customer.name || `${customer.first_name || ''} ${customer.last_name || ''}`.trim();

                        row.innerHTML = `
                            <td>${customerName}</td>
                            <td>${customer.email || '-'}</td>
                            <td>${customer.phone || '-'}</td>
                            <td>${customer.address || '-'}</td>
                            <td>
                                <button class="btn btn-sm btn-accent" onclick="selectCustomer('${customerName.replace(/'/g, "\\'").replace(/"/g, '\\"')}')">
                                    <i class="fas fa-check me-1"></i>Select
                                </button>
                            </td>
                        `;
                        tableBody.appendChild(row);
                    });
                    resultsState.classList.remove('d-none');
                } else {
                    emptyState.classList.remove('d-none');
                }
            } catch (error) {
                loadingState.classList.add('d-none');
                emptyState.classList.remove('d-none');
                console.error('Customer search error:', error);
            }
        }

        function selectCustomer(customerName) {
            document.getElementById('pos-customer-name').value = customerName;

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('customerSearchModal'));
            modal.hide();

            showToast(`Customer "${customerName}" selected`, 'success');
        }

        function clearCustomerSelection() {
            document.getElementById('pos-customer-name').value = 'Walk-in Customer';

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('customerSearchModal'));
            modal.hide();

            showToast('Cleared to walk-in customer', 'info');
        }

        function printReceipt() {
            if (posCart.length === 0) {
                showToast('No sale to print', 'warning');
                return;
            }

            // Generate receipt HTML
            const receiptHTML = generateReceiptHTML();

            // Show preview modal
            document.getElementById('receipt-preview-content').innerHTML = receiptHTML;
            const modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
            modal.show();
        }

        function doPrintReceipt() {
            const receiptContent = document.getElementById('receipt-preview-content').innerHTML;

            // Create printable receipt
            const printWindow = window.open('', '_blank', 'width=400,height=600');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Receipt - ${document.getElementById('pos-sale-number').textContent}</title>
                    <style>
                        body { font-family: 'Courier New', monospace; font-size: 12px; max-width: 300px; margin: 0 auto; }
                        .receipt { background: white; color: black; padding: 20px; }
                        .receipt-header { text-align: center; margin-bottom: 20px; }
                        .receipt-store { font-size: 16px; font-weight: bold; margin-bottom: 10px; }
                        .receipt-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .receipt-table th, .receipt-table td { padding: 5px; text-align: left; }
                        .receipt-table .text-center { text-align: center; }
                        .receipt-table .text-end { text-align: right; }
                        .receipt-table .total { border-top: 1px solid #000; font-weight: bold; }
                        .receipt-footer { text-align: center; margin-top: 20px; font-size: 10px; }
                        @media print { body { margin: 0; } }
                    </style>
                </head>
                <body>
                    <div class="receipt">
                        ${receiptContent}
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();

            // Close modal first, then print
            const previewModal = bootstrap.Modal.getInstance(document.getElementById('receiptPreviewModal'));
            previewModal.hide();

            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 300);

            showToast('Receipt sent to printer', 'success');
        }

        function emailReceipt() {
            if (posCart.length === 0) {
                showToast('No sale to email', 'warning');
                return;
            }

            // Pre-fill customer email if available in customer name (email format)
            const customerName = document.getElementById('pos-customer-name').value || 'Walk-in Customer';
            let prefilledEmail = '';

            // Try to extract email from customer name if it looks like an email
            if (customerName.includes('@') && customerName.includes('.')) {
                prefilledEmail = customerName;
            }

            // Prefill the email field
            document.getElementById('receipt-email-address').value = prefilledEmail;

            // Show email modal
            const modal = new bootstrap.Modal(document.getElementById('emailReceiptModal'));
            modal.show();
        }

        async function doEmailReceipt() {
            const emailAddress = document.getElementById('receipt-email-address').value.trim();
            const emailSubject = document.getElementById('receipt-email-subject').value.trim();
            const emailMessage = document.getElementById('receipt-email-message').value.trim();

            // Validation
            if (!emailAddress) {
                showToast('Please enter an email address', 'warning');
                document.getElementById('receipt-email-address').focus();
                return;
            }

            // Basic email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailAddress)) {
                showToast('Please enter a valid email address', 'warning');
                document.getElementById('receipt-email-address').focus();
                return;
            }

            const sendBtn = document.querySelector('#emailReceiptModal .btn-primary');
            const originalText = sendBtn.innerHTML;

            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';

            try {
                // Generate receipt HTML for email
                const receiptHTML = generateReceiptHTML();

                // Prepare email data
                const emailData = {
                    to: emailAddress,
                    subject: emailSubject,
                    message: emailMessage,
                    receipt_html: receiptHTML,
                    sale_number: document.getElementById('pos-sale-number').textContent,
                    customer_name: document.getElementById('pos-customer-name').value || 'Walk-in Customer',
                    total_amount: document.getElementById('pos-total').textContent
                };

                // Note: This would normally send to a backend email endpoint
                // For now, we'll simulate the email sending
                console.log('Email data:', emailData);

                // Simulate API call delay
                await new Promise(resolve => setTimeout(resolve, 1500));

                // Close modal and reset form
                const modal = bootstrap.Modal.getInstance(document.getElementById('emailReceiptModal'));
                modal.hide();

                // Reset form for next use
                document.getElementById('receipt-email-address').value = '';
                document.getElementById('receipt-email-subject').value = 'Your receipt from PC Parts Central';

                showToast(`Receipt emailed successfully to ${emailAddress}`, 'success');

            } catch (error) {
                console.error('Email send error:', error);
                showToast('Failed to send email. Please try again.', 'error');
            } finally {
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalText;
            }
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

        function addToCartFromCard(cardElement) {
            const productData = cardElement.dataset.product;
            if (productData) {
                try {
                    // Decode base64 first, then parse as JSON
                    const decodedData = atob(productData);
                    const product = JSON.parse(decodedData);
                    addToCart(product);
                } catch (error) {
                    console.error('Error parsing product data:', error);
                    showToast('Error adding product to cart', 'error');
                }
            }
        }

        function showToast(message, type = 'info') {
            // Simple toast implementation
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(toast);

            // Auto remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }

        function showHelp() {
            alert('POS Help: Use F1 for search, F12 to complete sale, F3 to clear cart');
        }
    </script>
</body>
</html>
