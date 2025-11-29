/**
 * COMPLETE GRN (Goods Received Notes) MANAGEMENT
 * Rebuild from scratch - Clean implementation
 */

// Wrap everything in an IIFE to avoid global conflicts
(function() {
    'use strict';

    // Local state for approved POs (scoped to avoid conflicts)
    let grnApprovedPOs = [];
    let currentGRN = null;

/**
 * Main GRN Page Loader
 */
window.loadGRNPage = async function() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-clipboard-check me-2"></i>Goods Received Notes</h1>
            <p class="page-subtitle">Record and track goods received from suppliers</p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex gap-3">
                <select id="grn-status-filter" class="form-select" style="width: 200px;">
                    <option value="all">All Statuses</option>
                    <option value="pending">Pending Inspection</option>
                    <option value="passed">Passed</option>
                    <option value="partial">Partial</option>
                    <option value="failed">Failed</option>
                </select>
                <input type="text" id="grn-search" class="form-control" placeholder="Search GRN..." style="width: 250px;">
            </div>
            <button class="btn btn-primary" onclick="openGRNModal()">
                <i class="fas fa-plus me-2"></i>New Goods Received Note
            </button>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4" id="grn-stats">
            <div class="col-md-3">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Total GRNs</p>
                                <h4 class="mb-0" id="stat-total">0</h4>
                            </div>
                            <i class="fas fa-clipboard-check fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Pending</p>
                                <h4 class="mb-0" id="stat-pending">0</h4>
                            </div>
                            <i class="fas fa-clock fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">Completed</p>
                                <h4 class="mb-0" id="stat-passed">0</h4>
                            </div>
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-1">This Month</p>
                                <h4 class="mb-0" id="stat-month">0</h4>
                            </div>
                            <i class="fas fa-calendar fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRN List Card -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Goods Received Notes</h5>
            </div>
            <div class="card-body">
                <!-- Loading State -->
                <div id="grn-loading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading GRNs...</p>
                </div>

                <!-- GRN Table -->
                <div id="grn-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>GRN #</th>
                                    <th>PO Number</th>
                                    <th>Supplier</th>
                                    <th>Received Date</th>
                                    <th>Total Items</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="grn-table-body">
                                <!-- Populated by loadGRNs() -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <div id="no-grn-message" class="text-center py-5 d-none">
                    <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No Goods Received Notes Found</h5>
                    <p class="text-muted">Start by creating your first GRN for an approved purchase order.</p>
                    <button class="btn btn-primary mt-2" onclick="openGRNModal()">
                        <i class="fas fa-plus me-2"></i>Create First GRN
                    </button>
                </div>
            </div>
        </div>
    `;

    // Load GRNs
    await window.loadGRNs();

    // Add event listeners
    document.getElementById('grn-status-filter')?.addEventListener('change', () => window.loadGRNs());
    document.getElementById('grn-search')?.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        filterGRNsInTable(searchTerm);
    });
}

/**
 * Load all GRNs from API
 */
window.loadGRNs = async function() {
    const loadingIndicator = document.getElementById('grn-loading');
    const content = document.getElementById('grn-content');
    const noGRNMessage = document.getElementById('no-grn-message');
    const tbody = document.getElementById('grn-table-body');
    const statusFilter = document.getElementById('grn-status-filter')?.value || 'all';

    try {
        loadingIndicator?.classList.remove('d-none');
        content?.classList.add('d-none');
        noGRNMessage?.classList.add('d-none');

        const url = statusFilter === 'all'
            ? `${API_BASE}/grn/index.php`
            : `${API_BASE}/grn/index.php?inspection_status=${statusFilter}`;

        const response = await fetch(url);
        const data = await response.json();

        loadingIndicator?.classList.add('d-none');

        // API wraps grns in 'data' object (same pattern as purchase_orders)
        const grns = (data.data && data.data.grns) || data.grns || [];

        if (grns.length > 0) {
            content?.classList.remove('d-none');
            tbody.innerHTML = '';

            // Calculate stats
            updateGRNStats(grns);

            // Populate table
            grns.forEach(grn => {
                const row = createGRNRow(grn);
                tbody.appendChild(row);
            });
        } else {
            noGRNMessage?.classList.remove('d-none');
            updateGRNStats([]);
        }
    } catch (error) {
        loadingIndicator?.classList.add('d-none');
        showError('Failed to load Goods Received Notes');
        noGRNMessage?.classList.remove('d-none');
    }
}

/**
 * Create a table row for a GRN
 */
function createGRNRow(grn) {
    const tr = document.createElement('tr');
    tr.setAttribute('data-grn-id', grn.id);

    const statusBadge = getStatusBadge(grn.inspection_status || 'pending');

    tr.innerHTML = `
        <td><strong>${grn.grn_number || `GRN-${grn.id}`}</strong></td>
        <td>${grn.po?.number || grn.po_number || `PO-${grn.po_id || grn.po?.id}`}</td>
        <td>${grn.supplier_name || 'N/A'}</td>
        <td>${formatDate(grn.received_date)}</td>
        <td>${grn.item_count || grn.total_items || 0} items</td>
        <td>${statusBadge}</td>
        <td>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="viewGRN(${grn.id})" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="deleteGRN(${grn.id})" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </td>
    `;

    return tr;
}

/**
 * Get status badge HTML
 */
function getStatusBadge(status) {
    const statusMap = {
        'pending': { class: 'warning', text: 'Pending Inspection', icon: 'clock' },
        'passed': { class: 'success', text: 'Passed', icon: 'check-circle' },
        'partial': { class: 'info', text: 'Partial', icon: 'adjust' },
        'failed': { class: 'danger', text: 'Failed', icon: 'times-circle' }
    };

    const config = statusMap[status] || statusMap['pending'];
    return `<span class="badge bg-${config.class}">
        <i class="fas fa-${config.icon} me-1"></i>${config.text}
    </span>`;
}

/**
 * Update GRN statistics
 */
function updateGRNStats(grns) {
    const total = grns.length;
    const pending = grns.filter(g => g.inspection_status === 'pending').length;
    const passed = grns.filter(g => g.inspection_status === 'passed').length;

    // Count GRNs from this month
    const now = new Date();
    const thisMonth = grns.filter(g => {
        const grnDate = new Date(g.received_date);
        return grnDate.getMonth() === now.getMonth() && grnDate.getFullYear() === now.getFullYear();
    }).length;

    document.getElementById('stat-total').textContent = total;
    document.getElementById('stat-pending').textContent = pending;
    document.getElementById('stat-passed').textContent = passed;
    document.getElementById('stat-month').textContent = thisMonth;
}

/**
 * Filter GRNs in table by search term
 */
function filterGRNsInTable(searchTerm) {
    const rows = document.querySelectorAll('#grn-table-body tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

/**
 * Open GRN Modal for creating/editing
 */
window.openGRNModal = async function(grnId = null) {
    // Load approved POs first
    await loadApprovedPOs();

    // Check if there are approved POs
    if (grnApprovedPOs.length === 0) {
        showError('No approved purchase orders available. Please approve a PO first before creating a GRN.');
        return;
    }

    const isEdit = grnId !== null;
    const modalContent = `
        <div class="modal fade" id="grnModal" tabindex="-1">
            <div class="modal-dialog modal-xl" style="max-width: 1400px;">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color); background: var(--bg-secondary);">
                        <h4 class="modal-title">
                            <i class="fas fa-clipboard-check me-2"></i>
                            ${isEdit ? 'Edit' : 'Create'} Goods Received Note
                        </h4>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
                        <form id="grnForm">
                            <input type="hidden" id="grn-id" value="${grnId || ''}">

                            <!-- PO Selection -->
                            <div class="row mb-4">
                                <div class="col-md-5">
                                    <label class="form-label fw-bold" style="font-size: 1.1rem;">
                                        <i class="fas fa-file-alt me-2 text-primary"></i>Purchase Order *
                                    </label>
                                    <select class="form-select" id="grn-po" required style="font-size: 1.15rem; padding: 14px; height: auto;">
                                        <option value="">-- Select Purchase Order --</option>
                                        ${grnApprovedPOs.map(po => `
                                            <option value="${po.id}">
                                                ${po.po_number} - ${po.supplier_name || po.supplier?.name || 'N/A'} - ${formatCurrency(po.total_amount)}
                                            </option>
                                        `).join('')}
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-bold" style="font-size: 1.1rem;">
                                        <i class="fas fa-calendar-days me-2 text-success"></i>Received Date *
                                    </label>
                                    <input type="date" class="form-control" id="grn-received-date"
                                           value="${new Date().toISOString().split('T')[0]}" required
                                           style="font-size: 1.15rem; padding: 14px; height: auto;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold" style="font-size: 1.1rem;">
                                        <i class="fas fa-clipboard-check me-2 text-info"></i>Inspection Status
                                    </label>
                                    <select class="form-select" id="grn-inspection-status" required style="font-size: 1.15rem; padding: 14px; height: auto;">
                                        <option value="pending">Pending Inspection</option>
                                        <option value="passed">Passed</option>
                                        <option value="partial">Partial</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold" style="font-size: 1.1rem;">
                                        <i class="fas fa-sticky-note me-2 text-warning"></i>Notes
                                    </label>
                                    <textarea class="form-control" id="grn-notes" rows="2"
                                              placeholder="Quality inspection notes, delivery condition, etc."
                                              style="font-size: 1.05rem;"></textarea>
                                </div>
                            </div>

                            <hr style="border-color: var(--border-color); margin: 2rem 0;">

                            <!-- Items Section -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">
                                        <i class="fas fa-boxes me-2"></i>Received Items
                                        <span class="badge bg-info ms-2" id="po-items-hint">Select PO</span>
                                    </h5>
                                    <button type="button" class="btn btn-success" id="fill-all-items-btn" style="display: none;">
                                        <i class="fas fa-check-double me-2"></i>Accept All Items
                                    </button>
                                </div>
                                <div id="grn-items-container">
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-box-open fa-3x mb-2" style="opacity: 0.3;"></i>
                                        <p>Select a purchase order to load items</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary -->
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="summary-card p-3 text-center" style="background: rgba(0, 245, 255, 0.1); border: 2px solid var(--accent); border-radius: 8px;">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="fas fa-box fa-2x me-3" style="color: var(--accent);"></i>
                                            <div>
                                                <h3 class="mb-0" id="total-items-received" style="color: var(--accent);">0</h3>
                                                <small class="text-muted">Received</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="summary-card p-3 text-center" style="background: rgba(34, 197, 94, 0.1); border: 2px solid var(--success); border-radius: 8px;">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="fas fa-check-circle fa-2x me-3" style="color: var(--success);"></i>
                                            <div>
                                                <h3 class="mb-0" id="total-items-accepted" style="color: var(--success);">0</h3>
                                                <small class="text-muted">Accepted</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="summary-card p-3 text-center" style="background: rgba(239, 68, 68, 0.1); border: 2px solid var(--danger); border-radius: 8px;">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <i class="fas fa-times-circle fa-2x me-3" style="color: var(--danger);"></i>
                                            <div>
                                                <h3 class="mb-0" id="total-items-rejected" style="color: var(--danger);">0</h3>
                                                <small class="text-muted">Rejected</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="grn-form-message" class="mt-3"></div>
                        </form>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color); background: var(--bg-secondary);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-primary btn-lg" onclick="saveGRN()" style="font-size: 1.1rem; padding: 12px 24px;">
                            <i class="fas fa-save me-2"></i>${isEdit ? 'Update' : 'Create'} GRN
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <style>
            /* GRN Modal Enhancements */
            .grn-item-card:hover {
                border-color: var(--accent) !important;
                box-shadow: 0 2px 8px rgba(0, 245, 255, 0.2);
            }

            .summary-card {
                transition: all 0.2s ease;
            }

            .summary-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }
        </style>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('grnModal');
    if (existingModal) existingModal.remove();

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalContent);

    // Set up event listener for PO selection
    document.getElementById('grn-po').addEventListener('change', function() {
        loadPOItemsForGRN();
        if (this.value) {
            // Update hint badge
            const hint = document.getElementById('po-items-hint');
            if (hint) {
                hint.textContent = 'Loading...';
                hint.className = 'badge bg-warning';
            }
        }
    });

    // Add event listener for received date to ensure it's not in the future
    document.getElementById('grn-received-date').addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (selectedDate > today) {
            showError('Received date cannot be in the future');
            this.value = new Date().toISOString().split('T')[0];
        }
    });

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('grnModal'));
    modal.show();

    // Clean up on hide
    document.getElementById('grnModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

/**
 * Load approved POs from API
 */
async function loadApprovedPOs() {
    try {
        const url = `${API_BASE}/purchase_orders/index.php?status=approved`;
        const response = await fetch(url);
        const data = await response.json();

        console.log('Approved POs API Response:', data);

        if (data.success) {
            // API wraps purchase_orders in a 'data' object
            grnApprovedPOs = (data.data && data.data.purchase_orders) || data.purchase_orders || [];
            console.log('Loaded Approved POs:', grnApprovedPOs);
        } else {
            grnApprovedPOs = [];
            console.warn('API returned error:', data);
        }
    } catch (error) {
        console.error('Error loading approved POs:', error);
        grnApprovedPOs = [];
    }
}

/**
 * Load PO items when PO is selected
 */
window.loadPOItemsForGRN = async function() {
    const poId = document.getElementById('grn-po').value;
    const container = document.getElementById('grn-items-container');
    const hint = document.getElementById('po-items-hint');

    if (!poId) {
        container.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-info-circle me-2"></i>Select a purchase order to load items</div>';
        hint.textContent = 'Select a PO to load items';
        return;
    }

    try {
        hint.textContent = 'Loading items...';
        const response = await fetch(`${API_BASE}/purchase_orders/show.php?id=${poId}`);
        const data = await response.json();

        // API wraps response in 'data' object
        const purchaseOrder = data.data?.purchase_order || data.purchase_order;

        if (data.success && purchaseOrder && purchaseOrder.items) {
            const items = purchaseOrder.items;
            hint.textContent = `${items.length} items loaded`;
            hint.className = 'badge bg-success';
            container.innerHTML = '';

            // Show the "Accept All Items" button
            const fillAllBtn = document.getElementById('fill-all-items-btn');
            if (fillAllBtn) {
                fillAllBtn.style.display = 'block';
                fillAllBtn.onclick = () => {
                    const allQuickFillButtons = container.querySelectorAll('.quick-fill-all');
                    let count = 0;
                    allQuickFillButtons.forEach(btn => {
                        btn.click();
                        count++;
                    });
                    showSuccess(`All ${count} items filled with ordered quantities!`);
                };
            }

            items.forEach((item, index) => {
                const itemCard = document.createElement('div');
                itemCard.className = 'grn-item-card';
                itemCard.style = 'background: var(--bg-tertiary); border: 2px solid var(--border-color); border-radius: 8px; padding: 1.25rem; margin-bottom: 1rem;';
                itemCard.innerHTML = `
                    <div class="row align-items-center">
                        <!-- Product Name -->
                        <div class="col-md-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-box fa-lg me-2" style="color: var(--accent);"></i>
                                <div>
                                    <strong style="font-size: 1.05rem;">${item.product.name}</strong>
                                    <br><small class="text-muted">SKU: ${item.product.sku || 'N/A'}</small>
                                </div>
                            </div>
                            <input type="hidden" class="item-product-id" value="${item.product.id}">
                            <input type="hidden" class="item-po-item-id" value="${item.id || ''}">
                        </div>

                        <!-- Ordered -->
                        <div class="col-md-2">
                            <label class="form-label mb-1" style="font-size: 0.9rem; font-weight: 600;">
                                <i class="fas fa-shopping-cart text-primary me-1"></i>Ordered
                            </label>
                            <input type="number" class="form-control item-ordered-qty"
                                   value="${item.quantity_ordered}" readonly
                                   style="background: var(--bg-card); font-size: 1.2rem; padding: 12px; font-weight: 600; height: auto;">
                        </div>

                        <!-- Received -->
                        <div class="col-md-2">
                            <label class="form-label mb-1" style="font-size: 0.9rem; font-weight: 600;">
                                <i class="fas fa-truck-loading me-1" style="color: var(--accent);"></i>Received *
                            </label>
                            <input type="number" class="form-control item-received-qty"
                                   min="0" max="${item.quantity_ordered || item.quantity}"
                                   value="0" required placeholder="0"
                                   style="font-size: 1.3rem; padding: 14px; font-weight: 600; height: auto; border: 2px solid var(--accent);">
                        </div>

                        <!-- Accepted -->
                        <div class="col-md-2">
                            <label class="form-label mb-1" style="font-size: 0.9rem; font-weight: 600;">
                                <i class="fas fa-check-circle text-success me-1"></i>Accepted *
                            </label>
                            <input type="number" class="form-control item-accepted-qty"
                                   min="0" value="0" required placeholder="0"
                                   style="font-size: 1.3rem; padding: 14px; font-weight: 600; height: auto; border: 2px solid var(--success);">
                        </div>

                        <!-- Rejected -->
                        <div class="col-md-2">
                            <label class="form-label mb-1" style="font-size: 0.9rem; font-weight: 600;">
                                <i class="fas fa-times-circle text-danger me-1"></i>Rejected
                            </label>
                            <input type="number" class="form-control item-rejected-qty"
                                   min="0" value="0" readonly
                                   style="background: var(--bg-card); font-size: 1.2rem; padding: 12px; color: var(--danger); font-weight: 600; height: auto;">
                        </div>

                        <!-- Quick Fill Button -->
                        <div class="col-md-1">
                            <label class="form-label mb-1" style="font-size: 0.9rem; font-weight: 600;">&nbsp;</label>
                            <button type="button" class="btn btn-success w-100 quick-fill-all"
                                    title="Accept All" style="padding: 14px; font-size: 1rem; height: auto;">
                                <i class="fas fa-check-double"></i>
                            </button>
                        </div>
                    </div>
                `;
                container.appendChild(itemCard);

                // Add quick action event listeners
                const quickFillBtn = itemCard.querySelector('.quick-fill-all');
                const receivedInput = itemCard.querySelector('.item-received-qty');
                const acceptedInput = itemCard.querySelector('.item-accepted-qty');
                const orderedQty = item.quantity_ordered || item.quantity;

                quickFillBtn.addEventListener('click', () => {
                    receivedInput.value = orderedQty;
                    acceptedInput.value = orderedQty;
                    receivedInput.dispatchEvent(new Event('input'));
                    acceptedInput.dispatchEvent(new Event('input'));
                    showSuccess(`${orderedQty} units marked as received and accepted`);
                });
            });

            // Add event listeners for quantity calculations
            container.querySelectorAll('.item-received-qty, .item-accepted-qty').forEach(input => {
                input.addEventListener('input', calculateItemRejected);
                input.addEventListener('input', calculateGRNTotals);
            });

            calculateGRNTotals();
        } else {
            container.innerHTML = '<div class="alert alert-warning">No items found for this purchase order</div>';
            hint.textContent = 'No items found';
        }
    } catch (error) {
        container.innerHTML = '<div class="alert alert-danger">Failed to load purchase order items</div>';
        hint.textContent = 'Error loading items';
    }
}

/**
 * Calculate rejected quantity (received - accepted)
 */
function calculateItemRejected(event) {
    const card = event.target.closest('.grn-item-card');
    if (!card) return;

    const received = parseFloat(card.querySelector('.item-received-qty').value) || 0;
    const accepted = parseFloat(card.querySelector('.item-accepted-qty').value) || 0;
    const rejected = Math.max(0, received - accepted);

    card.querySelector('.item-rejected-qty').value = rejected;

    // Visual feedback for rejected items
    const rejectedInput = card.querySelector('.item-rejected-qty');
    if (rejected > 0) {
        rejectedInput.style.borderColor = 'var(--danger)';
        rejectedInput.style.fontWeight = 'bold';
    } else {
        rejectedInput.style.borderColor = 'var(--border-color)';
        rejectedInput.style.fontWeight = '600';
    }
}

/**
 * Update item progress bar
 */
function updateItemProgress(card, orderedQty, receivedQty) {
    const progressBar = card.querySelector('.item-progress-bar');
    const progressText = card.querySelector('.item-progress-text');

    if (!progressBar || !progressText) return;

    const percentage = Math.min(100, (receivedQty / orderedQty) * 100);

    progressBar.style.width = percentage + '%';
    progressBar.setAttribute('aria-valuenow', percentage);
    progressText.textContent = Math.round(percentage) + '%';

    // Change color based on completion
    if (percentage === 100) {
        progressBar.style.background = 'var(--success)';
        progressText.classList.add('text-success');
        progressText.classList.remove('text-muted');
    } else if (percentage > 0) {
        progressBar.style.background = 'var(--accent)';
        progressText.classList.remove('text-success', 'text-muted');
    } else {
        progressBar.style.background = 'var(--accent)';
        progressText.classList.add('text-muted');
        progressText.classList.remove('text-success');
    }
}

/**
 * Update step indicators
 */
function updateStepIndicators(currentStep) {
    for (let i = 1; i <= 3; i++) {
        const stepNumber = document.querySelector(`#step-${i} .step-number`);
        const stepLabel = document.querySelector(`#step-${i} .step-label`);

        if (!stepNumber) continue;

        stepNumber.classList.remove('active', 'completed');

        if (i < currentStep) {
            stepNumber.classList.add('completed');
            stepNumber.innerHTML = '<i class="fas fa-check"></i>';
        } else if (i === currentStep) {
            stepNumber.classList.add('active');
            stepNumber.textContent = i;
        } else {
            stepNumber.textContent = i;
        }
    }
}

/**
 * Calculate GRN totals
 */
function calculateGRNTotals() {
    const itemCards = document.querySelectorAll('.grn-item-card');
    let totalReceived = 0;
    let totalAccepted = 0;
    let totalRejected = 0;

    itemCards.forEach(card => {
        const received = parseFloat(card.querySelector('.item-received-qty').value) || 0;
        const accepted = parseFloat(card.querySelector('.item-accepted-qty').value) || 0;
        const rejected = parseFloat(card.querySelector('.item-rejected-qty').value) || 0;

        totalReceived += received;
        totalAccepted += accepted;
        totalRejected += rejected;
    });

    document.getElementById('total-items-received').textContent = totalReceived;
    document.getElementById('total-items-accepted').textContent = totalAccepted;
    document.getElementById('total-items-rejected').textContent = totalRejected;
}

/**
 * Save GRN (Create or Update)
 */
window.saveGRN = async function() {
    const grnId = document.getElementById('grn-id').value;
    const poId = document.getElementById('grn-po').value;
    const receivedDate = document.getElementById('grn-received-date').value;
    const inspectionStatus = document.getElementById('grn-inspection-status').value;
    const notes = document.getElementById('grn-notes').value;

    if (!poId) {
        showError('Please select a purchase order');
        return;
    }

    // Collect items
    const items = [];
    const itemCards = document.querySelectorAll('.grn-item-card');

    itemCards.forEach(card => {
        const poItemId = card.querySelector('.item-po-item-id').value;
        const productId = card.querySelector('.item-product-id').value;
        const receivedQty = parseFloat(card.querySelector('.item-received-qty').value) || 0;
        const acceptedQty = parseFloat(card.querySelector('.item-accepted-qty').value) || 0;

        if (receivedQty > 0) {
            items.push({
                po_item_id: poItemId,
                product_id: productId,
                quantity_received: receivedQty,
                quantity_accepted: acceptedQty
                // quantity_rejected is auto-calculated by database (GENERATED column)
            });
        }
    });

    if (items.length === 0) {
        showError('Please enter received quantities for at least one item');
        return;
    }

    const payload = {
        po_id: poId,
        received_date: receivedDate,
        inspection_status: inspectionStatus,
        notes: notes,
        items: items
    };

    try {
        const url = `${API_BASE}/grn/create.php`;
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            showSuccess('Goods Received Note created successfully!');
            bootstrap.Modal.getInstance(document.getElementById('grnModal')).hide();
            await window.loadGRNs();
        } else {
            showError(result.message || 'Failed to create GRN');
        }
    } catch (error) {
        showError('Failed to save GRN. Please try again.');
    }
}

/**
 * View GRN Details
 */
window.viewGRN = async function(id) {
    try {
        const response = await fetch(`${API_BASE}/grn/show.php?id=${id}`);
        const data = await response.json();

        // API wraps grn in 'data' object (same pattern)
        const grn = (data.data && data.data.grn) || data.grn;

        if (data.success && grn) {
            const modalContent = `
                <div class="modal fade" id="viewGRNModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                            <div class="modal-header" style="border-color: var(--border-color);">
                                <h5 class="modal-title">
                                    <i class="fas fa-clipboard-check me-2"></i>
                                    GRN Details - ${grn.grn_number || `GRN-${grn.id}`}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">GRN Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td width="40%"><strong>GRN Number:</strong></td>
                                                <td>${grn.grn_number || `GRN-${grn.id}`}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Received Date:</strong></td>
                                                <td>${formatDate(grn.received_date)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Inspection Status:</strong></td>
                                                <td>${getStatusBadge(grn.inspection_status)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Notes:</strong></td>
                                                <td>${grn.notes || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Purchase Order Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td width="40%"><strong>PO Number:</strong></td>
                                                <td>${grn.po?.number || grn.po_number || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Supplier:</strong></td>
                                                <td>${grn.supplier?.name || grn.supplier_name || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <h6 class="text-primary mb-3">Received Items</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Received</th>
                                                <th>Accepted</th>
                                                <th>Rejected</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${(grn.items || []).map(item => `
                                                <tr>
                                                    <td>${item.product?.name || item.product_name || 'N/A'}</td>
                                                    <td>${item.quantity_received || 0}</td>
                                                    <td class="text-success"><strong>${item.quantity_accepted || 0}</strong></td>
                                                    <td class="text-danger"><strong>${item.quantity_rejected || 0}</strong></td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer" style="border-color: var(--border-color);">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal
            const existing = document.getElementById('viewGRNModal');
            if (existing) existing.remove();

            document.body.insertAdjacentHTML('beforeend', modalContent);
            const modal = new bootstrap.Modal(document.getElementById('viewGRNModal'));
            modal.show();

            document.getElementById('viewGRNModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        } else {
            showError('Failed to load GRN details');
        }
    } catch (error) {
        showError('Failed to load GRN details');
    }
}

/**
 * Delete GRN
 */
window.deleteGRN = async function(id) {
    const confirmed = await showConfirm(
        'Are you sure you want to delete this GRN? This will reverse all inventory changes and cannot be undone.',
        {
            title: 'Delete GRN',
            confirmText: 'Delete',
            cancelText: 'Cancel',
            type: 'danger',
            icon: 'fas fa-trash-alt'
        }
    );

    if (!confirmed) {
        return;
    }

    try {
        // Use DELETE method with ID in query string
        // No custom headers to avoid CORS preflight issues
        const response = await fetch(`${API_BASE}/grn/delete.php?id=${id}`, {
            method: 'DELETE',
            credentials: 'same-origin'
        });
        const result = await response.json();

        if (result.success) {
            // Show warnings if inventory went negative
            if (result.data && result.data.warnings && result.data.warnings.length > 0) {
                const warningMsg = '<strong>GRN deleted successfully.</strong><br><br><strong>Warnings:</strong><ul class="mb-0 mt-2">' +
                    result.data.warnings.map(w => `<li>${w}</li>`).join('') + '</ul>';

                await showConfirm(warningMsg, {
                    title: 'GRN Deleted with Warnings',
                    confirmText: 'OK',
                    cancelText: '',
                    type: 'warning',
                    icon: 'fas fa-exclamation-triangle'
                });
            } else {
                showSuccess('GRN deleted successfully');
            }
            await window.loadGRNs();
        } else {
            showError(result.message || 'Failed to delete GRN');
        }
    } catch (error) {
        showError('Failed to delete GRN');
    }
};

})(); // End of IIFE - Closes the scope to avoid global conflicts
