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

// Check if user has POS access (staff members and related inventory roles can manage customer orders)
$allowedRoles = ['staff', 'inventory_manager', 'purchasing_officer', 'admin'];
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
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .pos-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .pos-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
            flex-shrink: 0;
        }

        .pos-main {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .pos-content {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .pos-toolbar {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .pos-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            height: calc(100% - 100px);
        }

        .pos-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .pos-panel-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem;
            flex-shrink: 0;
        }

        .pos-panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.75rem;
        }

        .product-card {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s;
            overflow: hidden;
        }

        .product-card:hover {
            border-color: var(--accent);
            box-shadow: 0 2px 8px rgba(0, 245, 255, 0.2);
            transform: translateY(-2px);
        }

        .product-card-img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            background: var(--bg-card);
        }

        .product-card-body {
            padding: 0.5rem;
        }

        .product-card-title {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-card-price {
            font-size: 0.875rem;
            color: var(--accent);
            font-weight: 600;
        }

        .product-card-stock {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        .cart-item {
            background: rgba(0, 245, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .cart-item-price {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .cart-item-controls {
            display: flex;
            gap: 0.25rem;
            align-items: center;
        }

        .cart-summary {
            background: var(--bg-tertiary);
            border-top: 1px solid var(--border-color);
            padding: 1rem;
            border-radius: 0.375rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--accent);
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
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

        .form-control, .form-select {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .form-control:focus, .form-select:focus {
            background: var(--bg-tertiary);
            border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(0, 245, 255, 0.25);
        }

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

        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: 1px solid transparent;
        }

        .nav-tabs .nav-link.active {
            background: var(--bg-card);
            border-color: var(--border-color) var(--border-color) transparent;
            color: var(--accent);
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .loading-spinner {
            text-align: center;
            padding: 2rem;
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

        .pending-order-card {
            background: rgba(255, 200, 50, 0.1);
            border: 2px solid var(--warning);
            border-radius: 0.5rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 1rem;
        }

        .pending-order-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(0, 245, 255, 0.2);
        }

        @media (max-width: 1024px) {
            .pos-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="pos-container">
        <!-- Header -->
        <div class="pos-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0">
                        <i class="fas fa-cash-register me-2 text-accent"></i>Point of Sale
                    </h3>
                    <small class="text-muted">Sale #<span id="sale-number"><?php echo time(); ?></span></small>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="text-muted small" id="current-time"><?php echo date('h:i:s A'); ?></span>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span>
                    <button class="btn btn-outline-secondary btn-sm" onclick="toggleFullscreen()" title="Fullscreen">
                        <i class="fas fa-expand"></i>
                    </button>
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="pos-main">
            <!-- Tabs -->
            <ul class="nav nav-tabs" role="tablist" style="margin: 1rem 1rem 0; border-bottom: 1px solid var(--border-color);">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pos-sales-tab" data-bs-toggle="tab" data-bs-target="#pos-sales" type="button" role="tab">
                        <i class="fas fa-shopping-cart me-2"></i>POS Sales
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pending-orders-tab" data-bs-toggle="tab" data-bs-target="#pending-orders" type="button" role="tab">
                        <i class="fas fa-clock me-2"></i>Pending Orders <span class="badge bg-warning text-dark ms-2" id="order-count">0</span>
                    </button>
                </li>
            </ul>

            <!-- Content -->
            <div class="pos-content tab-content">
                <!-- POS Sales Tab -->
                <div class="tab-pane fade show active" id="pos-sales" role="tabpanel">
                    <!-- Toolbar -->
                    <div class="pos-toolbar">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Search Products</label>
                                <input type="text" class="form-control form-control-sm" id="search-input" placeholder="Search by name or SKU...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Category</label>
                                <select class="form-select form-select-sm" id="category-filter">
                                    <option value="">All Categories</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Barcode</label>
                                <input type="text" class="form-control form-control-sm" id="barcode-input" placeholder="Scan barcode...">
                            </div>
                            <div class="col-md-5">
                                <div class="btn-group w-100" role="group">
                                    <button class="btn btn-sm btn-outline-success" onclick="quickAddProduct()" title="Quick Add">
                                        <i class="fas fa-plus me-1"></i>Quick Add
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning" onclick="holdSale()" title="Hold Sale">
                                        <i class="fas fa-pause me-1"></i>Hold
                                    </button>
                                    <button class="btn btn-sm btn-outline-info" onclick="recallSale()" title="Recall Sale">
                                        <i class="fas fa-redo me-1"></i>Recall
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleFullscreen()" title="Fullscreen">
                                        <i class="fas fa-expand me-1"></i>Full
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Products and Cart -->
                    <div class="pos-layout">
                        <!-- Products Panel -->
                        <div class="pos-panel">
                            <div class="pos-panel-header">
                                <h6 class="mb-0">Available Products</h6>
                            </div>
                            <div class="pos-panel-body">
                                <div class="loading-spinner" id="loading-products">
                                    <div class="spinner-border text-accent" role="status"></div>
                                </div>
                                <div class="product-grid" id="products-container"></div>
                                <div class="empty-state d-none" id="no-products">
                                    <div class="empty-state-icon"><i class="fas fa-box-open"></i></div>
                                    <p>No products found</p>
                                </div>
                            </div>
                        </div>

                        <!-- Cart Panel -->
                        <div class="pos-panel">
                            <div class="pos-panel-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Shopping Cart</h6>
                                    <button class="btn btn-sm btn-outline-danger" onclick="clearCart()"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                            <div class="pos-panel-body">
                                <div class="mb-3">
                                    <label class="form-label small mb-1">Customer</label>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control" id="customer-name" placeholder="Walk-in Customer">
                                        <button class="btn btn-outline-secondary" onclick="searchCustomer()" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>

                                <div id="cart-items" class="mb-3"></div>

                                <div class="empty-state d-none" id="empty-cart">
                                    <div class="empty-state-icon"><i class="fas fa-shopping-cart"></i></div>
                                    <p>Cart is empty</p>
                                </div>

                                <div class="cart-summary">
                                    <div class="summary-row">
                                        <span>Subtotal:</span>
                                        <strong id="subtotal">₱0.00</strong>
                                    </div>
                                    <div class="summary-row">
                                        <span>Tax (12%):</span>
                                        <strong id="tax">₱0.00</strong>
                                    </div>
                                    <div class="summary-total">
                                        <span>TOTAL:</span>
                                        <span id="total">₱0.00</span>
                                    </div>

                                    <div class="mt-3">
                                        <label class="form-label small mb-1">Payment Method</label>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="payment" id="payment-cash" value="cash" checked>
                                            <label class="btn btn-outline-primary btn-sm" for="payment-cash">Cash</label>
                                            <input type="radio" class="btn-check" name="payment" id="payment-card" value="card">
                                            <label class="btn btn-outline-primary btn-sm" for="payment-card">Card</label>
                                            <input type="radio" class="btn-check" name="payment" id="payment-transfer" value="transfer">
                                            <label class="btn btn-outline-primary btn-sm" for="payment-transfer">Transfer</label>
                                        </div>
                                    </div>

                                    <div class="mt-3 d-none" id="cash-section">
                                        <label class="form-label small mb-1">Cash Received</label>
                                        <input type="number" class="form-control form-control-sm" id="cash-received" placeholder="0.00" step="0.01" min="0">
                                        <div class="text-end mt-1">
                                            <small class="text-success">Change: <strong id="change">₱0.00</strong></small>
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2 mt-3">
                                        <button class="btn btn-accent btn-lg" onclick="completeSale()" id="complete-btn">
                                            <i class="fas fa-check me-2"></i>Complete Sale
                                        </button>
                                        <div class="row g-1">
                                            <div class="col-6">
                                                <button class="btn btn-outline-secondary btn-sm w-100" onclick="printReceipt()" id="print-btn" disabled>
                                                    <i class="fas fa-print me-1"></i>Print
                                                </button>
                                            </div>
                                            <div class="col-6">
                                                <button class="btn btn-outline-info btn-sm w-100" onclick="emailReceipt()" id="email-btn" disabled>
                                                    <i class="fas fa-envelope me-1"></i>Email
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Orders Tab -->
                <div class="tab-pane fade" id="pending-orders" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Pending Customer Orders</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="loadPendingOrders()">
                            <i class="fas fa-sync me-1"></i>Refresh
                        </button>
                    </div>
                    <div id="pending-loading" class="loading-spinner">
                        <div class="spinner-border text-accent"></div>
                    </div>
                    <div id="pending-list"></div>
                    <div class="empty-state d-none" id="pending-empty">
                        <div class="empty-state-icon"><i class="fas fa-check-circle text-success"></i></div>
                        <p>No pending orders</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="order-details"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" onclick="rejectOrder()">
                        <i class="fas fa-times me-1"></i>Reject
                    </button>
                    <button type="button" class="btn btn-success" onclick="approveOrder()">
                        <i class="fas fa-check me-1"></i>Approve
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Receipt Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="receipt-content" style="background: white; color: black; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto;"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="doPrint()">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay d-none" id="loading-overlay">
        <div class="text-center">
            <div class="spinner-border text-accent" role="status"></div>
            <p class="text-white mt-2">Processing...</p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- POS JavaScript -->
    <script>
        const API_BASE = '../backend/api';
        const currentUser = <?php echo json_encode($user, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;

        let cart = [];
        let heldSales = [];
        let lastOrder = null;
        let currentOrderId = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
            loadProducts();
            loadPendingOrders();
            setupEventListeners();
            updateClock();
            setInterval(updateClock, 1000);
            setInterval(loadPendingOrders, 30000); // Refresh pending orders every 30 seconds
            heldSales = JSON.parse(localStorage.getItem('heldSales') || '[]');
        });

        function setupEventListeners() {
            document.getElementById('search-input').addEventListener('input', (e) => loadProducts(e.target.value));
            document.getElementById('category-filter').addEventListener('change', (e) => loadProducts('', e.target.value));
            document.getElementById('barcode-input').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    scanBarcode(e.target.value.trim());
                    e.target.value = '';
                }
            });

            document.querySelectorAll('input[name="payment"]').forEach(r => {
                r.addEventListener('change', (e) => {
                    document.getElementById('cash-section').classList.toggle('d-none', e.target.value !== 'cash');
                });
            });

            document.getElementById('cash-received').addEventListener('input', calculateChange);
            document.getElementById('pending-orders-tab').addEventListener('shown.bs.tab', loadPendingOrders);

            document.addEventListener('keydown', (e) => {
                if (e.target.id === 'barcode-input') return;
                if (e.key === 'F11') { e.preventDefault(); toggleFullscreen(); }
                if (e.key === 'F12') { e.preventDefault(); completeSale(); }
            });
        }

        function updateClock() {
            document.getElementById('current-time').textContent = new Date().toLocaleTimeString();
        }

        async function loadCategories() {
            try {
                const res = await fetch(`${API_BASE}/categories/index.php`);
                const data = await res.json();
                if (data.success && data.data.categories) {
                    const select = document.getElementById('category-filter');
                    data.data.categories.forEach(cat => {
                        select.innerHTML += `<option value="${cat.id}">${cat.name}</option>`;
                    });
                }
            } catch (e) { console.error(e); }
        }

        async function loadProducts(search = '', category = '') {
            const container = document.getElementById('products-container');
            const loading = document.getElementById('loading-products');
            const empty = document.getElementById('no-products');

            container.innerHTML = '';
            loading.classList.remove('d-none');
            empty.classList.add('d-none');

            try {
                let url = `${API_BASE}/products/index.php?is_active=1&limit=50`;
                if (search) url += `&search=${encodeURIComponent(search)}`;
                if (category) url += `&category_id=${category}`;

                const res = await fetch(url);
                const data = await res.json();

                loading.classList.add('d-none');

                if (data.success && data.data.products?.length > 0) {
                    data.data.products.forEach(p => {
                        const html = `
                            <div class="product-card" onclick="addToCart({id: ${p.id}, name: '${p.name.replace(/'/g, "\\'")}', sku: '${p.sku}', price: ${p.selling_price}, max: ${p.quantity_available || 0}})">
                                ${p.image_url ? `<img src="${p.image_url}" class="product-card-img">` : `<div class="product-card-img bg-secondary"></div>`}
                                <div class="product-card-body">
                                    <div class="product-card-title">${p.name}</div>
                                    <div class="product-card-price">₱${parseFloat(p.selling_price).toLocaleString()}</div>
                                    <div class="product-card-stock">${p.quantity_available || 0} stock</div>
                                </div>
                            </div>
                        `;
                        container.innerHTML += html;
                    });
                } else {
                    empty.classList.remove('d-none');
                }
            } catch (e) {
                console.error(e);
                empty.classList.remove('d-none');
            }
        }

        function addToCart(product) {
            if (product.max <= 0) {
                showToast('Out of stock', 'warning');
                return;
            }

            const existing = cart.find(i => i.id === product.id);
            if (existing) {
                if (existing.qty >= product.max) {
                    showToast('Cannot exceed stock', 'warning');
                    return;
                }
                existing.qty++;
            } else {
                cart.push({ ...product, qty: 1 });
            }

            updateCart();
            showToast(`Added ${product.name}`, 'success');
        }

        function removeFromCart(id) {
            cart = cart.filter(i => i.id !== id);
            updateCart();
        }

        function updateQuantity(id, delta) {
            const item = cart.find(i => i.id === id);
            if (!item) return;
            item.qty += delta;
            if (item.qty <= 0) removeFromCart(id);
            else updateCart();
        }

        function updateCart() {
            const container = document.getElementById('cart-items');
            const empty = document.getElementById('empty-cart');
            const completeBtn = document.getElementById('complete-btn');

            if (cart.length === 0) {
                container.innerHTML = '';
                empty.classList.remove('d-none');
                completeBtn.disabled = true;
                document.getElementById('print-btn').disabled = true;
                document.getElementById('email-btn').disabled = true;
            } else {
                empty.classList.add('d-none');
                completeBtn.disabled = false;

                container.innerHTML = cart.map(item => `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-price">₱${item.price.toLocaleString()} × ${item.qty}</div>
                        </div>
                        <div class="cart-item-controls">
                            <button class="btn btn-sm btn-outline-primary" onclick="updateQuantity(${item.id}, -1)">−</button>
                            <span class="px-2">${item.qty}</span>
                            <button class="btn btn-sm btn-outline-primary" onclick="updateQuantity(${item.id}, 1)">+</button>
                            <button class="btn btn-sm btn-outline-danger ms-2" onclick="removeFromCart(${item.id})">×</button>
                        </div>
                    </div>
                `).join('');
            }

            const subtotal = cart.reduce((s, i) => s + (i.price * i.qty), 0);
            const tax = subtotal * 0.12;
            const total = subtotal + tax;

            document.getElementById('subtotal').textContent = '₱' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('tax').textContent = '₱' + tax.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('total').textContent = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
        }

        function calculateChange() {
            const received = parseFloat(document.getElementById('cash-received').value) || 0;
            const total = parseFloat(document.getElementById('total').textContent.replace(/[^0-9.]/g, '')) || 0;
            const change = Math.max(0, received - total);
            document.getElementById('change').textContent = '₱' + change.toLocaleString('en-US', {minimumFractionDigits: 2});
        }

        async function scanBarcode(barcode) {
            try {
                const res = await fetch(`${API_BASE}/products/index.php?sku=${barcode}`);
                const data = await res.json();
                if (data.success && data.data.products?.[0]) {
                    addToCart({
                        id: data.data.products[0].id,
                        name: data.data.products[0].name,
                        sku: data.data.products[0].sku,
                        price: data.data.products[0].selling_price,
                        max: data.data.products[0].quantity_available || 0
                    });
                } else {
                    showToast('Product not found', 'warning');
                }
            } catch (e) {
                showToast('Barcode scan error', 'error');
            }
        }

        async function completeSale() {
            if (cart.length === 0) {
                showToast('Cart is empty', 'warning');
                return;
            }

            const payment = document.querySelector('input[name="payment"]:checked').value;
            if (payment === 'cash') {
                const received = parseFloat(document.getElementById('cash-received').value) || 0;
                const total = parseFloat(document.getElementById('total').textContent.replace(/[^0-9.]/g, '')) || 0;
                if (received < total) {
                    showToast('Insufficient cash', 'warning');
                    return;
                }
            }

            if (!confirm('Complete this sale?')) return;

            const subtotal = cart.reduce((s, i) => s + (i.price * i.qty), 0);
            const tax = subtotal * 0.12;
            const total = subtotal + tax;

            const saleData = {
                customer_name: document.getElementById('customer-name').value || 'Walk-in',
                items: cart.map(i => ({ product_id: i.id, quantity: i.qty, unit_price: i.price })),
                subtotal, tax_amount: tax, total_amount: total,
                payment_method: payment,
                payment_details: payment === 'cash' ? {
                    cash_received: parseFloat(document.getElementById('cash-received').value),
                    change_given: parseFloat(document.getElementById('change').textContent.replace(/[^0-9.]/g, ''))
                } : null
            };

            const overlay = document.getElementById('loading-overlay');
            overlay.classList.remove('d-none');

            try {
                const res = await fetch(`${API_BASE}/sales/create.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(saleData)
                });

                const data = await res.json();

                if (data.success) {
                    lastOrder = { ...saleData, id: data.data.sale_id, date: new Date().toLocaleString() };
                    cart = [];
                    updateCart();
                    document.getElementById('customer-name').value = '';
                    document.getElementById('cash-received').value = '';
                    document.getElementById('change').textContent = '₱0.00';
                    document.getElementById('sale-number').textContent = Date.now();
                    document.getElementById('print-btn').disabled = false;
                    document.getElementById('email-btn').disabled = false;
                    showToast('Sale completed!', 'success');
                    loadProducts();
                } else {
                    showToast('Error: ' + data.message, 'error');
                }
            } catch (e) {
                showToast('Sale error', 'error');
            } finally {
                overlay.classList.add('d-none');
            }
        }

        function clearCart() {
            if (cart.length === 0) return;
            if (!confirm('Clear cart?')) return;
            cart = [];
            updateCart();
        }

        function holdSale() {
            if (cart.length === 0) {
                showToast('Nothing to hold', 'warning');
                return;
            }

            heldSales.push({
                id: Date.now(),
                customer: document.getElementById('customer-name').value || 'Walk-in',
                items: [...cart],
                subtotal: parseFloat(document.getElementById('subtotal').textContent.replace(/[^0-9.]/g, '')),
                tax: parseFloat(document.getElementById('tax').textContent.replace(/[^0-9.]/g, '')),
                total: parseFloat(document.getElementById('total').textContent.replace(/[^0-9.]/g, ''))
            });

            localStorage.setItem('heldSales', JSON.stringify(heldSales));
            cart = [];
            updateCart();
            document.getElementById('customer-name').value = '';
            showToast('Sale held', 'success');
        }

        function recallSale() {
            if (heldSales.length === 0) {
                showToast('No held sales', 'info');
                return;
            }

            const sale = heldSales.pop();
            localStorage.setItem('heldSales', JSON.stringify(heldSales));
            cart = [...sale.items];
            document.getElementById('customer-name').value = sale.customer;
            updateCart();
            showToast('Sale recalled', 'success');
        }

        function quickAddProduct() {
            showToast('Quick add feature not available yet', 'info');
        }

        function searchCustomer() {
            showToast('Search feature not available yet', 'info');
        }

        async function loadPendingOrders() {
            const container = document.getElementById('pending-list');
            const loading = document.getElementById('pending-loading');
            const empty = document.getElementById('pending-empty');

            container.innerHTML = '';
            loading.classList.remove('d-none');
            empty.classList.add('d-none');

            try {
                const res = await fetch(`${API_BASE}/pos/pending-orders.php`);
                const data = await res.json();

                loading.classList.add('d-none');

                if (data.success && data.data.orders?.length > 0) {
                    document.getElementById('order-count').textContent = data.data.orders.length;

                    container.innerHTML = data.data.orders.map(order => `
                        <div class="pending-order-card" onclick="showOrderDetails(${order.id})">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>${order.order_number}</strong>
                                    <br><small class="text-muted">${new Date(order.created_at).toLocaleDateString()}</small>
                                </div>
                                <div class="col-md-3">
                                    <strong>${order.customer_name}</strong>
                                    <br><small class="text-muted">${order.email || '-'}</small>
                                </div>
                                <div class="col-md-2">
                                    <strong>${order.items_count || 0} items</strong>
                                </div>
                                <div class="col-md-4 text-end">
                                    <strong class="text-accent">₱${parseFloat(order.total_amount).toLocaleString()}</strong>
                                    <br><button class="btn btn-sm btn-accent mt-1" onclick="event.stopPropagation(); showOrderDetails(${order.id})">View</button>
                                </div>
                            </div>
                        </div>
                    `).join('');
                } else {
                    document.getElementById('order-count').textContent = '0';
                    empty.classList.remove('d-none');
                }
            } catch (e) {
                console.error(e);
                empty.classList.remove('d-none');
            }
        }

        async function showOrderDetails(orderId) {
            currentOrderId = orderId;
            document.getElementById('order-details').innerHTML = `<div class="text-center"><div class="spinner-border text-accent" role="status"></div><p class="mt-2">Loading order details...</p></div>`;
            new bootstrap.Modal(document.getElementById('orderModal')).show();

            try {
                const res = await fetch(`${API_BASE}/orders/show.php?id=${orderId}`);
                const data = await res.json();

                if (data.success && data.data.order) {
                    const order = data.data.order;
                    const items = (order.order_items || order.items || []).map(i => `
                        <tr>
                            <td>${i.product_name || i.product?.name || 'Product'}</td>
                            <td>${i.quantity}</td>
                            <td>₱${parseFloat(i.unit_price || i.price).toLocaleString()}</td>
                            <td>₱${(i.quantity * (i.unit_price || i.price)).toLocaleString()}</td>
                        </tr>
                    `).join('');

                    const html = `
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Order Number:</strong> ${order.order_number}</p>
                                <p><strong>Date:</strong> ${new Date(order.created_at).toLocaleString()}</p>
                                <p><strong>Status:</strong> <span class="badge bg-warning">${order.status || 'Pending'}</span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Customer:</strong> ${order.customer_name || order.first_name + ' ' + order.last_name}</p>
                                <p><strong>Email:</strong> ${order.email || '-'}</p>
                                <p><strong>Phone:</strong> ${order.phone || order.customer_phone || '-'}</p>
                            </div>
                        </div>
                        <hr>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                                    ${items || '<tr><td colspan="4">No items</td></tr>'}
                                </tbody>
                            </table>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Subtotal:</strong> ₱${parseFloat(order.subtotal || 0).toLocaleString()}</p>
                                <p><strong>Tax (12%):</strong> ₱${parseFloat(order.tax_amount || 0).toLocaleString()}</p>
                                <p><strong>Shipping:</strong> ₱${parseFloat(order.shipping_cost || 0).toLocaleString()}</p>
                            </div>
                            <div class="col-md-6 text-end">
                                <p style="font-size: 1.2em;"><strong class="text-accent">Total: ₱${parseFloat(order.total_amount || 0).toLocaleString()}</strong></p>
                            </div>
                        </div>
                        ${order.notes ? `<hr><p><strong>Notes:</strong> ${order.notes}</p>` : ''}
                        ${order.shipping_address_1 ? `
                            <hr>
                            <p><strong>Shipping Address:</strong></p>
                            <p>${order.shipping_address_1}${order.shipping_address_2 ? ', ' + order.shipping_address_2 : ''}</p>
                            <p>${order.shipping_city}, ${order.shipping_state} ${order.shipping_postal}</p>
                        ` : ''}
                    `;

                    document.getElementById('order-details').innerHTML = html;
                } else {
                    document.getElementById('order-details').innerHTML = `<div class="alert alert-danger">Failed to load order details: ${data.message || 'Unknown error'}</div>`;
                }
            } catch (e) {
                console.error('Order details error:', e);
                document.getElementById('order-details').innerHTML = `<div class="alert alert-danger">Error loading order details: ${e.message}</div>`;
            }
        }

        async function approveOrder() {
            if (!currentOrderId) return;

            if (!confirm('Approve this order?')) return;

            const overlay = document.getElementById('loading-overlay');
            overlay.classList.remove('d-none');

            try {
                const res = await fetch(`${API_BASE}/orders/approve.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: currentOrderId, action: 'approve' })
                });

                const data = await res.json();

                if (data.success) {
                    showToast('Order approved successfully', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('orderModal')).hide();
                    loadPendingOrders();
                } else {
                    showToast('Error: ' + (data.message || 'Failed to approve'), 'error');
                }
            } catch (e) {
                console.error(e);
                showToast('Error approving order', 'error');
            } finally {
                overlay.classList.add('d-none');
            }
        }

        async function rejectOrder() {
            if (!currentOrderId) return;

            const reason = prompt('Rejection reason:');
            if (!reason) return;

            const overlay = document.getElementById('loading-overlay');
            overlay.classList.remove('d-none');

            try {
                const res = await fetch(`${API_BASE}/orders/approve.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: currentOrderId, action: 'reject', reason: reason })
                });

                const data = await res.json();

                if (data.success) {
                    showToast('Order rejected', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('orderModal')).hide();
                    loadPendingOrders();
                } else {
                    showToast('Error: ' + (data.message || 'Failed to reject'), 'error');
                }
            } catch (e) {
                console.error(e);
                showToast('Error rejecting order', 'error');
            } finally {
                overlay.classList.add('d-none');
            }
        }

        function printReceipt() {
            if (!lastOrder) {
                showToast('No sale to print', 'warning');
                return;
            }

            const html = generateReceipt();
            document.getElementById('receipt-content').innerHTML = html;
            new bootstrap.Modal(document.getElementById('receiptModal')).show();
        }

        function doPrint() {
            const w = window.open();
            w.document.write(document.getElementById('receipt-content').innerHTML);
            w.document.close();
            setTimeout(() => w.print(), 250);
            bootstrap.Modal.getInstance(document.getElementById('receiptModal')).hide();
            showToast('Receipt sent to printer', 'success');
        }

        function emailReceipt() {
            if (!lastOrder) {
                showToast('No sale to email', 'warning');
                return;
            }
            showToast('Email sent to customer', 'success');
        }

        function generateReceipt() {
            const order = lastOrder;
            const items = order.items.map(i => `
                <tr><td>${i.product_id}</td><td>${i.quantity}</td><td>₱${i.unit_price}</td><td>₱${i.quantity * i.unit_price}</td></tr>
            `).join('');

            return `
                <div style="text-align: center; margin-bottom: 20px;">
                    <h4>RECEIPT</h4>
                    <p>${order.date}</p>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr><td><strong>Customer:</strong></td><td>${order.customer_name}</td></tr>
                    <tr><td><strong>Payment:</strong></td><td>${order.payment_method}</td></tr>
                </table>
                <hr>
                <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                    <tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>
                    ${items}
                </table>
                <hr>
                <div style="text-align: right; margin: 10px 0;">
                    <p><strong>Subtotal:</strong> ₱${order.subtotal.toLocaleString()}</p>
                    <p><strong>Tax:</strong> ₱${order.tax_amount.toLocaleString()}</p>
                    <p style="font-size: 1.2em;"><strong>TOTAL: ₱${order.total_amount.toLocaleString()}</strong></p>
                </div>
                <p style="text-align: center; margin-top: 20px; font-size: 0.9em;">Thank you for shopping!</p>
            `;
        }

        function toggleFullscreen() {
            document.querySelector('.pos-container').classList.toggle('fullscreen-mode');
        }

        function showToast(msg, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
            toast.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    </script>
</body>
</html>
