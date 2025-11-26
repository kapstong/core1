// Audit Logs Page Loader
async function loadActivityLogsPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-history me-2"></i>Audit Logs</h1>
            <p class="page-subtitle">System-wide activity tracking and security audit trail</p>
        </div>

        <!-- Filters -->
        <div class="card mb-4" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Action</label>
                        <select id="filter-action" class="form-select">
                            <option value="">All Actions</option>
                            <option value="login">Login</option>
                            <option value="logout">Logout</option>
                            <option value="login_failed">Login Failed</option>
                            <option value="create">Create</option>
                            <option value="update">Update</option>
                            <option value="delete">Delete</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Entity Type</label>
                        <select id="filter-entity-type" class="form-select">
                            <option value="">All Types</option>
                            <option value="user">User</option>
                            <option value="product">Product</option>
                            <option value="purchase_order">Purchase Order</option>
                            <option value="stock_adjustment">Stock Adjustment</option>
                            <option value="grn">GRN</option>
                            <option value="category">Category</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" id="filter-start-date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" id="filter-end-date" class="form-control">
                    </div>
                </div>
                <div class="mt-3">
                    <button onclick="loadAuditLogs()" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                    <button onclick="filterInventoryActivities()" class="btn btn-info">
                        <i class="fas fa-warehouse me-2"></i>Show Inventory Only
                    </button>
                    <button onclick="clearAuditFilters()" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </button>
                    <button onclick="exportAuditLogs()" class="btn btn-outline-success">
                        <i class="fas fa-download me-2"></i>Export CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Entity</th>
                                <th>Description</th>
                                <th>IP Address</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="audit-logs-body">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="audit-pagination" class="mt-3"></div>
            </div>
        </div>
    `;

    // Load logs
    await loadAuditLogs();
}

let currentAuditPage = 1;

async function loadAuditLogs(page = 1) {
    try {
        currentAuditPage = page;

        // Build query string
        const params = new URLSearchParams({
            page: page,
            limit: 50
        });

        const action = document.getElementById('filter-action')?.value;
        const entityType = document.getElementById('filter-entity-type')?.value;
        const startDate = document.getElementById('filter-start-date')?.value;
        const endDate = document.getElementById('filter-end-date')?.value;

        if (action) params.append('action', action);
        if (entityType) params.append('entity_type', entityType);
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);

        const response = await fetch(`${API_BASE}/audit/logs.php?${params}`);
        const data = await response.json();

        if (data.success) {
            displayAuditLogs(data.data.logs);
            displayAuditPagination(data.data.pagination);
        } else {
            showError(data.message || 'Failed to load audit logs');
        }
    } catch (error) {
        devLog('Error loading audit logs:', error);
        showError('Failed to load audit logs');
    }
}

function displayAuditLogs(logs) {
    const tbody = document.getElementById('audit-logs-body');

    if (!logs || logs.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p>No audit logs found</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = logs.map(log => {
        const actionBadge = getActionBadge(log.action);
        const timestamp = new Date(log.created_at).toLocaleString();

        return `
            <tr>
                <td>
                    <small>${timestamp}</small>
                </td>
                <td>
                    ${log.username ? `<strong>${log.username}</strong><br><small class="text-muted">ID: ${log.user_id || 'N/A'}</small>` : '<span class="text-muted">System</span>'}
                </td>
                <td>${actionBadge}</td>
                <td>
                    <span class="badge bg-secondary">${log.entity_type}</span>
                    ${log.entity_id ? `<br><small class="text-muted">ID: ${log.entity_id}</small>` : ''}
                </td>
                <td>
                    <small>${log.description}</small>
                </td>
                <td>
                    <code style="font-size: 0.8rem;">${log.ip_address || 'N/A'}</code>
                </td>
                <td>
                    ${log.old_values || log.new_values ? `
                        <button onclick="viewAuditDetails(${log.id}, ${escapeHtml(JSON.stringify(log))})" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    ` : '<span class="text-muted">—</span>'}
                </td>
            </tr>
        `;
    }).join('');
}

function getActionBadge(action) {
    const badges = {
        'login': '<span class="badge bg-success"><i class="fas fa-sign-in-alt me-1"></i>Login</span>',
        'logout': '<span class="badge bg-info"><i class="fas fa-sign-out-alt me-1"></i>Logout</span>',
        'login_failed': '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Login Failed</span>',
        'create': '<span class="badge bg-primary"><i class="fas fa-plus me-1"></i>Create</span>',
        'update': '<span class="badge bg-warning"><i class="fas fa-edit me-1"></i>Update</span>',
        'delete': '<span class="badge bg-danger"><i class="fas fa-trash me-1"></i>Delete</span>'
    };
    return badges[action] || `<span class="badge bg-secondary">${action}</span>`;
}

function displayAuditPagination(pagination) {
    const container = document.getElementById('audit-pagination');

    if (pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = '<nav><ul class="pagination justify-content-center">';

    // Previous button
    html += `
        <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadAuditLogs(${pagination.current_page - 1}); return false;">
                Previous
            </a>
        </li>
    `;

    // Page numbers
    const startPage = Math.max(1, pagination.current_page - 2);
    const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

    if (startPage > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAuditLogs(1); return false;">1</a></li>`;
        if (startPage > 2) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `
            <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" onclick="loadAuditLogs(${i}); return false;">${i}</a>
            </li>
        `;
    }

    if (endPage < pagination.total_pages) {
        if (endPage < pagination.total_pages - 1) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadAuditLogs(${pagination.total_pages}); return false;">${pagination.total_pages}</a></li>`;
    }

    // Next button
    html += `
        <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadAuditLogs(${pagination.current_page + 1}); return false;">
                Next
            </a>
        </li>
    `;

    html += '</ul></nav>';

    html += `<p class="text-center text-muted mt-2">Showing page ${pagination.current_page} of ${pagination.total_pages} (${pagination.total} total records)</p>`;

    container.innerHTML = html;
}

function viewAuditDetails(id, log) {
    const oldValues = log.old_values ? JSON.parse(log.old_values) : null;
    const newValues = log.new_values ? JSON.parse(log.new_values) : null;

    let detailsHTML = '<h5>Audit Log Details</h5>';

    if (oldValues) {
        detailsHTML += '<h6 class="mt-3">Old Values:</h6>';
        detailsHTML += `<pre class="bg-dark p-3 rounded"><code>${JSON.stringify(oldValues, null, 2)}</code></pre>`;
    }

    if (newValues) {
        detailsHTML += '<h6 class="mt-3">New Values:</h6>';
        detailsHTML += `<pre class="bg-dark p-3 rounded"><code>${JSON.stringify(newValues, null, 2)}</code></pre>`;
    }

    if (log.user_agent) {
        detailsHTML += '<h6 class="mt-3">User Agent:</h6>';
        detailsHTML += `<p><code>${log.user_agent}</code></p>`;
    }

    // Show in a modal or alert (simplified version)
    const modal = document.createElement('div');
    modal.className = 'modal fade show';
    modal.style.display = 'block';
    modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
    modal.innerHTML = `
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                <div class="modal-header" style="border-color: var(--border-color);">
                    <h5 class="modal-title">Audit Log #${id}</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="this.closest('.modal').remove()"></button>
                </div>
                <div class="modal-body">
                    ${detailsHTML}
                </div>
                <div class="modal-footer" style="border-color: var(--border-color);">
                    <button type="button" class="btn btn-secondary" onclick="this.closest('.modal').remove()">Close</button>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function filterInventoryActivities() {
    // Reset all filters first
    clearAuditFilters();

    // Build query string for inventory activities
    const params = new URLSearchParams({
        page: 1,
        limit: 50,
        inventory_entities: 'true'
    });

    // Make API call to get inventory-focused logs
    fetch(`${API_BASE}/audit/logs.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAuditLogs(data.data.logs);
                displayAuditPagination(data.data.pagination);
                showToast('Showing inventory activities only', 'info');
            } else {
                showError(data.message || 'Failed to load inventory logs');
            }
        })
        .catch(error => {
            devLog('Error loading inventory logs:', error);
            showError('Failed to load inventory logs');
        });
}

function clearAuditFilters() {
    document.getElementById('filter-action').value = '';
    document.getElementById('filter-entity-type').value = '';
    document.getElementById('filter-start-date').value = '';
    document.getElementById('filter-end-date').value = '';
    loadAuditLogs(1);
}

function exportAuditLogs() {
    try {
        // Build query string with current filters
        const params = new URLSearchParams();

        const action = document.getElementById('filter-action')?.value;
        const entityType = document.getElementById('filter-entity-type')?.value;
        const startDate = document.getElementById('filter-start-date')?.value;
        const endDate = document.getElementById('filter-end-date')?.value;

        if (action) params.append('action', action);
        if (entityType) params.append('entity_type', entityType);
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);

        // Create download URL
        const exportUrl = `${API_BASE}/audit/export.php?${params}`;

        // Trigger download
        window.location.href = exportUrl;

        showToast('Exporting audit logs...', 'success');
    } catch (error) {
        devLog('Error exporting audit logs:', error);
        showError('Failed to export audit logs');
    }
}

function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
