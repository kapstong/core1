/**
 * POS Pending Orders Functionality
 * Handles customer order approval/rejection workflow
 */

let currentOrderId = null;

async function loadPendingOrders() {
    const loading = document.getElementById('pending-orders-loading');
    const empty = document.getElementById('pending-orders-empty');
    const list = document.getElementById('pending-orders-list');
    const countBadge = document.getElementById('pending-orders-count');

    // Show loading
    loading.classList.remove('d-none');
    empty.classList.add('d-none');
    list.innerHTML = '';

    try {
        const response = await fetch(`../backend/api/shop/orders.php?status=pending`);
        const data = await response.json();

        console.log('Pending orders response:', data);

        loading.classList.add('d-none');

        if (data.success && data.data && data.data.orders && data.data.orders.length > 0) {
            const orders = data.data.orders;

            // Update count badge
            countBadge.textContent = orders.length;

            // Render orders
            list.innerHTML = orders.map(order => `
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100" style="background: var(--bg-secondary); border: 1px solid var(--border-color); cursor: pointer;" onclick="viewOrderDetails(${order.id})">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title mb-0 text-accent">${order.order_number}</h6>
                                <span class="badge bg-warning text-dark">Pending</span>
                            </div>
                            <p class="card-text small text-muted mb-2">
                                <i class="fas fa-calendar me-1"></i>
                                ${new Date(order.created_at || order.order_date).toLocaleDateString('en-US', {
                                    month: 'short',
                                    day: 'numeric',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                })}
                            </p>
                            <p class="card-text small mb-2">
                                <i class="fas fa-user me-1"></i>
                                ${order.customer_name || 'N/A'}
                            </p>
                            <p class="card-text small mb-2">
                                <i class="fas fa-box me-1"></i>
                                ${order.items_count || (order.items ? order.items.length : 0)} item(s)
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <strong class="text-success">₱${parseFloat(order.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>
                                <button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); viewOrderDetails(${order.id})">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

        } else {
            empty.classList.remove('d-none');
            countBadge.textContent = '0';
        }

    } catch (error) {
        console.error('Error loading pending orders:', error);
        loading.classList.add('d-none');
        if (typeof showToast !== 'undefined') {
            showToast('Failed to load pending orders', 'error');
        }
    }
}

async function viewOrderDetails(orderId) {
    currentOrderId = orderId;

    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    const content = document.getElementById('order-details-content');

    content.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-accent"></div></div>';

    modal.show();

    try {
        const response = await fetch(`../backend/api/shop/orders.php?action=details&id=${orderId}`);
        const data = await response.json();

        if (data.success && data.data && data.data.order) {
            const order = data.data.order;

            // Build items HTML
            const itemsHTML = order.items && order.items.length > 0
                ? order.items.map(item => `
                    <tr>
                        <td>${item.product_name}<br><small class="text-muted">SKU: ${item.product_sku || 'N/A'}</small></td>
                        <td class="text-center">${item.quantity}</td>
                        <td class="text-end">₱${parseFloat(item.unit_price).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                        <td class="text-end fw-bold">₱${(item.quantity * item.unit_price).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                    </tr>
                `).join('')
                : '<tr><td colspan="4" class="text-center text-muted">No items</td></tr>';

            content.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-accent">Order Information</h6>
                        <p class="mb-1"><strong>Order Number:</strong> ${order.order_number}</p>
                        <p class="mb-1"><strong>Order Date:</strong> ${new Date(order.created_at || order.order_date).toLocaleString('en-US')}</p>
                        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-warning text-dark">${order.status}</span></p>
                        <p class="mb-1"><strong>Payment Method:</strong> ${order.payment_method || 'N/A'}</p>
                        <p class="mb-1"><strong>Payment Status:</strong> ${order.payment_status || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-accent">Customer Information</h6>
                        <p class="mb-1"><strong>Name:</strong> ${order.customer_name || (order.first_name + ' ' + order.last_name) || 'N/A'}</p>
                        <p class="mb-1"><strong>Email:</strong> ${order.email || 'N/A'}</p>
                        <p class="mb-1"><strong>Phone:</strong> ${order.shipping_address?.phone || order.phone || 'N/A'}</p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-accent">Shipping Address</h6>
                        <address class="small">
                            ${order.shipping_address?.first_name || ''} ${order.shipping_address?.last_name || ''}<br>
                            ${order.shipping_address?.address_line_1 || 'N/A'}<br>
                            ${order.shipping_address?.address_line_2 ? order.shipping_address.address_line_2 + '<br>' : ''}
                            ${order.shipping_address?.city || ''}, ${order.shipping_address?.state || ''} ${order.shipping_address?.postal_code || ''}<br>
                            ${order.shipping_address?.country || ''}<br>
                            ${order.shipping_address?.phone ? 'Phone: ' + order.shipping_address.phone : ''}
                        </address>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-accent">Billing Address</h6>
                        <address class="small">
                            ${order.billing_address?.first_name || ''} ${order.billing_address?.last_name || ''}<br>
                            ${order.billing_address?.address_line_1 || 'N/A'}<br>
                            ${order.billing_address?.address_line_2 ? order.billing_address.address_line_2 + '<br>' : ''}
                            ${order.billing_address?.city || ''}, ${order.billing_address?.state || ''} ${order.billing_address?.postal_code || ''}<br>
                            ${order.billing_address?.country || ''}<br>
                            ${order.billing_address?.phone ? 'Phone: ' + order.billing_address.phone : ''}
                        </address>
                    </div>
                </div>

                <h6 class="text-accent mb-3">Order Items</h6>
                <div class="table-responsive">
                    <table class="table table-dark table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHTML}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                <td class="text-end fw-bold">₱${parseFloat(order.subtotal || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Tax:</strong></td>
                                <td class="text-end">₱${parseFloat(order.tax_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                <td class="text-end">₱${parseFloat(order.shipping_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            </tr>
                            <tr class="table-primary">
                                <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
                                <td class="text-end fw-bold fs-5">₱${parseFloat(order.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                ${order.notes ? `<div class="alert alert-info mt-3"><strong>Notes:</strong> ${order.notes}</div>` : ''}
            `;

        } else {
            content.innerHTML = '<div class="alert alert-danger">Failed to load order details</div>';
        }

    } catch (error) {
        console.error('Error loading order details:', error);
        content.innerHTML = '<div class="alert alert-danger">Error loading order details</div>';
    }
}

function showApproveModal() {
    if (!currentOrderId) {
        if (typeof showToast !== 'undefined') {
            showToast('No order selected', 'error');
        }
        return;
    }

    const modal = new bootstrap.Modal(document.getElementById('approveConfirmModal'));
    modal.show();
}

async function confirmApproveOrder() {
    // Close confirmation modal
    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('approveConfirmModal'));
    confirmModal.hide();

    const loading = document.getElementById('loading-overlay');
    loading.classList.remove('d-none');

    try {
        const response = await fetch('../backend/api/orders/approve.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_id: currentOrderId,
                action: 'approve'
            })
        });

        const data = await response.json();
        console.log('Approve response:', data);

        loading.classList.add('d-none');

        if (data.success) {
            if (typeof showToast !== 'undefined') {
                showToast('Order approved successfully!', 'success');
            }

            // Close order details modal
            bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal')).hide();

            // Reload pending orders
            loadPendingOrders();

            currentOrderId = null;
        } else {
            console.error('Approval failed:', data);
            if (typeof showToast !== 'undefined') {
                showToast(data.message || 'Failed to approve order', 'error');
            }
        }

    } catch (error) {
        loading.classList.add('d-none');
        console.error('Error approving order:', error);
        if (typeof showToast !== 'undefined') {
            showToast('Error approving order: ' + error.message, 'error');
        }
    }
}

function showRejectModal() {
    if (!currentOrderId) {
        if (typeof showToast !== 'undefined') {
            showToast('No order selected', 'error');
        }
        return;
    }

    // Clear previous reason
    document.getElementById('rejection-reason').value = '';
    document.getElementById('rejection-reason').classList.remove('is-invalid');

    const modal = new bootstrap.Modal(document.getElementById('rejectConfirmModal'));
    modal.show();
}

async function confirmRejectOrder() {
    const reasonInput = document.getElementById('rejection-reason');
    const reason = reasonInput.value.trim();

    if (!reason) {
        reasonInput.classList.add('is-invalid');
        return;
    }

    reasonInput.classList.remove('is-invalid');

    // Close confirmation modal
    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('rejectConfirmModal'));
    confirmModal.hide();

    const loading = document.getElementById('loading-overlay');
    loading.classList.remove('d-none');

    try {
        const response = await fetch('../backend/api/orders/approve.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_id: currentOrderId,
                action: 'reject',
                reason: reason
            })
        });

        const data = await response.json();

        loading.classList.add('d-none');

        if (data.success) {
            if (typeof showToast !== 'undefined') {
                showToast('Order rejected successfully', 'success');
            }

            // Close order details modal
            bootstrap.Modal.getInstance(document.getElementById('orderDetailsModal')).hide();

            // Reload pending orders
            loadPendingOrders();

            currentOrderId = null;
        } else {
            if (typeof showToast !== 'undefined') {
                showToast(data.message || 'Failed to reject order', 'error');
            }
        }

    } catch (error) {
        loading.classList.add('d-none');
        console.error('Error rejecting order:', error);
        if (typeof showToast !== 'undefined') {
            showToast('Error rejecting order', 'error');
        }
    }
}

// Load pending orders count on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initial load
    loadPendingOrders();
});
