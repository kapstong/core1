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
    <title>PC Parts Central - Premium Gaming & Workstation Components</title>
    <link rel="icon" type="image/png" href="ppc.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Premium CSS -->
    <link rel="stylesheet" href="assets/css/main.css">

    <style>
        /* Additional shop-specific styles */
        .hero-banner {
            position: relative;
            padding: 5rem 0;
            overflow: hidden;
        }

        .hero-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(0, 102, 255, 0.2) 0%, transparent 50%);
            animation: pulse 3s ease-in-out infinite;
        }

        .search-wrapper {
            position: relative;
            max-width: 700px;
            margin: 2rem auto;
        }

        .search-wrapper .form-control {
            padding: 1.25rem 4rem 1.25rem 1.5rem;
            font-size: 1.05rem;
            border: 2px solid var(--border-accent);
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            box-shadow: 0 0 30px rgba(0, 245, 255, 0.2);
        }

        .search-wrapper .form-control:focus {
            box-shadow: 0 0 40px rgba(0, 245, 255, 0.4);
        }

        .search-btn {
            position: absolute;
            right: 0.5rem;
            top: 50%;
            transform: translateY(-50%);
            padding: 0.75rem 1.5rem;
        }

        .stats-section {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .category-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0.5rem;
        }

        .category-pill:hover {
            background: linear-gradient(135deg, rgba(0, 102, 255, 0.2) 0%, rgba(0, 245, 255, 0.1) 100%);
            border-color: var(--border-accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 245, 255, 0.3);
        }

        .category-pill.active {
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            color: var(--bg-primary);
            border-color: var(--accent);
        }

        .section-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 900;
            text-align: center;
            margin-bottom: 3rem;
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .floating-cart {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        /* Product Image Placeholder */
        .product-image-placeholder {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, var(--bg-glass) 0%, rgba(0, 245, 255, 0.1) 100%);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .product-image-placeholder:hover {
            background: linear-gradient(135deg, rgba(0, 245, 255, 0.1) 0%, var(--bg-glass) 100%);
            border-color: var(--accent);
            box-shadow: 0 0 20px rgba(0, 245, 255, 0.2);
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0.5rem;
        }

        .product-image-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .stats-section {
                gap: 1.5rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .product-image-placeholder {
                height: 180px;
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
                    <img src="ppc.png" alt="PC Parts Central Logo" style="height: 32px; width: auto; margin-right: 8px;">
                    PC Parts Central
                </a>
                <div class="d-flex align-items-center gap-3">
                    <a href="#categories" class="nav-link d-none d-md-inline">Categories</a>
                    <a href="#products" class="nav-link d-none d-md-inline">Products</a>

                    <!-- User Menu (shown when authenticated) -->
                    <li class="nav-item dropdown" id="user-menu" style="display: none;">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><span id="customer-name">Loading...</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li id="pos-menu-item" style="display: none;"><hr class="dropdown-divider" style="border-color: var(--border-color);"></li>
                            <li id="pos-menu-link" style="display: none;"><a class="dropdown-item" href="pos.php"><i class="fas fa-cash-register me-2"></i>Point of Sale</a></li>
                            <li><hr class="dropdown-divider" style="border-color: var(--border-color);"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>

                    <!-- Login Button (shown when not authenticated) -->
                    <a href="login.php" class="btn btn-outline-light btn-sm" id="login-btn">
                        <i class="fas fa-sign-in-alt me-1"></i>
                        <span class="d-none d-md-inline">Login</span>
                    </a>

                    <a href="cart.php" class="cart-button" id="cart-btn">
                        <div class="cart-icon-wrapper">
                            <i class="fas fa-shopping-cart cart-icon"></i>
                            <span id="cart-count" class="cart-count-badge"></span>
                        </div>
                        <span class="cart-text">Cart</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-banner">
        <div class="container text-center">
            <h1 class="display-1 mb-4 fade-in">
                Build Your Dream PC
            </h1>
            <p class="lead text-secondary mb-5" style="font-size: 1.25rem; max-width: 700px; margin: 0 auto;">
                Premium components for gaming, content creation, and professional workstations.
                Shop the latest CPUs, GPUs, and more.
            </p>

            <!-- Search Bar -->
            <div class="search-wrapper">
                <input type="text"
                       class="form-control"
                       id="search-input"
                       placeholder="Search for CPUs, GPUs, RAM, or any PC component..."
                       aria-label="Search products">
                <button class="btn btn-accent search-btn" id="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>

            <!-- Stats -->
            <div class="stats-section">
                <div class="stat-item">
                    <div class="stat-number" id="stat-products">0</div>
                    <div class="stat-label">Premium Products</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">10</div>
                    <div class="stat-label">Categories</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">100%</div>
                    <div class="stat-label">Authentic</div>
                </div>
            </div>
        </div>
    </section>



    <!-- Categories Section -->
    <section id="categories" class="py-5">
        <div class="container">
            <h2 class="section-title">
                <i class="fas fa-layer-group me-2"></i>
                Browse Categories
            </h2>
            <div id="categories-container" class="d-flex flex-wrap justify-content-center gap-3">
                <!-- Categories will be loaded here -->
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section id="products" class="py-5" style="background: var(--bg-secondary);">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h2 class="section-title mb-0">
                    <i class="fas fa-fire me-2"></i>
                    Featured Products
                </h2>
                <select class="form-select form-control" id="category-filter" style="max-width: 250px;">
                    <option value="">All Categories</option>
                </select>
            </div>

            <div id="loading" class="text-center py-5" style="display: none;">
                <div class="spinner"></div>
                <p class="text-muted mt-3">Loading products...</p>
            </div>

            <div id="products-container" class="product-grid">
                <!-- Products will be loaded here -->
            </div>

            <div id="no-products" class="text-center py-5" style="display: none;">
                <i class="fas fa-box-open fa-4x text-muted mb-3" style="opacity: 0.3;"></i>
                <p class="text-muted">No products found matching your criteria</p>
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
            <div class="d-flex justify-content-center gap-3 mb-3">
                <a href="#" class="text-muted"><i class="fab fa-facebook fa-lg"></i></a>
                <a href="#" class="text-muted"><i class="fab fa-twitter fa-lg"></i></a>
                <a href="#" class="text-muted"><i class="fab fa-instagram fa-lg"></i></a>
                <a href="#" class="text-muted"><i class="fab fa-youtube fa-lg"></i></a>
            </div>

        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Shop JS -->
    <script>
        const IS_DEVELOPMENT = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

        // Development-only console logging
        function devLog(message, data = null) {
            if (IS_DEVELOPMENT) {
                if (data) {
                    console.error(message, data);
                } else {
                    console.error(message);
                }
            }
        }

        // Determine API base path based on environment
        const IS_DEVELOPMENT = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        const BASE_PATH = IS_DEVELOPMENT ? '/core1' : '';
        const API_BASE = BASE_PATH + '/backend/api';

        // Authentication check
        let isAuthenticated = false;

        // Check if customer is authenticated
        async function checkAuthentication() {
            try {
                // First check for persistent login token
                const hasPersistentLogin = await checkPersistentLogin();
                if (hasPersistentLogin) {
                    // If persistent login worked, user is now authenticated
                    isAuthenticated = true;
                    const response = await fetch(`${API_BASE}/shop/auth.php?action=me`, {
                        credentials: 'same-origin'
                    });
                    const data = await response.json();
                    if (data.success && data.data && data.data.customer) {
                        return data.data.customer;
                    }
                }

                // Regular authentication check
                const response = await fetch(`${API_BASE}/shop/auth.php?action=me`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data.success && data.data && data.data.customer) {
                    isAuthenticated = true;
                    return data.data.customer;
                } else {
                    isAuthenticated = false;
                    // Check if this is a staff user trying to access the shop
                    if (data.data && data.data.is_staff) {
                        // Redirect staff to the employee dashboard
                        window.location.href = 'dashboard.php';
                        return null;
                    }
                    // For regular guests, no redirect - allow browsing
                    return null;
                }
            } catch (error) {
                devLog('Auth check error:', error);
                isAuthenticated = false;
                return null;
            }
        }

        // Check for persistent login token
        async function checkPersistentLogin() {
            try {
                // Check if remember_token cookie exists
                const cookies = document.cookie.split(';').reduce((acc, cookie) => {
                    const [name, value] = cookie.trim().split('=');
                    acc[name] = value;
                    return acc;
                }, {});

                if (!cookies.remember_token) {
                    return false;
                }

                // Validate token with server
                const response = await fetch(`${API_BASE}/auth/validate_persistent.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ token: cookies.remember_token })
                });

                const data = await response.json();
                return data.success === true;
            } catch (error) {
                devLog('Persistent login check error:', error);
                return false;
            }
        }

        // Initialize cart authentication and user menu
        async function initCart() {
            const user = await checkAuthentication();
            const userMenu = document.getElementById('user-menu');
            const loginBtn = document.getElementById('login-btn');
            const customerName = document.getElementById('customer-name');
            const posMenuItem = document.getElementById('pos-menu-item');
            const posMenuLink = document.getElementById('pos-menu-link');

            if (isAuthenticated && user) {
                // User is authenticated, show user menu and load database cart
                userMenu.style.display = 'block';
                loginBtn.style.display = 'none';
                customerName.textContent = user.first_name || 'User';

                // Check if user is staff (can access POS)
                const isStaff = user.role === 'admin' || user.role === 'inventory_manager' || user.role === 'staff';
                if (isStaff) {
                    posMenuItem.style.display = 'block';
                    posMenuLink.style.display = 'block';
                } else {
                    posMenuItem.style.display = 'none';
                    posMenuLink.style.display = 'none';
                }

                // Load user's cart from database and sync with localStorage
                await loadUserCart();
            } else {
                // User not authenticated, show login button and use localStorage cart
                userMenu.style.display = 'none';
                loginBtn.style.display = 'block';
                posMenuItem.style.display = 'none';
                posMenuLink.style.display = 'none';

                const cartBtn = document.getElementById('cart-btn');
                if (cartBtn) {
                    cartBtn.href = 'login.php';
                    cartBtn.innerHTML = `
                        <div class="cart-icon-wrapper">
                            <i class="fas fa-shopping-cart cart-icon"></i>
                            <span id="cart-count" class="cart-count-badge"></span>
                        </div>
                        <span class="cart-text">Cart</span>
                    `;
                }

                // Update badge for guest cart
                Cart.updateBadge();
            }
        }

        // Load user's cart from database and sync with localStorage
        async function loadUserCart() {
            try {
                const response = await fetch(`${API_BASE}/shop/cart.php`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();

                if (data.success) {
                    const dbCartItems = data.data.items || [];
                    const localCartItems = JSON.parse(localStorage.getItem('cart') || '[]');

                    // If user has items in database cart, use those
                    if (dbCartItems.length > 0) {
                        // Convert database cart format to localStorage format
                        const syncedCart = dbCartItems.map(item => ({
                            id: item.product_id,
                            sku: item.product.sku || '',
                            name: item.product.name,
                            price: item.unit_price,
                            quantity: item.quantity
                        }));

                        // Update localStorage with database cart
                        localStorage.setItem('cart', JSON.stringify(syncedCart));
                        Cart.items = syncedCart;
                    } else if (localCartItems.length > 0) {
                        // If no database cart but has localStorage cart, sync to database
                        await syncLocalCartToDatabase(localCartItems);
                    }

                    // Update cart badge
                    Cart.updateBadge();
                }
            } catch (error) {
                devLog('Error loading user cart:', error);
                // Fallback to localStorage cart
                Cart.updateBadge();
            }
        }

        // Sync localStorage cart to database when user logs in
        async function syncLocalCartToDatabase(localCartItems) {
            if (localCartItems.length === 0) return;

            try {
                for (const item of localCartItems) {
                    await fetch(`${API_BASE}/shop/cart.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            product_id: item.id,
                            quantity: item.quantity
                        })
                    });
                }

                // Clear localStorage after successful sync
                localStorage.removeItem('cart');
                Cart.items = [];

                devLog('Local cart synced to database');
            } catch (error) {
                devLog('Error syncing cart to database:', error);
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
                console.error('Logout error:', error);
            }
            window.location.href = 'login.php';
        }

        // Cart functionality
        const Cart = {
            items: JSON.parse(localStorage.getItem('cart') || '[]'),

            async add(product) {
                if (!isAuthenticated) {
                    // Redirect to login if not authenticated
                    Cart.showNotification('Please login to add items to cart', 'warning');
                    setTimeout(() => {
                        // Check authentication and use redirect URL if available
                        checkAuthentication().then(() => {
                            // If still not authenticated after check, redirect to login
                            if (!isAuthenticated) {
                                window.location.href = 'login.php';
                            }
                        }).catch(() => {
                            window.location.href = 'login.php';
                        });
                    }, 1500);
                    return;
                }

                try {
                    const response = await fetch(`${API_BASE}/shop/cart.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            product_id: product.id,
                            quantity: 1
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Update local cart to match database
                        const existing = this.items.find(item => item.id === product.id);
                        if (existing) {
                            existing.quantity++;
                        } else {
                            this.items.push({
                                id: product.id,
                                sku: product.sku,
                                name: product.name,
                                price: parseFloat(product.selling_price),
                                quantity: 1
                            });
                        }
                        this.save();
                        this.updateBadge();
                        this.showNotification('Added to cart!', 'success');
                    } else {
                        this.showNotification(data.message || 'Failed to add item to cart', 'error');
                    }
                } catch (error) {
                    devLog('Error adding to cart:', error);
                    this.showNotification('Failed to add item to cart', 'error');
                }
            },

            save() {
                localStorage.setItem('cart', JSON.stringify(this.items));
            },

            updateBadge() {
                if (!isAuthenticated) return;

                const count = this.items.reduce((sum, item) => sum + item.quantity, 0);
                const badge = document.getElementById('cart-count');
                if (badge) {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'flex' : 'none';
                }
            },

            showNotification(message, type) {
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
        };

        // Load categories
        async function loadCategories() {
            try {
                const response = await fetch(`${API_BASE}/shop/categories.php`);
                const data = await response.json();

                if (data.success && data.data.categories) {
                    const container = document.getElementById('categories-container');
                    const filter = document.getElementById('category-filter');

                    // Add "All Products" button first
                    const allProductsButton = document.createElement('div');
                    allProductsButton.className = 'category-pill active';
                    allProductsButton.dataset.categoryId = '';
                    allProductsButton.innerHTML = `
                        <i class="fas fa-th"></i>
                        <span>All Products</span>
                    `;
                    allProductsButton.addEventListener('click', () => {
                        document.getElementById('category-filter').value = '';
                        loadProducts(null);
                        document.getElementById('products').scrollIntoView({ behavior: 'smooth' });
                        updateActivePill(null);
                    });
                    container.appendChild(allProductsButton);

                    data.data.categories.forEach(cat => {
                        // Create category button
                        const button = document.createElement('div');
                        button.className = 'category-pill';
                        button.dataset.categoryId = cat.id;
                        button.innerHTML = `
                            <i class="fas ${cat.icon || 'fa-box'}"></i>
                            <span>${cat.name}</span>
                        `;
                        button.addEventListener('click', () => {
                            document.getElementById('category-filter').value = cat.id;
                            loadProducts(cat.id);
                            document.getElementById('products').scrollIntoView({ behavior: 'smooth' });
                            updateActivePill(cat.id);
                        });
                        container.appendChild(button);

                        // Add to filter
                        const option = document.createElement('option');
                        option.value = cat.id;
                        option.textContent = cat.name;
                        filter.appendChild(option);
                    });
                }
            } catch (error) {
                devLog('Error loading categories:', error);
            }
        }

        function updateActivePill(categoryId) {
            document.querySelectorAll('.category-pill').forEach(pill => {
                if (pill.dataset.categoryId == categoryId || (!categoryId && pill.dataset.categoryId === '')) {
                    pill.classList.add('active');
                } else {
                    pill.classList.remove('active');
                }
            });
        }

        // Load products
        async function loadProducts(categoryId = null, searchTerm = null) {
            const loading = document.getElementById('loading');
            const container = document.getElementById('products-container');
            const noProducts = document.getElementById('no-products');

            loading.style.display = 'block';
            container.innerHTML = '';
            noProducts.style.display = 'none';

            try {
                let url = `${API_BASE}/products/index.php?is_active=1`;
                if (categoryId) url += `&category_id=${categoryId}`;
                if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;

                const response = await fetch(url);
                const data = await response.json();

                loading.style.display = 'none';

                if (data.success && data.data.products && data.data.products.length > 0) {
                    // Update stats
                    document.getElementById('stat-products').textContent = data.data.products.length;

                    data.data.products.forEach(product => {
                        const productCard = document.createElement('div');
                        const stockStatus = (product.quantity_available || 0) > 0 ? 'In Stock' : 'Out of Stock';
                        const stockClass = (product.quantity_available || 0) > 0 ? 'success' : 'danger';
                        const stockIcon = (product.quantity_available || 0) > 0 ? 'check-circle' : 'times-circle';

                        // Auto-detect base path from current URL
                        const isDevEnvironment = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
            const basePath = isDevEnvironment ? '/core1' : '';

                        // Check if product has an image
                        // Fix double assets/img paths and ensure absolute path
                        const fixImageUrl = (url) => {
                            if (!url) return '';
                            // Remove double assets/img prefixes
                            url = url.replace(/assets\/img\/assets\/img\//g, 'assets/img/');
                            // Convert relative paths to absolute paths
                            if (url.startsWith('assets/')) {
                                url = basePath + '/public/' + url;
                            }
                            return url;
                        };
                        const imageUrl = fixImageUrl(product.image_url);
                        const imageHtml = imageUrl
                            ? `<img src="${imageUrl}" alt="${product.name}" class="product-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />`
                            : '';

                        productCard.innerHTML = `
                            <div class="product-card fade-in" style="cursor: pointer;" onclick="window.location.href='product.php?id=${product.id}'">
                                <div class="product-image-placeholder">
                                    ${imageHtml}
                                    <div class="product-image-fallback" style="display: ${product.image_url ? 'none' : 'flex'};">
                                        <i class="fas fa-microchip fa-3x text-accent"></i>
                                    </div>
                                </div>
                                <div class="product-card-body">
                                    <div class="product-brand">${product.brand || 'Generic'}</div>
                                    <h5 class="product-title">${product.name}</h5>
                                    <div class="product-price">₱${parseFloat(product.selling_price).toFixed(2)}</div>
                                    <div class="d-flex justify-content-between align-items-center mt-3 gap-2">
                                        <small class="text-${stockClass}">
                                            <i class="fas fa-${stockIcon} me-1"></i>
                                            ${stockStatus}
                                        </small>
                                        <button class="btn btn-accent btn-sm"
                                                onclick='event.stopPropagation(); Cart.add(${JSON.stringify(product).replace(/'/g, "\\'")})'
                                                ${(product.quantity_available || 0) <= 0 ? 'disabled' : ''}>
                                            <i class="fas fa-cart-plus"></i>
                                            Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.appendChild(productCard);
                    });
                } else {
                    noProducts.style.display = 'block';
                }
            } catch (error) {
                devLog('Error loading products:', error);
                loading.style.display = 'none';
                noProducts.style.display = 'block';
            }
        }

        // Search products
        function searchProducts() {
            const searchTerm = document.getElementById('search-input').value.trim();
            if (searchTerm) {
                document.getElementById('category-filter').value = '';
                loadProducts(null, searchTerm);
                document.getElementById('products').scrollIntoView({ behavior: 'smooth' });
                updateActivePill(null);
            }
        }

        // Event listeners
        document.getElementById('category-filter').addEventListener('change', (e) => {
            loadProducts(e.target.value || null);
            updateActivePill(e.target.value);
        });

        document.getElementById('search-btn').addEventListener('click', searchProducts);
        document.getElementById('search-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') searchProducts();
        });

        // Cart button click handler - will be attached after initCart() runs
        function handleCartClick(e) {
            e.preventDefault();
            if (!isAuthenticated) {
                // Redirect to login if not authenticated
                window.location.href = 'login.php';
                return;
            }
            if (Cart.items.length === 0) {
                Cart.showNotification('Your cart is empty', 'info');
            } else {
                Cart.showNotification(`Cart has ${Cart.items.length} item(s)`, 'info');
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', async () => {
            initCart(); // Check authentication first
            loadCategories();
            loadProducts();

            // Smooth scroll
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    const href = this.getAttribute('href');
                    // Skip links that are just "#" or invalid selectors
                    if (!href || href === '#' || href.length <= 1) return;

                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
        });

        // Expose Cart globally
        window.Cart = Cart;
    </script>

    <!-- Help Button -->
    <div class="help-button" id="help-button" title="Shopping Help & Guide">
        <i class="fas fa-question"></i>
    </div>

    <!-- Help Modal -->
    <div class="help-overlay" id="help-overlay">
        <div class="help-panel" id="help-panel">
            <!-- Help Header -->
            <div class="help-header">
                <div class="help-header-content">
                    <div class="help-header-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h2>Shopping Guide</h2>
                </div>
                <button class="help-close" id="help-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Help Body -->
            <div class="help-body">
                <!-- Getting Started Section -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <i class="fas fa-shopping-bag"></i>
                        Welcome to PC Parts Central
                    </h3>
                    <div class="help-section-content">
                        <p>Welcome to PC Parts Central - your one-stop shop for all PC components and accessories!</p>
                        <p>This guide will help you navigate the shop, find products, and complete your purchase quickly and easily.</p>
                    </div>
                </div>

                <div class="help-divider"></div>

                <!-- Browsing Products -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <i class="fas fa-search"></i>
                        Finding Products
                    </h3>
                    <div class="help-section-content">
                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-list"></i>
                                Browse All Products
                            </div>
                            <div class="help-card-content">
                                <p>Scroll through our complete product catalog on the main page. All available products are displayed with:</p>
                                <ul>
                                    <li>Product image</li>
                                    <li>Product name and brand</li>
                                    <li>Current price</li>
                                    <li>Stock availability</li>
                                    <li>Quick "Add to Cart" button</li>
                                </ul>
                            </div>
                        </div>

                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-search"></i>
                                Search Products
                            </div>
                            <div class="help-card-content">
                                <p>Use the search box at the top of the page to quickly find specific products:</p>
                                <ol>
                                    <li>Type product name, brand, or keywords (e.g., "RTX 4090", "AMD Ryzen", "16GB RAM")</li>
                                    <li>Results update automatically as you type</li>
                                    <li>Search covers product names, SKUs, and descriptions</li>
                                </ol>
                                <p style="margin-top: 12px;"><span class="help-badge">Tip</span> Be specific for better results (e.g., "RTX 4090" instead of just "GPU")</p>
                            </div>
                        </div>

                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-filter"></i>
                                Filter by Category
                            </div>
                            <div class="help-card-content">
                                <p>Use the category filter to narrow down your search:</p>
                                <ul>
                                    <li>Click the <strong>Category</strong> dropdown</li>
                                    <li>Select a category (e.g., Graphics Cards, Processors, Memory)</li>
                                    <li>Only products in that category will be displayed</li>
                                    <li>Select "All Categories" to see everything again</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="help-divider"></div>

                <!-- Product Details -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <i class="fas fa-info-circle"></i>
                        Product Information
                    </h3>
                    <div class="help-section-content">
                        <p>Each product card displays important information:</p>
                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-tag"></i>
                                Understanding Product Cards
                            </div>
                            <div class="help-card-content">
                                <ul>
                                    <li><strong>Product Name:</strong> Full name of the item</li>
                                    <li><strong>Brand:</strong> Manufacturer (AMD, Intel, NVIDIA, etc.)</li>
                                    <li><strong>Price:</strong> Current selling price</li>
                                    <li><strong>Stock Status:</strong>
                                        <ul style="margin-top: 8px;">
                                            <li><span class="help-badge">In Stock</span> Available for purchase</li>
                                            <li><span class="help-badge">Low Stock</span> Limited quantity remaining</li>
                                            <li><span class="help-badge">Out of Stock</span> Not currently available</li>
                                        </ul>
                                    </li>
                                    <li><strong>Description:</strong> Product specifications and details</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="help-divider"></div>

                <!-- Shopping Cart -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <i class="fas fa-shopping-cart"></i>
                        Using the Shopping Cart
                    </h3>
                    <div class="help-section-content">
                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-plus-circle"></i>
                                Adding Items to Cart
                            </div>
                            <div class="help-card-content">
                                <ol>
                                    <li>Find the product you want to purchase</li>
                                    <li>Click the <strong>Add to Cart</strong> button</li>
                                    <li>A notification will confirm the item was added</li>
                                    <li>The cart icon in the navigation bar will update with the item count</li>
                                </ol>
                                <p style="margin-top: 12px;"><span class="help-badge">Note</span> You can only add items that are currently in stock</p>
                            </div>
                        </div>

                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-shopping-basket"></i>
                                Viewing Your Cart
                            </div>
                            <div class="help-card-content">
                                <p>Click the <strong>Cart</strong> icon in the navigation bar to view your cart. You can:</p>
                                <ul>
                                    <li>See all items you've added</li>
                                    <li>Adjust quantities using +/- buttons</li>
                                    <li>Remove items by clicking the trash icon</li>
                                    <li>View subtotal for each item</li>
                                    <li>See your cart total</li>
                                </ul>
                            </div>
                        </div>

                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-edit"></i>
                                Modifying Cart Items
                            </div>
                            <div class="help-card-content">
                                <p><strong>Increase Quantity:</strong> Click the <strong>+</strong> button next to the quantity</p>
                                <p><strong>Decrease Quantity:</strong> Click the <strong>-</strong> button next to the quantity</p>
                                <p><strong>Remove Item:</strong> Click the <strong>trash icon</strong> to remove from cart</p>
                                <p style="margin-top: 12px;"><span class="help-badge">Auto-Update</span> Cart total updates automatically when you modify quantities</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="help-divider"></div>

                <!-- Checkout Process -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <i class="fas fa-credit-card"></i>
                        Checkout & Payment
                    </h3>
                    <div class="help-section-content">
                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-check-circle"></i>
                                Completing Your Purchase
                            </div>
                            <div class="help-card-content">
                                <ol>
                                    <li>Review your cart items and quantities</li>
                                    <li>Verify the total amount is correct</li>
                                    <li>Click the <strong>Proceed to Checkout</strong> button</li>
                                    <li>Follow the checkout instructions</li>
                                    <li>Provide delivery information (if applicable)</li>
                                    <li>Select your preferred payment method</li>
                                    <li>Complete payment to finalize your order</li>
                                </ol>
                                <p style="margin-top: 12px;"><span class="help-badge">Secure</span> All transactions are processed securely</p>
                            </div>
                        </div>

                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-money-bill-wave"></i>
                                Payment Methods
                            </div>
                            <div class="help-card-content">
                                <p>We accept the following payment methods:</p>
                                <ul>
                                    <li>Cash (for in-store pickup)</li>
                                    <li>Credit/Debit Cards (Visa, Mastercard, etc.)</li>
                                    <li>Bank Transfer</li>
                                    <li>Digital Wallets</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="help-divider"></div>

                <!-- Product Categories -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <i class="fas fa-sitemap"></i>
                        Product Categories
                    </h3>
                    <div class="help-section-content">
                        <p>Our products are organized into the following categories:</p>
                        <ul>
                            <li><strong>Processors (CPUs):</strong> Intel and AMD processors for all budgets</li>
                            <li><strong>Graphics Cards (GPUs):</strong> NVIDIA and AMD graphics cards</li>
                            <li><strong>Memory (RAM):</strong> DDR4 and DDR5 memory modules</li>
                            <li><strong>Storage:</strong> SSDs, HDDs, and NVMe drives</li>
                            <li><strong>Motherboards:</strong> Compatible with Intel and AMD processors</li>
                            <li><strong>Power Supplies (PSUs):</strong> Modular and non-modular options</li>
                            <li><strong>Cases:</strong> Mid-tower, full-tower, and mini-ITX cases</li>
                            <li><strong>Cooling:</strong> Air and liquid cooling solutions</li>
                            <li><strong>Peripherals:</strong> Keyboards, mice, monitors, and more</li>
                        </ul>
                    </div>
                </div>

                <div class="help-divider"></div>

                <!-- Tips for Shopping -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <i class="fas fa-lightbulb"></i>
                        Shopping Tips
                    </h3>
                    <div class="help-section-content">
                        <ul>
                            <li><strong>Compare Products:</strong> Use the search and filter features to compare similar products</li>
                            <li><strong>Check Stock:</strong> Products marked "Low Stock" may sell out quickly</li>
                            <li><strong>Read Descriptions:</strong> Check product specifications to ensure compatibility</li>
                            <li><strong>Plan Your Build:</strong> Make sure all components are compatible with each other</li>
                            <li><strong>Check Your Cart:</strong> Review quantities and items before checkout</li>
                            <li><strong>Contact Us:</strong> Need help choosing? Contact our staff for assistance</li>
                        </ul>
                    </div>
                </div>

                <div class="help-divider"></div>

                <!-- FAQ -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <i class="fas fa-question-circle"></i>
                        Frequently Asked Questions
                    </h3>
                    <div class="help-section-content">
                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-truck"></i>
                                Delivery & Pickup
                            </div>
                            <div class="help-card-content">
                                <p><strong>Do you offer delivery?</strong> Yes, we offer delivery for all products. Delivery fees vary by location.</p>
                                <p><strong>Can I pick up in-store?</strong> Yes, in-store pickup is available. Select this option at checkout.</p>
                                <p><strong>How long does delivery take?</strong> Standard delivery takes 3-5 business days.</p>
                            </div>
                        </div>

                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-undo"></i>
                                Returns & Warranty
                            </div>
                            <div class="help-card-content">
                                <p><strong>What's your return policy?</strong> We offer 30-day returns on most products in original condition.</p>
                                <p><strong>Do products come with warranty?</strong> Yes, all products include manufacturer warranty.</p>
                                <p><strong>What if I receive a defective item?</strong> Contact us immediately for a replacement or refund.</p>
                            </div>
                        </div>

                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-headset"></i>
                                Customer Support
                            </div>
                            <div class="help-card-content">
                                <p><strong>Need help?</strong> Our customer support team is here to assist you.</p>
                                <p><strong>Not sure about compatibility?</strong> Contact us for expert advice on building your PC.</p>
                                <p><strong>Have questions?</strong> Visit our store or call us during business hours.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="help-divider"></div>

                <!-- Troubleshooting -->
                <div class="help-section">
                    <h3 class="help-section-title">
                        <i class="fas fa-wrench"></i>
                        Troubleshooting
                    </h3>
                    <div class="help-section-content">
                        <div class="help-card">
                            <div class="help-card-title">
                                <i class="fas fa-exclamation-triangle"></i>
                                Common Issues
                            </div>
                            <div class="help-card-content">
                                <p><strong>Can't add item to cart:</strong> The product may be out of stock. Check the stock status.</p>
                                <p><strong>Cart not updating:</strong> Try refreshing the page or clearing your browser cache.</p>
                                <p><strong>Product not showing in search:</strong> Try different keywords or browse by category.</p>
                                <p><strong>Checkout not working:</strong> Ensure all required fields are filled and your cart isn't empty.</p>
                                <p><strong>Images not loading:</strong> Check your internet connection and try refreshing the page.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help Footer -->
            <div class="help-footer">
                <p class="help-footer-text">
                    Made with <i class="fas fa-heart"></i> for PC Parts Central Customers
                </p>
            </div>
        </div>
    </div>

    <!-- Help System JavaScript -->
    <script>
        // Help System
        const shopHelpButton = document.getElementById('help-button');
        const shopHelpOverlay = document.getElementById('help-overlay');
        const shopHelpPanel = document.getElementById('help-panel');
        const shopHelpClose = document.getElementById('help-close');

        // Open help panel
        shopHelpButton.addEventListener('click', () => {
            shopHelpOverlay.classList.add('active');
            shopHelpPanel.classList.add('active');
        });

        // Close help panel
        shopHelpClose.addEventListener('click', () => {
            shopHelpOverlay.classList.remove('active');
            shopHelpPanel.classList.remove('active');
        });

        // Close on overlay click
        shopHelpOverlay.addEventListener('click', (e) => {
            if (e.target === shopHelpOverlay) {
                shopHelpOverlay.classList.remove('active');
                shopHelpPanel.classList.remove('active');
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && shopHelpPanel.classList.contains('active')) {
                shopHelpOverlay.classList.remove('active');
                shopHelpPanel.classList.remove('active');
            }
        });

        // Quick help shortcut: Press '?' to open help
        document.addEventListener('keydown', (e) => {
            if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                // Only if not typing in an input field
                if (!['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                    e.preventDefault();
                    shopHelpOverlay.classList.add('active');
                    shopHelpPanel.classList.add('active');
                }
            }
        });
    </script>
</body>
</html>
