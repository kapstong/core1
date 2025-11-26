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
    <title>Product Details - PC Parts Central</title>
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
        .product-hero {
            padding: 4rem 0;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, rgba(0, 245, 255, 0.05) 100%);
        }

        .product-image-container {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
        }

        .product-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 1rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .product-info {
            padding: 2rem;
        }

        .product-title {
            font-size: 2.5rem;
            font-weight: 900;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .product-brand {
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .product-price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 1.5rem;
        }

        .product-description {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .specs-section {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            margin: 2rem 0;
        }

        .specs-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .specs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .spec-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .spec-label {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .spec-value {
            font-weight: 700;
            color: var(--accent);
        }

        .rating-section {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            margin: 2rem 0;
        }

        .rating-stars {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .star {
            font-size: 1.5rem;
            color: #6c757d !important;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .star.active {
            color: #ffd700 !important;
        }

        .rating-summary {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .rating-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }

        .rating-text {
            color: var(--text-muted);
        }

        .reviews-section {
            margin-top: 3rem;
        }

        .review-card {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .review-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .review-author {
            font-weight: 600;
            color: var(--primary);
        }

        .review-date {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .review-rating {
            color: #ffd700;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 2rem 0;
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--border-accent);
            background: transparent;
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: var(--accent);
            color: var(--bg-primary);
        }

        .quantity-input {
            width: 80px;
            text-align: center;
            padding: 0.5rem;
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            background: var(--bg-glass);
            color: var(--text-primary);
        }

        .stock-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .stock-status.in-stock {
            background: rgba(25, 135, 84, 0.2);
            color: #198754;
            border: 1px solid rgba(25, 135, 84, 0.3);
        }

        .stock-status.out-of-stock {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .breadcrumb-custom {
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .breadcrumb-custom .breadcrumb-item a {
            color: var(--accent);
            text-decoration: none;
        }

        .breadcrumb-custom .breadcrumb-item.active {
            color: var(--text-primary);
        }

        /* Enhanced Cart Button Styles */
        .cart-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
            color: white;
            text-decoration: none;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 245, 255, 0.3);
            position: relative;
            border: none;
            cursor: pointer;
        }

        .cart-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 245, 255, 0.4);
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
        }

        .cart-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-icon {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .cart-button:hover .cart-icon {
            transform: scale(1.1);
        }

        .cart-count-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            box-shadow: 0 0 15px rgba(255, 51, 102, 0.6);
            border: 2px solid var(--bg-primary);
            min-width: 20px;
            transition: all 0.3s ease;
        }

        .cart-count-badge:empty {
            display: none;
        }

        .cart-text {
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        @media (max-width: 768px) {
            .product-title {
                font-size: 2rem;
            }

            .product-image {
                height: 300px;
            }

            .specs-grid {
                grid-template-columns: 1fr;
            }

            .cart-button {
                padding: 0.6rem 0.8rem;
                font-size: 0.8rem;
            }

            .cart-icon {
                font-size: 1rem;
            }

            .cart-text {
                display: none; /* Hide text on mobile for space */
            }

            .cart-count-badge {
                width: 18px;
                height: 18px;
                font-size: 0.6rem;
                min-width: 18px;
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
                        <i class="fas fa-arrow-left me-1"></i>
                        Back to Shop
                    </a>
                    <div id="cart-container" style="display: none;">
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
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="container">
        <nav aria-label="breadcrumb" class="breadcrumb-custom">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="index.php">Shop</a></li>
                <li class="breadcrumb-item active" id="breadcrumb-category">Category</li>
                <li class="breadcrumb-item active" id="breadcrumb-product">Product</li>
            </ol>
        </nav>
    </div>

    <!-- Product Hero -->
    <section class="product-hero">
        <div class="container">
            <div class="row g-5">
                <!-- Product Image -->
                <div class="col-lg-6">
                    <div class="product-image-container">
                        <img id="product-image" src="" alt="Product Image" class="product-image">
                    </div>
                </div>

                <!-- Product Info -->
                <div class="col-lg-6">
                    <div class="product-info">
                        <div class="product-brand" id="product-brand">Brand</div>
                        <h1 class="product-title" id="product-title">Product Title</h1>

                        <!-- Rating -->
                        <div class="rating-summary">
                            <div class="rating-stars" id="product-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="rating-number" id="rating-number">0.0</div>
                            <div class="rating-text" id="rating-count">(0 reviews)</div>
                        </div>

                        <!-- Price -->
                        <div class="product-price" id="product-price">₱0.00</div>

                        <!-- Stock Status -->
                        <div id="stock-status" class="stock-status">
                            <i class="fas fa-check-circle"></i>
                            In Stock
                        </div>

                        <!-- Description -->
                        <div class="product-description" id="product-description">
                            Loading product details...
                        </div>

                        <!-- Quantity Controls -->
                        <div class="quantity-controls">
                            <button class="quantity-btn" id="decrease-qty">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="99">
                            <button class="quantity-btn" id="increase-qty">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>

                        <!-- Add to Cart Button -->
                        <button class="btn btn-accent btn-lg w-100" id="add-to-cart-btn">
                            <i class="fas fa-cart-plus me-2"></i>
                            Add to Cart
                        </button>

                        <!-- Wishlist Button -->
                        <button class="btn btn-outline-primary btn-lg w-100 mt-3" id="wishlist-btn">
                            <i class="fas fa-heart me-2"></i>
                            Add to Wishlist
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Details -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <!-- Specifications -->
                <div class="col-lg-8">
                    <div class="specs-section">
                        <h3 class="specs-title">
                            <i class="fas fa-cogs me-2"></i>
                            Specifications
                        </h3>
                        <div id="specifications" class="specs-grid">
                            <!-- Specifications will be loaded here -->
                        </div>
                    </div>

                    <!-- Reviews Section -->
                    <div class="reviews-section">
                        <h3 class="specs-title">
                            <i class="fas fa-comments me-2"></i>
                            Customer Reviews
                        </h3>

                        <!-- Add Review (if logged in) -->
                        <div id="add-review-section" class="rating-section" style="display: none;">
                            <h4>Write a Review</h4>
                            <div class="rating-stars" id="review-rating">
                                <i class="fas fa-star star" data-rating="1"></i>
                                <i class="fas fa-star star" data-rating="2"></i>
                                <i class="fas fa-star star" data-rating="3"></i>
                                <i class="fas fa-star star" data-rating="4"></i>
                                <i class="fas fa-star star" data-rating="5"></i>
                            </div>
                            <textarea class="form-control mt-3" id="review-text" rows="4" placeholder="Share your experience with this product..."></textarea>
                            <button class="btn btn-accent mt-3" id="submit-review">
                                <i class="fas fa-paper-plane me-2"></i>
                                Submit Review
                            </button>
                        </div>

                        <!-- Reviews List -->
                        <div id="reviews-list">
                            <!-- Reviews will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="specs-section">
                        <h4 class="specs-title">Product Info</h4>
                        <div class="spec-item">
                            <span class="spec-label">SKU:</span>
                            <span class="spec-value" id="product-sku">Loading...</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Category:</span>
                            <span class="spec-value" id="product-category">Loading...</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Brand:</span>
                            <span class="spec-value" id="product-brand-sidebar">Loading...</span>
                        </div>
                        <div class="spec-item">
                            <span class="spec-label">Warranty:</span>
                            <span class="spec-value" id="product-warranty">Loading...</span>
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

    <!-- Product Detail JS -->
    <script>
        const IS_DEVELOPMENT = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        const API_BASE = '/core1/backend/api';

        // Get product ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const productId = urlParams.get('id');

        if (!productId) {
            window.location.href = 'index.php';
        }

        // Authentication
        let isAuthenticated = false;
        let currentUser = null;

        // Cart functionality
        const Cart = {
            items: JSON.parse(localStorage.getItem('cart') || '[]'),

            async add(product, quantity = 1) {
                if (!isAuthenticated) {
                    showNotification('Please login to add items to cart', 'warning');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 1500);
                    return;
                }

                try {
                    // Add to backend API
                    const response = await fetch(`${API_BASE}/shop/cart.php`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            product_id: product.id,
                            quantity: quantity
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Update local storage for badge display
                        const existing = this.items.find(item => item.id === product.id);
                        if (existing) {
                            existing.quantity += quantity;
                        } else {
                            this.items.push({
                                id: product.id,
                                sku: product.sku,
                                name: product.name,
                                price: parseFloat(product.selling_price),
                                quantity: quantity
                            });
                        }
                        this.save();
                        this.updateBadge();
                        showNotification('Added to cart!', 'success');
                    } else {
                        showNotification(data.message || 'Failed to add item to cart', 'danger');
                    }
                } catch (error) {
                    showNotification('Failed to add item to cart. Please try again.', 'danger');
                    if (IS_DEVELOPMENT) console.error('Error adding to cart:', error);
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
            }
        };

        // Check authentication
        async function checkAuthentication() {
            try {
                const response = await fetch(`${API_BASE}/shop/auth.php?action=me`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data.success && data.data && data.data.customer) {
                    // Check if this is a staff user
                    if (data.data.is_staff) {
                        // Redirect staff to the employee dashboard
                        window.location.href = 'dashboard.php';
                        return null;
                    }
                    isAuthenticated = true;
                    currentUser = data.data.customer;
                    // Show cart container when authenticated
                    document.getElementById('cart-container').style.display = 'block';
                    return data.data.customer;
                } else {
                    isAuthenticated = false;
                    // Hide cart container when not authenticated
                    document.getElementById('cart-container').style.display = 'none';
                    return null;
                }
            } catch (error) {
                isAuthenticated = false;
                // Hide cart container when not authenticated
                document.getElementById('cart-container').style.display = 'none';
                return null;
            }
        }

        // Load product details
        async function loadProduct() {
            try {
                const response = await fetch(`${API_BASE}/products/show.php?id=${productId}`);
                const data = await response.json();

                if (data.success && data.data) {
                    const product = data.data;
                    displayProduct(product);
                } else {
                    showNotification('Product not found', 'error');
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                }
            } catch (error) {
                console.error('Error loading product:', error);
                showNotification('Error loading product', 'error');
            }
        }

        // Display product information
        function displayProduct(product) {
            // Basic info
            document.getElementById('product-title').textContent = product.name;
            document.getElementById('product-brand').textContent = product.brand || 'Generic';
            document.getElementById('product-price').textContent = `₱${parseFloat(product.selling_price).toFixed(2)}`;
            document.getElementById('product-description').textContent = product.description || 'No description available.';
            document.getElementById('product-sku').textContent = product.sku;
            document.getElementById('product-category').textContent = product.category_name || 'Uncategorized';
            document.getElementById('product-brand-sidebar').textContent = product.brand || 'Generic';
            document.getElementById('product-warranty').textContent = `${product.warranty_months || 12} months`;

            // Image
            if (product.image_url) {
                document.getElementById('product-image').src = product.image_url;
            }

            // Stock status
            const stockStatus = document.getElementById('stock-status');
            const quantityAvailable = product.quantity_available || 0;
            if (quantityAvailable > 0) {
                stockStatus.className = 'stock-status in-stock';
                stockStatus.innerHTML = `<i class="fas fa-check-circle"></i> In Stock (${quantityAvailable} available)`;
            } else {
                stockStatus.className = 'stock-status out-of-stock';
                stockStatus.innerHTML = `<i class="fas fa-times-circle"></i> Out of Stock`;
                document.getElementById('add-to-cart-btn').disabled = true;
                document.getElementById('quantity').disabled = true;
            }

            // Breadcrumb
            document.getElementById('breadcrumb-category').textContent = product.category_name || 'Category';
            document.getElementById('breadcrumb-product').textContent = product.name;

            // Specifications
            displaySpecifications(product.specifications);

            // Load reviews
            loadReviews();
        }

        // Display specifications
        function displaySpecifications(specs) {
            const container = document.getElementById('specifications');

            if (!specs || Object.keys(specs).length === 0) {
                container.innerHTML = '<p class="text-muted">No specifications available.</p>';
                return;
            }

            container.innerHTML = '';
            Object.entries(specs).forEach(([key, value]) => {
                const specItem = document.createElement('div');
                specItem.className = 'spec-item';
                specItem.innerHTML = `
                    <span class="spec-label">${key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}:</span>
                    <span class="spec-value">${value}</span>
                `;
                container.appendChild(specItem);
            });
        }

        // Load reviews
        async function loadReviews() {
            try {
                const response = await fetch(`${API_BASE}/shop/reviews.php?action=list&product_id=${productId}`);
                const data = await response.json();

                if (data.success) {
                    displayReviews(data.data);
                } else {
                    showNotification('Failed to load reviews', 'error');
                }
            } catch (error) {
                console.error('Error loading reviews:', error);
                showNotification('Error loading reviews', 'error');
            }
        }

        // Display reviews
        function displayReviews(reviewData) {
            const container = document.getElementById('reviews-list');
            const stats = reviewData.stats;

            // Update rating display
            document.getElementById('rating-number').textContent = stats.average_rating.toFixed(1);
            document.getElementById('rating-count').textContent = `(${stats.total_reviews} reviews)`;

            // Update star ratings
            updateStarDisplay(stats.average_rating);

            if (reviewData.reviews.length === 0) {
                container.innerHTML = '<p class="text-muted text-center py-4">No reviews yet. Be the first to review this product!</p>';
                return;
            }

            container.innerHTML = reviewData.reviews.map(review => `
                <div class="review-card">
                    <div class="review-header">
                        <div>
                            <span class="review-author">${review.customer_name}</span>
                            <div class="review-rating">
                                ${generateStars(review.rating)}
                            </div>
                        </div>
                        <span class="review-date">${review.created_at_formatted}</span>
                    </div>
                    ${review.title ? `<h6 class="review-title">${review.title}</h6>` : ''}
                    <p class="mb-2">${review.review_text}</p>
                    ${review.is_verified_purchase ? '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Verified Purchase</small>' : ''}
                    <div class="review-actions mt-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="voteHelpful(${review.id}, 'helpful')">
                            <i class="fas fa-thumbs-up me-1"></i>Helpful (${review.helpful_votes})
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Generate star rating HTML
        function generateStars(rating) {
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= rating) {
                    stars += '<i class="fas fa-star"></i>';
                } else if (i - 0.5 <= rating) {
                    stars += '<i class="fas fa-star-half-alt"></i>';
                } else {
                    stars += '<i class="far fa-star"></i>';
                }
            }
            return stars;
        }

        // Update star display
        function updateStarDisplay(rating) {
            const stars = document.querySelectorAll('#product-rating .fa-star');
            stars.forEach((star, index) => {
                if (index < Math.floor(rating)) {
                    star.className = 'fas fa-star';
                } else if (index < rating) {
                    star.className = 'fas fa-star-half-alt';
                } else {
                    star.className = 'far fa-star';
                }
            });
        }

        // Vote on review helpfulness
        async function voteHelpful(reviewId, voteType) {
            if (!isAuthenticated) {
                showNotification('Please login to vote on reviews', 'warning');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/shop/reviews.php?action=helpful`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        review_id: reviewId,
                        vote_type: voteType
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Thank you for your feedback!', 'success');
                    loadReviews(); // Reload reviews to update counts
                } else {
                    showNotification(data.message || 'Failed to submit vote', 'error');
                }
            } catch (error) {
                console.error('Error voting on review:', error);
                showNotification('Error submitting vote', 'error');
            }
        }

        // Quantity controls
        document.getElementById('decrease-qty').addEventListener('click', () => {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            if (currentValue > 1) {
                input.value = currentValue - 1;
            }
        });

        document.getElementById('increase-qty').addEventListener('click', () => {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            if (currentValue < 99) {
                input.value = currentValue + 1;
            }
        });

        document.getElementById('quantity').addEventListener('input', (e) => {
            let value = parseInt(e.target.value);
            if (isNaN(value) || value < 1) value = 1;
            if (value > 99) value = 99;
            e.target.value = value;
        });

        // Add to cart
        document.getElementById('add-to-cart-btn').addEventListener('click', async () => {
            const quantity = parseInt(document.getElementById('quantity').value);

            try {
                const response = await fetch(`${API_BASE}/products/show.php?id=${productId}`);
                const data = await response.json();

                if (data.success && data.data) {
                    Cart.add(data.data, quantity);
                }
            } catch (error) {
                showNotification('Error adding to cart', 'error');
            }
        });

        // Wishlist functionality
        let isInWishlist = false;

        async function checkWishlistStatus() {
            if (!isAuthenticated) return;

            try {
                const response = await fetch(`${API_BASE}/shop/wishlist.php?action=check&product_id=${productId}`);
                const data = await response.json();

                if (data.success) {
                    isInWishlist = data.in_wishlist;
                    updateWishlistButton();
                }
            } catch (error) {
                console.error('Error checking wishlist status:', error);
            }
        }

        function updateWishlistButton() {
            const btn = document.getElementById('wishlist-btn');
            const icon = btn.querySelector('i');

            if (isInWishlist) {
                btn.innerHTML = '<i class="fas fa-heart me-2"></i> Remove from Wishlist';
                btn.className = 'btn btn-danger btn-lg w-100 mt-3';
            } else {
                btn.innerHTML = '<i class="far fa-heart me-2"></i> Add to Wishlist';
                btn.className = 'btn btn-outline-primary btn-lg w-100 mt-3';
            }
        }

        // Wishlist button click handler
        document.getElementById('wishlist-btn').addEventListener('click', async () => {
            if (!isAuthenticated) {
                showNotification('Please login to manage your wishlist', 'warning');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
                return;
            }

            const action = isInWishlist ? 'remove' : 'add';

            try {
                const response = await fetch(`${API_BASE}/shop/wishlist.php?action=${action}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: parseInt(productId)
                    })
                });

                const data = await response.json();

                if (data.success) {
                    isInWishlist = !isInWishlist;
                    updateWishlistButton();
                    showNotification(data.message, 'success');
                } else {
                    showNotification(data.message || 'Failed to update wishlist', 'error');
                }
            } catch (error) {
                console.error('Error updating wishlist:', error);
                showNotification('Error updating wishlist', 'error');
            }
        });

        // Rating system - attach after DOM is fully loaded
        function initStarRating() {
            const stars = document.querySelectorAll('#review-rating .star');
            if (stars.length === 0) {
                return;
            }

            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);

                    // Remove active class from all stars
                    document.querySelectorAll('#review-rating .star').forEach(s => {
                        s.classList.remove('active');
                        s.style.color = ''; // Reset inline styles
                    });

                    // Add active class to clicked star and all before it
                    for (let i = 1; i <= rating; i++) {
                        const starElement = document.querySelector(`#review-rating .star[data-rating="${i}"]`);
                        if (starElement) {
                            starElement.classList.add('active');
                            // Force color change as backup
                            starElement.style.setProperty('color', '#ffd700', 'important');
                        }
                    }
                });

                // Add hover effect
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.dataset.rating);
                    for (let i = 1; i <= rating; i++) {
                        const starElement = document.querySelector(`#review-rating .star[data-rating="${i}"]`);
                        if (starElement && !starElement.classList.contains('active')) {
                            starElement.style.color = '#ffd700';
                        }
                    }
                });

                star.addEventListener('mouseleave', function() {
                    document.querySelectorAll('#review-rating .star').forEach(s => {
                        if (!s.classList.contains('active')) {
                            s.style.color = '';
                        }
                    });
                });
            });
        }

        // Submit review
        document.getElementById('submit-review').addEventListener('click', async () => {
            // Double-check authentication before submitting
            if (!isAuthenticated || !currentUser) {
                showNotification('Please login to submit a review', 'warning');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 1500);
                return;
            }

            const rating = document.querySelectorAll('#review-rating .star.active').length;
            const reviewText = document.getElementById('review-text').value.trim();
            const reviewTitle = document.getElementById('review-title')?.value.trim() || '';

            if (rating === 0) {
                showNotification('Please select a rating', 'warning');
                return;
            }

            if (!reviewText) {
                showNotification('Please write a review', 'warning');
                return;
            }

            // Disable submit button
            const submitBtn = document.getElementById('submit-review');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';

            try {
                const response = await fetch(`${API_BASE}/shop/reviews.php?action=create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: parseInt(productId),
                        rating: rating,
                        title: reviewTitle,
                        review_text: reviewText
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Review submitted successfully! It will be visible after approval.', 'success');
                    document.getElementById('review-text').value = '';
                    if (document.getElementById('review-title')) {
                        document.getElementById('review-title').value = '';
                    }
                    document.querySelectorAll('#review-rating .star').forEach(s => s.classList.remove('active'));
                    // Reload reviews to show pending status if applicable
                    loadReviews();
                } else {
                    showNotification(data.message || 'Failed to submit review', 'error');
                }
            } catch (error) {
                console.error('Error submitting review:', error);
                showNotification('Error submitting review. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });

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
        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuthentication();
            Cart.updateBadge();

            if (isAuthenticated) {
                document.getElementById('add-review-section').style.display = 'block';
                // Initialize star rating after review section is shown
                setTimeout(() => {
                    initStarRating();
                }, 100);
                // Check wishlist status
                await checkWishlistStatus();
            }

            loadProduct();
        });
    </script>
</body>
</html>
