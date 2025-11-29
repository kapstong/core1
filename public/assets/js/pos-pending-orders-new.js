/**
 * POS Pending Orders Management
 * Handles loading, displaying, and managing pending customer orders
 * Note: currentOrderId is declared in pos.php
 */

/**
 * Load and display pending orders
 */
async function loadPendingOrders() {
    const loadingEl = document.getElementById('pending-orders-loading');
    const emptyEl = document.getElementById('pending-orders-empty');
    const listEl = document.getElementById('pending-orders-list');
    const countBadge = document.getElementById('pending-orders-count');

    if (!loadingEl || !emptyEl || !listEl) {
        console.error('Pending orders UI elements not found');
        return;
    }

    // Show loading state
    loadingEl.classList.remove('d-none');
    emptyEl.classList.add('d-none');
    listEl.innerHTML = '';

    try {
        // Fetch pending orders from API
        const response = await fetch('../backend/api/pos/pending-orders.php', {
            method: 'GET',
            credentials: 'include'
        });

        console.log('API Response status:', response.status, response.statusText);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('API Error Response:', errorText);
            // Try the old endpoint as fallback
            console.log('Trying fallback endpoint...');
            return loadPendingOrdersLegacy();
        }

        const data = await response.json();
        console.log('Pending orders loaded:', data);
        console.log('Orders array:', data.data?.orders);
        console.log('Data structure check - success:', data.success, 'has data:', !!data.data, 'has orders:', !!data.data?.orders);

        loadingEl.classList.add('d-none');

        // Check if we have orders
        if (data.success && data.data && data.data.orders && Array.isArray(data.data.orders) && data.data.orders.length > 0) {
            const orders = data.data.orders;
            console.log('Rendering', orders.length, 'orders');

            // Update count badge
            if (countBadge) {
                countBadge.textContent = orders.length;
            }

            // Render order cards - using simple div blocks instead of Bootstrap grid for guaranteed visibility
            const cardsHTML = orders.map(order => {
                console.log('Order to render:', order);
                return `
                <div style="display: block; margin-bottom: 1rem; padding: 1rem; background: #ffcccc; border: 2px solid #ff0000; border-radius: 0.5rem; cursor: pointer;"
                     class="pending-order-card"
                     onmouseover="this.style.borderColor='#00ff00';"
                     onmouseout="this.style.borderColor='#ff0000';"
                     onclick="viewOrderDetails(${order.id})">
                        <div style="color: #000000;">
                            <h4 style="color: #0000ff; font-weight: bold; margin-bottom: 0.5rem;">
                                <i class="fas fa-receipt"></i> ${order.order_number}
                            </h4>
                            <p style="margin-bottom: 0.5rem;"><strong>Date:</strong> ${new Date(order.created_at || order.order_date).toLocaleDateString()}</p>
                            <p style="margin-bottom: 0.5rem;"><strong>Customer:</strong> ${order.customer_name || 'N/A'}</p>
                            <p style="margin-bottom: 0.5rem;"><strong>Email:</strong> ${order.email || 'N/A'}</p>
                            <p style="margin-bottom: 0.5rem;"><strong>Items:</strong> ${order.items_count || 0} item(s)</p>
                            <p style="margin-bottom: 1rem;"><strong>Total: ₱${parseFloat(order.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong></p>
                            <button style="background: #007bff; color: white; border: none; padding: 0.5rem 1rem; border-radius: 0.25rem; cursor: pointer;" onclick="event.stopPropagation(); viewOrderDetails(${order.id})">
                                <i class="fas fa-arrow-right"></i> View Details
                            </button>
                        </div>
                </div>
                `;
            }).join('');
            
            console.log('Generated HTML length:', cardsHTML.length);
            console.log('Cards HTML preview:', cardsHTML.substring(0, 200) + '...');
            console.log('Setting innerHTML to listEl:', listEl);
            listEl.innerHTML = cardsHTML;
            console.log('innerHTML set successfully, listEl now has:', listEl.innerHTML.length, 'characters');
            console.log('Number of child elements in listEl:', listEl.children.length);

            // Verify the structure after setting HTML
            const firstCard = listEl.querySelector('div[style*="display: block"]');
            if (firstCard) {
                console.log('First card element found and displayed');
                console.log('Card element:', firstCard);
                console.log('Card text content:', firstCard.textContent.substring(0, 100) + '...');
            } else {
                console.log('WARNING: No card element found after setting HTML!');
                console.log('Current innerHTML:', listEl.innerHTML.substring(0, 500) + '...');
                console.log('List of child elements:');
                Array.from(listEl.children).forEach((child, index) => {
                    console.log(`Child ${index}:`, child.tagName, child.className, child.style.display);
                });
            }

        } else {
            // No pending orders
            emptyEl.classList.remove('d-none');
            if (countBadge) {
                countBadge.textContent = '0';
            }
        }

    } catch (error) {
        console.error('Error loading pending orders:', error);
        loadingEl.classList.add('d-none');
        listEl.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Error Loading Orders</strong><br>
                ${error.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
    }
}

/**
 * View order details in a modal
 */
async function viewOrderDetails(orderId) {
    currentOrderId = orderId;
    
    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    const contentEl = document.getElementById('order-details-content');
    
    if (!contentEl) {
        console.error('Order details modal not found');
        return;
    }

    contentEl.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-accent" role="status"></div></div>';
    modal.show();

    try {
        // Fetch full order details
        const response = await fetch(`../backend/api/shop/orders.php?action=details&id=${orderId}`, {
            method: 'GET',
            credentials: 'include'
        });

        if (!response.ok) {
            throw new Error(`Failed to fetch order details: ${response.status}`);
        }

        const data = await response.json();
        console.log('Order details:', data);

        if (data.success && data.data && data.data.order) {
            const order = data.data.order;
            const items = order.items || [];

            const itemsHTML = items.length > 0
                ? items.map(item => `
                    <tr>
                        <td>
                            <small>${item.product_name || 'Unknown Product'}</small>
                        </td>
                        <td class="text-end">
                            <small>${item.quantity || 0} x ₱${parseFloat(item.price || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</small>
                        </td>
                        <td class="text-end">
                            <small>₱${parseFloat((item.quantity || 0) * (item.price || 0)).toLocaleString('en-US', {minimumFractionDigits: 2})}</small>
                        </td>
                    </tr>
                `).join('')
                : '<tr><td colspan="3" class="text-center text-muted">No items</td></tr>';

            contentEl.innerHTML = `
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Order Number</h6>
                        <p class="fs-6 fw-bold">${order.order_number}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Order Date</h6>
                        <p class="fs-6">${new Date(order.created_at || order.order_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</p>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Customer</h6>
                        <p class="fs-6">${order.customer_name}</p>
                        <small class="text-muted">${order.email}<br>${order.phone}</small>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Payment Method</h6>
                        <p class="fs-6">${order.payment_method}</p>
                        <small class="badge bg-info">${order.payment_status || 'pending'}</small>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <h6 class="text-muted mb-3">Order Items</h6>
                        <table class="table table-sm table-borderless">
                            <thead style="border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color);">
                                <tr>
                                    <th><small>Product</small></th>
                                    <th class="text-end"><small>Qty × Price</small></th>
                                    <th class="text-end"><small>Subtotal</small></th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHTML}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row mb-4" style="background: var(--bg-tertiary); padding: 1rem; border-radius: 0.5rem;">
                    <div class="col-6">
                        <small class="text-muted">Subtotal:</small><br>
                        <strong>₱${parseFloat(order.subtotal || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Tax:</small><br>
                        <strong>₱${parseFloat(order.tax_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong>
                    </div>
                    <div class="col-6 mt-2">
                        <small class="text-muted">Shipping:</small><br>
                        <strong>₱${parseFloat(order.shipping_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong>
                    </div>
                    <div class="col-6 mt-2">
                        <small class="text-muted">Total:</small><br>
                        <strong class="text-success fs-5">₱${parseFloat(order.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <button class="btn btn-sm btn-success w-100" onclick="approveOrder(${orderId})">
                            <i class="fas fa-check me-1"></i>Approve
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-sm btn-danger w-100" onclick="rejectOrder(${orderId})">
                            <i class="fas fa-times me-1"></i>Reject
                        </button>
                    </div>
                </div>
            `;

        } else {
            contentEl.innerHTML = '<div class="alert alert-warning">Order details not found</div>';
        }

    } catch (error) {
        console.error('Error loading order details:', error);
        contentEl.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
    }
}

/**
 * Approve an order
 */
async function approveOrder(orderId) {
    if (!confirm('Are you sure you want to approve this order?')) {
        return;
    }

    try {
        const response = await fetch('../backend/api/orders/approve.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                order_id: orderId,
                action: 'approve'
            })
        });

        const data = await response.json();

        if (data.success) {
            if (typeof showToast !== 'undefined') {
                showToast('Order approved successfully!', 'success');
            }

            // Close modal and reload
            const modal = bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal'));
            if (modal) modal.hide();

            // Reload pending orders
            setTimeout(() => loadPendingOrders(), 500);
        } else {
            throw new Error(data.message || 'Failed to approve order');
        }

    } catch (error) {
        console.error('Error approving order:', error);
        if (typeof showToast !== 'undefined') {
            showToast('Error: ' + error.message, 'error');
        } else {
            alert('Error: ' + error.message);
        }
    }
}

/**
 * Reject an order
 */
async function rejectOrder(orderId) {
    const reason = prompt('Please provide a reason for rejection:');
    if (!reason) return;

    try {
        const response = await fetch('../backend/api/orders/approve.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify({
                order_id: orderId,
                action: 'reject',
                reason: reason
            })
        });

        const data = await response.json();

        if (data.success) {
            if (typeof showToast !== 'undefined') {
                showToast('Order rejected successfully!', 'info');
            }

            // Close modal and reload
            const modal = bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal'));
            if (modal) modal.hide();

            // Reload pending orders
            setTimeout(() => loadPendingOrders(), 500);
        } else {
            throw new Error(data.message || 'Failed to reject order');
        }

    } catch (error) {
        console.error('Error rejecting order:', error);
        if (typeof showToast !== 'undefined') {
            showToast('Error: ' + error.message, 'error');
        } else {
            alert('Error: ' + error.message);
        }
    }
}

/**
 * Show pending orders panel using Bootstrap's Tab API
 */
function showPendingOrdersPanel() {
    console.log('Showing pending orders panel using Bootstrap Tab API');

    const tabButton = document.getElementById('pending-orders-tab');
    if (!tabButton) {
        console.error('Pending orders tab button not found!');
        return;
    }

    try {
        // Use Bootstrap's Tab API to properly show the tab
        const tab = new bootstrap.Tab(tabButton);
        tab.show();

        console.log('Successfully triggered Bootstrap tab show()');
    } catch (error) {
        console.error('Error using Bootstrap Tab API:', error);

        // Fallback: manually trigger click
        console.log('Fallback: triggering click event');
        tabButton.click();
    }
}

/**
 * Fallback function using legacy shop/orders.php endpoint
 */
async function loadPendingOrdersLegacy() {
    console.log('Using legacy endpoint for pending orders');
    const loadingEl = document.getElementById('pending-orders-loading');
    const emptyEl = document.getElementById('pending-orders-empty');
    const listEl = document.getElementById('pending-orders-list');
    const countBadge = document.getElementById('pending-orders-count');

    loadingEl.classList.remove('d-none');
    emptyEl.classList.add('d-none');
    listEl.innerHTML = '';

    try {
        const response = await fetch('../backend/api/shop/orders.php?status=pending', {
            method: 'GET',
            credentials: 'include'
        });

        if (!response.ok) {
            throw new Error(`Legacy endpoint failed: ${response.status}`);
        }

        const data = await response.json();
        console.log('Legacy endpoint response:', data);

        loadingEl.classList.add('d-none');

        if (data.success && data.data && data.data.orders && Array.isArray(data.data.orders) && data.data.orders.length > 0) {
            const orders = data.data.orders;

            if (countBadge) {
                countBadge.textContent = orders.length;
            }

            const cardsHTML = orders.map(order => `
                <div class="col-md-6 col-lg-4" style="min-height: 300px;">
                    <div class="card h-100" style="background: var(--bg-secondary); border: 2px solid var(--border-color); cursor: pointer; transition: all 0.3s ease;" 
                         onclick="viewOrderDetails(${order.id})">
                            <div class="card-body" style="color: var(--text-primary);">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="card-title mb-1" style="color: var(--accent); font-weight: bold;">
                                            <i class="fas fa-receipt me-2"></i>${order.order_number}
                                        </h6>
                                        <small class="text-muted">${new Date(order.created_at || order.order_date).toLocaleDateString()}</small>
                                    </div>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                </div>
                                
                                <div class="mb-2" style="padding: 0.75rem 0;">
                                    <small style="color: var(--text-primary);"><i class="fas fa-user me-2"></i>${order.customer_name || 'N/A'}</small><br>
                                    <small style="color: var(--text-primary);"><i class="fas fa-envelope me-2"></i>${order.email || 'N/A'}</small>
                                </div>

                                <div class="mb-2">
                                    <small><i class="fas fa-box me-2"></i><strong>${order.items_count || 0}</strong> item(s)</small><br>
                                    <small><i class="fas fa-credit-card me-2"></i>Payment: <strong>${order.payment_method || 'N/A'}</strong></small>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-3 pt-2" style="border-top: 1px solid var(--border-color);">
                                    <div>
                                        <small>Total:</small><br>
                                        <strong class="text-success" style="font-size: 1.25rem;">₱${parseFloat(order.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); viewOrderDetails(${order.id})">
                                        <i class="fas fa-arrow-right me-1"></i>Details
                                    </button>
                                </div>
                            </div>
                    </div>
                </div>
            `).join('');

            listEl.innerHTML = cardsHTML;
        } else {
            emptyEl.classList.remove('d-none');
            if (countBadge) {
                countBadge.textContent = '0';
            }
        }
    } catch (error) {
        console.error('Legacy endpoint error:', error);
        loadingEl.classList.add('d-none');
        listEl.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize pending orders functionality
        initializePendingOrders();
    });
} else {
    // Initialize pending orders functionality immediately
    initializePendingOrders();
}

/**
 * Initialize pending orders functionality
 */
function initializePendingOrders() {
    console.log('Initializing pending orders functionality');

    const pendingTab = document.getElementById('pending-orders-tab');
    if (pendingTab) {
        console.log('Found pending orders tab, adding event listeners');

        // TEMPORARY: Force show the pending orders tab immediately to test if data loads correctly when visible
        console.log('FORCING PENDING ORDERS TAB TO SHOW ON PAGE LOAD...');
        setTimeout(() => {
            console.log('Activating pending orders tab...');
            const tab = new bootstrap.Tab(pendingTab);
            tab.show();

            // Load data after the tab is shown
            setTimeout(() => {
                console.log('Loading pending orders data after tab activation...');
                loadPendingOrders();
            }, 100);
        }, 1000); // Wait 1 second to let page load

        // Also listen for Bootstrap tab shown event for normal operation
        pendingTab.addEventListener('shown.bs.tab', function(event) {
            console.log('Pending orders tab shown');
            setTimeout(() => loadPendingOrders(), 100); // Load data after tab transition completes
        });

        console.log('Event listeners attached to pending orders tab');
    } else {
        console.log('Pending orders tab not found!');
    }
}
