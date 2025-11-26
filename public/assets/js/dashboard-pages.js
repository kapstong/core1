// Dashboard page loading functions

async function loadSupplierHomePage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-tachometer-alt me-2"></i>Supplier Dashboard</h1>
            <p class="page-subtitle">Welcome back, ${currentUser.full_name || currentUser.username}!</p>
        </div>

        <!-- Quick Stats - Hidden as requested -->
        <!--<div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value" id="supplier-total-pos">0</div>
                        <div class="stat-label">Total POs</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--accent);">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value" id="supplier-approved-pos">0</div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value" id="supplier-pending-pos">0</div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value" id="supplier-completed-pos">0</div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-icon" style="background: rgba(0, 245, 255, 0.1); color: var(--accent);">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>
            </div>
        </div>-->

        <!-- Recent Activity -->
        <div class="row mt-4">
            <div class="col-lg-8">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Purchase Orders</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Total Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="supplier-recent-pos">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button onclick="showPage('purchase-orders')" class="btn btn-primary">
                                <i class="fas fa-list me-2"></i>View All Orders
                            </button>
                            <button onclick="showPage('po-history')" class="btn btn-outline-primary">
                                <i class="fas fa-history me-2"></i>Order History
                            </button>
                            <button onclick="showPage('profile')" class="btn btn-outline-secondary">
                                <i class="fas fa-user me-2"></i>Update Profile
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Load supplier stats
    try {
        const response = await fetch(`${API_BASE}/purchase_orders/dashboard.php`);
        const data = await response.json();

        if (data.success) {
            // Update stats cards
            document.getElementById('supplier-total-pos').textContent = data.data.summary.total_pos || 0;
            document.getElementById('supplier-approved-pos').textContent = data.data.summary.approved_orders || 0;
            document.getElementById('supplier-pending-pos').textContent = data.data.summary.pending_orders || 0;
            document.getElementById('supplier-completed-pos').textContent = data.data.summary.completed_orders || 0;
            document.getElementById('supplier-rating').textContent = 'N/A'; // Rating not implemented yet

            // Load recent POs
            const tbody = document.getElementById('supplier-recent-pos');
            if (data.data.recent_orders && data.data.recent_orders.length > 0) {
                tbody.innerHTML = data.data.recent_orders.map(order => `
                    <tr>
                        <td>${order.po_number || order.id}</td>
                        <td>${formatDate(order.created_at)}</td>
                        <td><span class="badge bg-${getStatusColor(order.status)}">${order.status}</span></td>
                        <td>₱${parseFloat(order.total_amount).toLocaleString()}</td>
                        <td>
                            <button onclick="viewPODetails('${order.id}')" class="btn btn-sm btn-primary">View</button>
                        </td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No recent orders</td></tr>';
            }
        } else {
            showError(data.message || 'Failed to load dashboard data');
        }
    } catch (error) {
        devLog('Error loading supplier dashboard:', error);
        showError('Failed to load dashboard data');
    }
}

async function loadProfilePage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-user me-2"></i>My Profile</h1>
            <p class="page-subtitle">Manage your account information</p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form id="profileForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="profile-full-name" value="${currentUser.full_name || ''}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" id="profile-username" value="${currentUser.username}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" id="profile-email" value="${currentUser.email || ''}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="profile-phone" value="${currentUser.phone || ''}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="${formatRole(currentUser.role)}" readonly>
                                <div class="form-text">Contact administrator to change your role</div>
                            </div>
                            <div id="profile-message"></div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form id="passwordForm">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current-password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new-password" required minlength="6">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm-password" required minlength="6">
                            </div>
                            <div id="password-message"></div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Profile form handler
    document.getElementById('profileForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = {
            full_name: document.getElementById('profile-full-name').value,
            username: document.getElementById('profile-username').value,
            email: document.getElementById('profile-email').value,
            phone: document.getElementById('profile-phone').value
        };

        try {
            const response = await fetch(`${API_BASE}/users/update.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const result = await response.json();
            if (result.success) {
                showSuccess('Profile updated successfully');
                // Update current user data
                currentUser = { ...currentUser, ...formData };
                updateUserUI();
            } else {
                showError(result.message || 'Failed to update profile');
            }
        } catch (error) {
            showError('Failed to update profile');
        }
    });

    // Password form handler
    document.getElementById('passwordForm').addEventListener('submit', async (e) => {
        e.preventDefault();

        const currentPassword = document.getElementById('current-password').value;
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;

        if (newPassword !== confirmPassword) {
            showError('New passwords do not match');
            return;
        }

        try {
            const response = await fetch(`${API_BASE}/users/change-password.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ current_password: currentPassword, new_password: newPassword })
            });

            const result = await response.json();
            if (result.success) {
                showSuccess('Password changed successfully');
                document.getElementById('passwordForm').reset();
            } else {
                showError(result.message || 'Failed to change password');
            }
        } catch (error) {
            showError('Failed to change password');
        }
    });
}

async function loadSettingsPage() {
    const content = document.getElementById('page-content');

    // Show different settings based on user role
    if (currentUser.role === 'supplier') {
        await loadSupplierSettingsPage();
        return;
    }

    // Check if user has permission to access admin settings
    if (currentUser.role !== 'admin' && currentUser.role !== 'inventory_manager') {
        content.innerHTML = `
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-cog me-2"></i>System Settings</h1>
                <p class="page-subtitle">Configure system preferences and options</p>
            </div>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Access Denied:</strong> You need administrator or inventory manager privileges to access system settings.
            </div>
        `;
        return;
    }

    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-cog me-2"></i>System Settings</h1>
            <p class="page-subtitle">Configure system preferences and options</p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Business Information</h5>
                    </div>
                    <div class="card-body">
                        <form id="businessForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Business Name</label>
                                    <input type="text" class="form-control" id="business-name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" id="business-email" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="business-phone">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" id="tax-rate" step="0.01" min="0" max="100">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Business Address</label>
                                <textarea class="form-control" id="business-address" rows="3"></textarea>
                            </div>
                            <div id="business-message"></div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Business Settings
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Email Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="emailForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" id="smtp-host">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" id="smtp-port">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" id="smtp-username">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" id="smtp-password">
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="smtp-ssl">
                                <label class="form-check-label" for="smtp-ssl">
                                    Use SSL/TLS
                                </label>
                            </div>
                            <div id="email-message"></div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Email Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-wrench me-2"></i>Maintenance Mode</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info alert-sm mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            When enabled, customers cannot access the shop until this mode is disabled.
                        </div>

                        <!-- Current Status -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center">
                                <span class="me-2">Current Status:</span>
                                <span id="maintenance-status" class="badge bg-secondary">Loading...</span>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="d-grid gap-2 mb-3">
                            <button id="enable-maintenance-btn" class="btn btn-warning" onclick="toggleMaintenanceMode(true)">
                                <i class="fas fa-wrench me-2"></i>Enable Maintenance Mode
                            </button>
                            <button id="disable-maintenance-btn" class="btn btn-success" onclick="toggleMaintenanceMode(false)">
                                <i class="fas fa-check me-2"></i>Disable Maintenance Mode
                            </button>
                        </div>

                        <!-- Custom Message -->
                        <div class="mb-3">
                            <label class="form-label">Maintenance Message</label>
                            <textarea class="form-control" id="maintenance-message" rows="3" placeholder="Tell customers when the store will be back..."></textarea>
                            <div class="form-text">This message will be shown to customers when maintenance mode is active.</div>
                        </div>

                        <div id="maintenance-message-display" class="mt-3"></div>

                        <button onclick="saveMaintenanceMessage()" class="btn btn-primary w-100">
                            <i class="fas fa-save me-2"></i>Save Message
                        </button>
                    </div>
                </div>

                <div class="card mt-4" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-database me-2"></i>System Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button onclick="backupDatabase()" class="btn btn-warning">
                                <i class="fas fa-download me-2"></i>Backup Database
                            </button>
                            <button onclick="clearCache()" class="btn btn-info">
                                <i class="fas fa-broom me-2"></i>Clear Cache
                            </button>
                            <button onclick="resetSettings()" class="btn btn-danger">
                                <i class="fas fa-undo me-2"></i>Reset to Defaults
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Wait for DOM to be fully updated before accessing elements
    setTimeout(async () => {
        // Load current settings
        await loadSettingsData();
    }, 0);

    // Business form handler
    document.getElementById('businessForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveBusinessSettings();
    });

    // Email form handler
    document.getElementById('emailForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveEmailSettings();
    });
}

async function loadSettingsData() {
    try {
        const response = await fetch(`${API_BASE}/settings/index.php`);
        const data = await response.json();

        if (data.success) {
            // Convert settings array to object for easier access
            const settings = {};
            if (data.settings && Array.isArray(data.settings)) {
                data.settings.forEach(setting => {
                    settings[setting.setting_key] = setting.parsed_value !== undefined ? setting.parsed_value : setting.setting_value;
                });
            } else if (data.data) {
                // Fallback for different response structure
                Object.assign(settings, data.data);
            }

            // Helper function to safely set element value
            const setElementValue = (id, value) => {
                const element = document.getElementById(id);
                if (element) {
                    if (element.type === 'checkbox') {
                        element.checked = value;
                    } else {
                        element.value = value || '';
                    }
                }
            };

            // Business settings
            setElementValue('business-name', settings.store_name);
            setElementValue('business-email', settings.store_email);
            setElementValue('business-phone', settings.store_phone);
            setElementValue('tax-rate', settings.tax_rate);
            setElementValue('business-address', settings.store_address);

            // Email settings
            setElementValue('smtp-host', settings.smtp_host);
            setElementValue('smtp-port', settings.smtp_port);
            setElementValue('smtp-username', settings.smtp_username);
            setElementValue('smtp-password', settings.smtp_password);
            setElementValue('smtp-ssl', settings.smtp_ssl || false);

            // Maintenance mode settings
            const maintenanceEnabled = settings.maintenance_mode === 'true' || settings.maintenance_mode === true;
            setElementValue('maintenance-mode', maintenanceEnabled);
            setElementValue('maintenance-message', settings.maintenance_message);

            // Update maintenance status badge
            const statusBadge = document.getElementById('maintenance-status');
            if (statusBadge) {
                if (maintenanceEnabled) {
                    statusBadge.className = 'badge bg-warning';
                    statusBadge.textContent = 'Active';
                } else {
                    statusBadge.className = 'badge bg-success';
                    statusBadge.textContent = 'Disabled';
                }
            }
        }
    } catch (error) {
        devLog('Error loading settings:', error);
    }
}

// Helper function to show maintenance message preview
function showMessagePreview(message) {
	const preview = document.getElementById('maintenance-preview');
	const previewText = document.getElementById('maintenance-message-preview');
	
	if (preview && previewText) {
		if (message && message.trim()) {
			previewText.textContent = message;
			preview.classList.remove('d-none');
		} else {
			preview.classList.add('d-none');
		}
	}
}

// Helper function to update maintenance mode UI
function updateMaintenanceUI(isEnabled) {
	const statusBadge = document.getElementById('maintenance-status');
	const statusIcon = document.getElementById('maintenance-icon');
	const enableBtn = document.getElementById('enable-maintenance-btn');
	const disableBtn = document.getElementById('disable-maintenance-btn');

	if (statusBadge) {
		if (isEnabled) {
			statusBadge.className = 'badge bg-warning';
			statusBadge.textContent = 'ACTIVE';
		} else {
			statusBadge.className = 'badge bg-success';
			statusBadge.textContent = 'DISABLED';
		}
	}

	if (statusIcon) {
		if (isEnabled) {
			statusIcon.className = 'fas fa-exclamation-triangle fa-2x';
			statusIcon.style.color = '#ffc107';
		} else {
			statusIcon.className = 'fas fa-check-circle fa-2x';
			statusIcon.style.color = '#22c55e';
		}
	}

	// Update button states
	if (enableBtn) enableBtn.disabled = isEnabled;
	if (disableBtn) disableBtn.disabled = !isEnabled;
}

async function saveBusinessSettings() {
    const businessNameEl = document.getElementById('business-name');
    const businessEmailEl = document.getElementById('business-email');
    const businessPhoneEl = document.getElementById('business-phone');
    const taxRateEl = document.getElementById('tax-rate');
    const businessAddressEl = document.getElementById('business-address');

    if (!businessNameEl || !businessEmailEl || !businessPhoneEl || !taxRateEl || !businessAddressEl) {
        showError('Could not find business settings form elements');
        return;
    }

    const formData = {
        business_name: businessNameEl.value,
        business_email: businessEmailEl.value,
        business_phone: businessPhoneEl.value,
        tax_rate: taxRateEl.value,
        business_address: businessAddressEl.value
    };

    try {
        const response = await fetch(`${API_BASE}/settings/index.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Business settings saved successfully');
        } else {
            showError(result.message || 'Failed to save business settings');
        }
    } catch (error) {
        showError('Failed to save business settings');
    }
}

async function saveEmailSettings() {
    const smtpHostEl = document.getElementById('smtp-host');
    const smtpPortEl = document.getElementById('smtp-port');
    const smtpUsernameEl = document.getElementById('smtp-username');
    const smtpPasswordEl = document.getElementById('smtp-password');
    const smtpSslEl = document.getElementById('smtp-ssl');

    if (!smtpHostEl || !smtpPortEl || !smtpUsernameEl || !smtpPasswordEl || !smtpSslEl) {
        showError('Could not find email settings form elements');
        return;
    }

    const formData = {
        smtp_host: smtpHostEl.value,
        smtp_port: smtpPortEl.value,
        smtp_username: smtpUsernameEl.value,
        smtp_password: smtpPasswordEl.value,
        smtp_ssl: smtpSslEl.checked
    };

    try {
        const response = await fetch(`${API_BASE}/settings/index.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Email settings saved successfully');
        } else {
            showError(result.message || 'Failed to save email settings');
        }
    } catch (error) {
        showError('Failed to save email settings');
    }
}

async function saveMaintenanceSettings() {
    const maintenanceModeEl = document.getElementById('maintenance-mode');
    const maintenanceMessageEl = document.getElementById('maintenance-message');

    if (!maintenanceModeEl || !maintenanceMessageEl) {
        showError('Could not find maintenance settings elements');
        return;
    }

    const formData = {
        maintenance_mode: maintenanceModeEl.checked ? 'true' : 'false',
        maintenance_message: maintenanceMessageEl.value
    };

    try {
        const response = await fetch(`${API_BASE}/settings/index.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Maintenance settings saved successfully');
            if (formData.maintenance_mode === 'true') {
                showAlert('<strong>Maintenance Mode Enabled!</strong> Customers cannot access the shop until you disable this mode.', 'warning');
            }
        } else {
            showError(result.message || 'Failed to save maintenance settings');
        }
    } catch (error) {
        showError('Failed to save maintenance settings');
    }
}

function applyThemeSettings() {
    const themeMode = document.getElementById('theme-mode').value;
    const accentColor = document.getElementById('accent-color').value;

    // Apply theme (this would need CSS variable updates)
    showSuccess('Theme settings applied (visual changes require page refresh)');
}

async function backupDatabase() {
    try {
        showSuccess('Database backup initiated. Download will start shortly.');
        // Implementation would depend on backend API
    } catch (error) {
        showError('Failed to backup database');
    }
}

async function clearCache() {
    try {
        const response = await fetch(`${API_BASE}/settings/clear-cache.php`, {
            method: 'POST'
        });

        if (response.ok) {
            showSuccess('Cache cleared successfully');
        } else {
            showError('Failed to clear cache');
        }
    } catch (error) {
        showError('Failed to clear cache');
    }
}

async function resetSettings() {
    if (await showConfirm('Are you sure you want to reset all settings to defaults? This cannot be undone.', {
        title: 'Reset Settings',
        confirmText: 'Reset',
        type: 'danger'
    })) {
        try {
            const response = await fetch(`${API_BASE}/settings/reset.php`, {
                method: 'POST'
            });

            if (response.ok) {
                showSuccess('Settings reset to defaults');
                loadSettingsData();
            } else {
                showError('Failed to reset settings');
            }
        } catch (error) {
            showError('Failed to reset settings');
        }
    }
}

// Maintenance Mode Functions
async function toggleMaintenanceMode(enable) {
    const action = enable ? 'enable' : 'disable';
    const confirmMessage = enable
        ? 'Are you sure you want to enable maintenance mode? This will prevent customers from accessing the shop.'
        : 'Are you sure you want to disable maintenance mode? This will allow customers to access the shop again.';

    if (!await showConfirm(confirmMessage, {
        title: `${enable ? 'Enable' : 'Disable'} Maintenance Mode`,
        confirmText: enable ? 'Enable' : 'Disable',
        type: enable ? 'warning' : 'success',
        icon: enable ? 'fas fa-wrench' : 'fas fa-check'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/settings/index.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                maintenance_mode: enable ? 'true' : 'false'
            })
        });

        // Debug: Log response status
        console.log(`Maintenance mode ${action} - Response Status:`, response.status, response.statusText);

        // Check if response is OK first (200-299)
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
            console.error('Response not OK:', errorData);
            showError(errorData.message || `Failed to ${action} maintenance mode (HTTP ${response.status})`);
            return;
        }

        const result = await response.json();
        console.log('Maintenance mode API response:', result);

        // Check for success in response
        if (result && result.success) {
            showSuccess(`Maintenance mode ${enable ? 'enabled' : 'disabled'} successfully`);
            // Refresh the status display
            loadMaintenanceStatus();
        } else {
            console.error('API returned success=false:', result);
            showError(result.message || `Failed to ${action} maintenance mode`);
        }
    } catch (error) {
        console.error('Maintenance mode toggle error:', error);
        showError(`Failed to ${action} maintenance mode: ${error.message || 'Unknown error'}`);
    }
}

async function saveMaintenanceMessage() {
    const message = document.getElementById('maintenance-message').value;

    try {
        const response = await fetch(`${API_BASE}/settings/index.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                maintenance_message: message
            })
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Maintenance message saved successfully');
            // Refresh the status display
            loadMaintenanceStatus();
        } else {
            showError(result.message || 'Failed to save maintenance message');
        }
    } catch (error) {
        showError('Failed to save maintenance message');
    }
}

async function loadMaintenanceStatus() {
    try {
        const response = await fetch(`${API_BASE}/settings/index.php`);
        const data = await response.json();

        if (data.success) {
            // Convert settings array to object for easier access
            const settings = {};
            if (data.settings && Array.isArray(data.settings)) {
                data.settings.forEach(setting => {
                    settings[setting.setting_key] = setting.parsed_value !== undefined ? setting.parsed_value : setting.setting_value;
                });
            }

            const maintenanceEnabled = settings.maintenance_mode === 'true' || settings.maintenance_mode === true;
            const statusElement = document.getElementById('maintenance-status');

            if (statusElement) {
                statusElement.className = `badge ${maintenanceEnabled ? 'bg-warning' : 'bg-success'}`;
                statusElement.textContent = maintenanceEnabled ? 'Enabled' : 'Disabled';
            }

            // Update message display
            const messageElement = document.getElementById('maintenance-message-display');
            if (messageElement) {
                const currentMessage = settings.maintenance_message || 'We are currently performing scheduled maintenance. Please check back later.';
                messageElement.innerHTML = `
                    <div class="alert alert-info">
                        <strong>Current Message:</strong><br>
                        ${currentMessage}
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading maintenance status:', error);
    }
}

// Helper functions
function formatDate(dateString) {
    if (!dateString || dateString === 'null' || dateString === 'undefined') {
        return null;
    }
    const date = new Date(dateString);
    if (isNaN(date.getTime())) {
        return null;
    }
    return date.toLocaleDateString();
}

function formatCurrency(amount) {
    return `₱${parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
}

function getStatusColor(status) {
    const colors = {
        'pending': 'warning',
        'approved': 'success',
        'rejected': 'danger',
        'completed': 'primary'
    };
    return colors[status] || 'secondary';
}

// Supplier-specific settings page
async function loadSupplierSettingsPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-cog me-2"></i>Supplier Settings</h1>
            <p class="page-subtitle">Manage your supplier account settings</p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-building me-2"></i>Company Information</h5>
                    </div>
                    <div class="card-body">
                        <form id="supplierForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" class="form-control" id="supplier-name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" id="supplier-contact">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" id="supplier-email" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="supplier-phone">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" id="supplier-address" rows="3"></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Payment Terms</label>
                                    <select class="form-select" id="supplier-payment-terms">
                                        <option value="">Select payment terms</option>
                                        <option value="Net 15">Net 15</option>
                                        <option value="Net 30">Net 30</option>
                                        <option value="Net 45">Net 45</option>
                                        <option value="Net 60">Net 60</option>
                                        <option value="Due on Receipt">Due on Receipt</option>
                                        <option value="COD">Cash on Delivery (COD)</option>
                                        <option value="Custom">Custom</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="supplier-active" checked>
                                <label class="form-check-label" for="supplier-active">
                                    Active Supplier Account
                                </label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" id="supplier-notes" rows="2"></textarea>
                            </div>
                            <div id="supplier-message"></div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Supplier Information
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card mt-4" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-bell me-2"></i>Notification Preferences</h5>
                    </div>
                    <div class="card-body">
                        <form id="notificationForm">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="notify-new-orders" checked>
                                <label class="form-check-label" for="notify-new-orders">
                                    Email notifications for new purchase orders
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="notify-order-updates" checked>
                                <label class="form-check-label" for="notify-order-updates">
                                    Email notifications for order status updates
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="notify-payments">
                                <label class="form-check-label" for="notify-payments">
                                    Email notifications for payment confirmations
                                </label>
                            </div>
                            <div id="notification-message"></div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Notification Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Total Orders:</span>
                                <strong id="total-orders">0</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Completed Orders:</span>
                                <strong id="completed-orders">0</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Pending Orders:</span>
                                <strong id="pending-orders">0</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Total Revenue:</span>
                                <strong id="total-revenue">₱0</strong>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Current Rating:</span>
                                <strong id="current-rating">⭐ 0.0</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Help & Support</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Need help with your supplier account?</p>
                        <div class="d-grid gap-2">
                            <button onclick="contactSupport()" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-envelope me-2"></i>Contact Support
                            </button>
                            <button onclick="viewDocumentation()" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-book me-2"></i>View Documentation
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Load supplier data
    await loadSupplierData();

    // Form handlers
    document.getElementById('supplierForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveSupplierSettings();
    });

    document.getElementById('notificationForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveNotificationSettings();
    });
}

async function loadSupplierData() {
    try {
        // Load supplier profile data
        const response = await fetch(`${API_BASE}/suppliers/show.php?id=${currentUser.id}`);
        const data = await response.json();

        if (data.success) {
            const supplier = data.data.supplier;

            // Populate form fields
            document.getElementById('supplier-name').value = supplier.name || '';
            document.getElementById('supplier-contact').value = supplier.contact_person || '';
            document.getElementById('supplier-email').value = supplier.email || '';
            document.getElementById('supplier-phone').value = supplier.phone || '';
            document.getElementById('supplier-address').value = supplier.address || '';
            document.getElementById('supplier-payment-terms').value = supplier.payment_terms || '';
            document.getElementById('supplier-rating').value = supplier.rating || 0;
            document.getElementById('supplier-active').checked = supplier.is_active == 1;
            document.getElementById('supplier-notes').value = supplier.notes || '';

            // Update performance overview from statistics
            const stats = supplier.statistics || {};
            document.getElementById('total-orders').textContent = stats.total_orders || 0;
            document.getElementById('completed-orders').textContent = stats.approved_orders || 0;
            document.getElementById('pending-orders').textContent = stats.draft_orders || 0;
            document.getElementById('total-revenue').textContent = `₱${(stats.total_spent || 0).toLocaleString()}`;
            document.getElementById('current-rating').textContent = `⭐ ${supplier.rating || 0}`;
        } else {
            // If supplier profile doesn't exist, show a message
            showError('Supplier profile not found. Please contact administrator to set up your supplier account.');
        }
    } catch (error) {
        devLog('Error loading supplier data:', error);
        if (error.message.includes('404')) {
            showError('Supplier profile not found. Please contact administrator to set up your supplier account.');
        } else {
            showError('Failed to load supplier information');
        }
    }
}

async function saveSupplierSettings() {
    const formData = {
        name: document.getElementById('supplier-name').value,
        contact_person: document.getElementById('supplier-contact').value,
        email: document.getElementById('supplier-email').value,
        phone: document.getElementById('supplier-phone').value,
        address: document.getElementById('supplier-address').value,
        payment_terms: document.getElementById('supplier-payment-terms').value,
        rating: document.getElementById('supplier-rating').value,
        is_active: document.getElementById('supplier-active').checked ? 1 : 0,
        notes: document.getElementById('supplier-notes').value
    };

    try {
        const response = await fetch(`${API_BASE}/suppliers/update.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Supplier information updated successfully');
        } else {
            showError(result.message || 'Failed to update supplier information');
        }
    } catch (error) {
        showError('Failed to update supplier information');
    }
}

async function saveNotificationSettings() {
    const formData = {
        notify_new_orders: document.getElementById('notify-new-orders').checked,
        notify_order_updates: document.getElementById('notify-order-updates').checked,
        notify_payments: document.getElementById('notify-payments').checked
    };

    try {
        const response = await fetch(`${API_BASE}/suppliers/notifications.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Notification preferences saved successfully');
        } else {
            showError(result.message || 'Failed to save notification preferences');
        }
    } catch (error) {
        showError('Failed to save notification preferences');
    }
}

function contactSupport() {
    // Open email client or show contact form
    window.location.href = 'mailto:support@pcparts.com?subject=Supplier Support Request';
}

function viewDocumentation() {
    // Open documentation page
    window.open('/docs/supplier-guide.html', '_blank');
}

// Supplier-specific page functions
async function loadSupplierPendingOrdersPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-clock me-2"></i>Pending Purchase Orders</h1>
            <p class="page-subtitle">Review and approve purchase orders awaiting your action</p>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Orders Awaiting Approval</h5>
            </div>
            <div class="card-body">
                <div id="loading-indicator" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading pending orders...</p>
                </div>

                <div id="orders-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>PO Number</th>
                                    <th>Order Date</th>
                                    <th>Expected Delivery</th>
                                    <th>Items</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pending-orders-tbody">
                                <!-- Orders will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-orders-message" class="text-center py-5 d-none">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h5 class="text-muted">No Pending Orders</h5>
                    <p class="text-muted">All purchase orders have been processed. Check back later for new orders.</p>
                </div>
            </div>
        </div>
    `;

    // Load pending orders
    await loadPendingOrders();
}

async function loadPendingOrders() {
    const loadingIndicator = document.getElementById('loading-indicator');
    const ordersContent = document.getElementById('orders-content');
    const noOrdersMessage = document.getElementById('no-orders-message');
    const tbody = document.getElementById('pending-orders-tbody');

    try {
        loadingIndicator.classList.remove('d-none');
        ordersContent.classList.add('d-none');
        noOrdersMessage.classList.add('d-none');

        const response = await fetch(`${API_BASE}/purchase_orders/pending.php`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.orders && data.orders.length > 0) {
            tbody.innerHTML = data.orders.map(order => `
                <tr>
                    <td>
                        <strong>${order.po_number || `PO-${order.id}`}</strong>
                    </td>
                    <td>${formatDate(order.created_at)}</td>
                    <td>${formatDate(order.expected_delivery)}</td>
                    <td>
                        <span class="badge bg-info">${order.total_items || 0} items</span>
                    </td>
                    <td>
                        <strong class="text-primary">${formatCurrency(order.total_amount)}</strong>
                    </td>
                    <td>
                        <span class="badge bg-warning">
                            <i class="fas fa-clock me-1"></i>Pending Approval
                        </span>
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button onclick="viewPurchaseOrder(${order.id})" class="btn btn-outline-primary" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button onclick="approvePurchaseOrder(${order.id})" class="btn btn-outline-success" title="Approve Order">
                                <i class="fas fa-check"></i>
                            </button>
                            <button onclick="rejectPurchaseOrder(${order.id})" class="btn btn-outline-danger" title="Reject Order">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');

            ordersContent.classList.remove('d-none');
        } else {
            noOrdersMessage.classList.remove('d-none');
        }
    } catch (error) {
        console.error('Error loading pending orders:', error);
        loadingIndicator.classList.add('d-none');
        showError('Failed to load pending purchase orders. Please try again.');
    }
}

async function loadSupplierOrderHistoryPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-history me-2"></i>Purchase Order History</h1>
            <p class="page-subtitle">View all your past purchase orders and their current status</p>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Purchase Orders</h5>
                    <div class="d-flex gap-2 align-items-center">
                        <label class="form-label mb-0 me-2">Filter by Status:</label>
                        <select id="status-filter" class="form-select form-select-sm" style="width: auto;">
                            <option value="all">All Status</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div id="loading-indicator" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading order history...</p>
                </div>

                <div id="orders-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>PO Number</th>
                                    <th>Order Date</th>
                                    <th>Items</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="order-history-tbody">
                                <!-- Orders will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-orders-message" class="text-center py-5 d-none">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Orders Found</h5>
                    <p class="text-muted">You haven't received any purchase orders yet.</p>
                </div>
            </div>
        </div>
    `;

    // Load order history
    await loadOrderHistory();

    // Add event listener for status filter
    document.getElementById('status-filter').addEventListener('change', async (e) => {
        await loadOrderHistory(e.target.value);
    });
}

async function loadOrderHistory(statusFilter = 'all') {
    const loadingIndicator = document.getElementById('loading-indicator');
    const ordersContent = document.getElementById('orders-content');
    const noOrdersMessage = document.getElementById('no-orders-message');
    const tbody = document.getElementById('order-history-tbody');

    try {
        loadingIndicator.classList.remove('d-none');
        ordersContent.classList.add('d-none');
        noOrdersMessage.classList.add('d-none');

        const url = statusFilter === 'all'
            ? `${API_BASE}/purchase_orders/history.php`
            : `${API_BASE}/purchase_orders/history.php?status=${statusFilter}`;

        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.orders && data.orders.length > 0) {
            tbody.innerHTML = data.orders.map(order => {
                let statusBadge = '';
                let statusIcon = '';

                switch(order.status) {
                    case 'approved':
                        statusBadge = 'bg-success';
                        statusIcon = 'fas fa-check-circle';
                        break;
                    case 'rejected':
                        statusBadge = 'bg-danger';
                        statusIcon = 'fas fa-times-circle';
                        break;
                    case 'completed':
                        statusBadge = 'bg-primary';
                        statusIcon = 'fas fa-check-double';
                        break;
                    default:
                        statusBadge = 'bg-secondary';
                        statusIcon = 'fas fa-question-circle';
                }

                return `
                    <tr>
                        <td>
                            <strong>${order.po_number || `PO-${order.id}`}</strong>
                        </td>
                        <td>${formatDate(order.created_at)}</td>
                        <td>
                            <span class="badge bg-info">${order.total_items || 0} items</span>
                        </td>
                        <td>
                            <strong class="text-primary">${formatCurrency(order.total_amount)}</strong>
                        </td>
                        <td>
                            <span class="badge ${statusBadge}">
                                <i class="${statusIcon} me-1"></i>${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                            </span>
                        </td>
                        <td>${formatDate(order.updated_at || order.created_at)}</td>
                        <td>
                            <button onclick="viewPurchaseOrder(${order.id})" class="btn btn-outline-primary btn-sm" title="View Details">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            ordersContent.classList.remove('d-none');
        } else {
            noOrdersMessage.classList.remove('d-none');
        }
    } catch (error) {
        console.error('Error loading order history:', error);
        loadingIndicator.classList.add('d-none');
        showError('Failed to load purchase order history. Please try again.');
    }
}

// Purchase Order Action Functions
async function viewPurchaseOrder(orderId) {
    try {
        const response = await fetch(`${API_BASE}/purchase_orders/show.php?id=${orderId}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success && (data.purchase_order || data.order || data.data?.purchase_order)) {
            const order = data.purchase_order || data.order || data.data?.purchase_order;
            const modalContent = `
                <div class="modal fade" id="orderModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                            <div class="modal-header" style="border-color: var(--border-color);">
                                <h5 class="modal-title">
                                    <i class="fas fa-file-invoice me-2"></i>
                                    Purchase Order ${order.po_number || `PO-${order.id}`}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Order Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Order Date:</strong></td>
                                                <td>${formatDate(order.created_at)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Expected Delivery:</strong></td>
                                                <td>${formatDate(order.expected_delivery || order.expected_delivery_date)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Status:</strong></td>
                                                <td>
                                                    <span class="badge bg-${order.status === 'approved' ? 'success' : order.status === 'rejected' ? 'danger' : order.status === 'completed' ? 'primary' : 'warning'}">
                                                        ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Items:</strong></td>
                                                <td>${order.items?.length || 0}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Amount:</strong></td>
                                                <td><strong class="text-primary">${formatCurrency(order.total_amount)}</strong></td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Supplier Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Company:</strong></td>
                                                <td>${order.supplier?.name || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Contact:</strong></td>
                                                <td>${order.supplier?.contact_person || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td>${order.supplier?.email || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Phone:</strong></td>
                                                <td>${order.supplier?.phone || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <h6 class="text-primary mb-3">Order Items</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>SKU</th>
                                                <th>Quantity</th>
                                                <th>Unit Price</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${(order.items || []).map(item => `
                                                <tr>
                                                    <td>${item.product?.name || item.product_name || 'N/A'}</td>
                                                    <td><code>${item.product?.sku || item.sku || 'N/A'}</code></td>
                                                    <td>${item.quantity_ordered || item.quantity || 0}</td>
                                                    <td>${formatCurrency(item.unit_cost || item.unit_price || 0)}</td>
                                                    <td><strong>${formatCurrency((item.quantity_ordered || item.quantity || 0) * (item.unit_cost || item.unit_price || 0))}</strong></td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>

                                ${order.notes ? `
                                    <div class="mt-3">
                                        <h6 class="text-primary">Notes</h6>
                                        <p class="text-muted">${order.notes}</p>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="modal-footer" style="border-color: var(--border-color);">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                ${order.status === 'pending' && currentUser.role === 'supplier' ? `
                                    <button onclick="approvePurchaseOrder(${order.id})" class="btn btn-success">
                                        <i class="fas fa-check me-1"></i>Approve Order
                                    </button>
                                    <button onclick="rejectPurchaseOrder(${order.id})" class="btn btn-danger">
                                        <i class="fas fa-times me-1"></i>Reject Order
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if present
            const existingModal = document.getElementById('orderModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalContent);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('orderModal'));
            modal.show();

            // Clean up modal when hidden
            document.getElementById('orderModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        } else {
            console.error('Invalid API response format:', data);
            showError('Failed to load purchase order details - Invalid response format');
        }
    } catch (error) {
        console.error('Error loading purchase order:', error);
        showError('Failed to load purchase order details - ' + error.message);
    }
}

async function approvePurchaseOrder(orderId) {
    if (!await showConfirm('Are you sure you want to approve this purchase order?', {
        title: 'Approve Purchase Order',
        confirmText: 'Approve',
        type: 'success',
        icon: 'fas fa-check'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/purchase_orders/approve.php?id=${orderId}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success) {
            showSuccess('Purchase order approved successfully');
            // Refresh current page
            if (window.location.hash.includes('purchase-orders')) {
                await loadSupplierPendingOrdersPage();
            } else if (window.location.hash.includes('po-history')) {
                await loadSupplierOrderHistoryPage();
            }
        } else {
            showError(data.message || 'Failed to approve purchase order');
        }
    } catch (error) {
        console.error('Error approving purchase order:', error);
        showError('Failed to approve purchase order');
    }
}

async function rejectPurchaseOrder(orderId) {
    const reason = await showPrompt('Please provide a reason for rejecting this purchase order:', {
        title: 'Reject Purchase Order',
        required: true
    });

    if (!reason) return;

    try {
        const response = await fetch(`${API_BASE}/purchase_orders/reject.php?id=${orderId}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ reason })
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.success) {
            showSuccess('Purchase order rejected successfully');
            // Refresh current page
            if (window.location.hash.includes('purchase-orders')) {
                await loadSupplierPendingOrdersPage();
            } else if (window.location.hash.includes('po-history')) {
                await loadSupplierOrderHistoryPage();
            }
        } else {
            showError(data.message || 'Failed to reject purchase order');
        }
    } catch (error) {
        console.error('Error rejecting purchase order:', error);
        showError('Failed to reject purchase order');
    }
}

// Helper function for showing prompts
function showPrompt(message, options = {}) {
    const { title = 'Enter Details', required = false, defaultValue = '' } = options;

    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';
        overlay.innerHTML = `
            <div class="confirm-dialog">
                <div class="confirm-header">
                    <div class="confirm-icon warning">
                        <i class="fas fa-edit"></i>
                    </div>
                    <h5 class="confirm-title">${title}</h5>
                </div>
                <div class="confirm-body">
                    <p>${message}</p>
                    <textarea class="form-control" id="prompt-input" rows="3" placeholder="Enter your response here..." ${required ? 'required' : ''}>${defaultValue}</textarea>
                </div>
                <div class="confirm-footer">
                    <button class="confirm-btn confirm-btn-cancel" data-action="cancel">
                        Cancel
                    </button>
                    <button class="confirm-btn confirm-btn-confirm warning" data-action="confirm" ${required ? 'disabled' : ''}>
                        Submit
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        const input = overlay.querySelector('#prompt-input');
        const confirmBtn = overlay.querySelector('[data-action="confirm"]');

        if (required) {
            input.addEventListener('input', () => {
                confirmBtn.disabled = !input.value.trim();
            });
        }

        overlay.addEventListener('click', (e) => {
            if (e.target.dataset.action === 'confirm') {
                const value = input.value.trim();
                if (!required || value) {
                    overlay.remove();
                    resolve(value);
                }
            } else if (e.target.dataset.action === 'cancel' || e.target.classList.contains('confirm-overlay')) {
                overlay.remove();
                resolve(null);
            }
        });

        input.focus();

        const handleEsc = (e) => {
            if (e.key === 'Escape') {
                overlay.remove();
                resolve(null);
                document.removeEventListener('keydown', handleEsc);
            }
        };
        document.addEventListener('keydown', handleEsc);
    });
}

// Admin page loading functions (stubs for now)
async function loadAnalyticsPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-chart-line me-2"></i>Analytics</h1>
            <p class="page-subtitle">Business intelligence and reporting dashboard</p>
        </div>

        <!-- Analytics Overview -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="analytics-total-sales">₱0</div>
                            <div class="stat-label">Total Sales</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span id="sales-growth">+0%</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="analytics-total-orders">0</div>
                            <div class="stat-label">Total Orders</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(0, 245, 255, 0.1); color: var(--accent);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span id="orders-growth">+0%</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="analytics-avg-order">₱0</div>
                            <div class="stat-label">Avg Order Value</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">
                            <i class="fas fa-calculator"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-neutral">
                        <i class="fas fa-minus"></i>
                        <span id="avg-order-change">0%</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="analytics-profit-margin">0%</div>
                            <div class="stat-label">Profit Margin</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(255, 170, 0, 0.1); color: var(--warning);">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span id="margin-growth">+0%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="chart-container">
                    <div class="chart-header">
                        <h5 class="chart-title">Sales Trend (Last 30 Days)</h5>
                    </div>
                    <canvas id="sales-trend-chart" height="300"></canvas>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="chart-container">
                    <div class="chart-header">
                        <h5 class="chart-title">Sales by Category</h5>
                    </div>
                    <canvas id="category-sales-chart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Additional Analytics -->
        <div class="row">
            <div class="col-lg-6">
                <div class="chart-container">
                    <div class="chart-header">
                        <h5 class="chart-title">Top Selling Products</h5>
                    </div>
                    <div class="list-group list-group-flush" style="background: transparent;" id="top-products-list">
                        <div class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="chart-container">
                    <div class="chart-header">
                        <h5 class="chart-title">Customer Analytics</h5>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center p-3">
                                <div class="h3 text-primary" id="new-customers">0</div>
                                <div class="text-muted">New Customers</div>
                                <small class="text-success">This month</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3">
                                <div class="h3 text-success" id="returning-customers">0</div>
                                <div class="text-muted">Returning</div>
                                <small class="text-muted">This month</small>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center p-3">
                                <div class="h4 text-warning" id="avg-customer-value">₱0</div>
                                <div class="text-muted">Avg Customer Value</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-3">
                                <div class="h4 text-info" id="customer-retention">0%</div>
                                <div class="text-muted">Retention Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Load analytics data
    await loadAnalyticsData();
}

async function loadAnalyticsData() {
    try {
        const response = await fetch(`${API_BASE}/analytics/index.php`);
        const data = await response.json();

        if (data.success) {
            const analytics = data.data;

            // Update stats
            document.getElementById('analytics-total-sales').textContent = `₱${(analytics.total_sales || 0).toLocaleString()}`;
            document.getElementById('analytics-total-orders').textContent = analytics.total_orders || 0;
            document.getElementById('analytics-avg-order').textContent = `₱${(analytics.avg_order_value || 0).toLocaleString()}`;
            document.getElementById('analytics-profit-margin').textContent = `${(analytics.profit_margin || 0).toFixed(1)}%`;

            // Update trends (mock data for now)
            document.getElementById('sales-growth').textContent = '+12.5%';
            document.getElementById('orders-growth').textContent = '+8.3%';
            document.getElementById('avg-order-change').textContent = '+2.1%';
            document.getElementById('margin-growth').textContent = '+5.7%';

            // Update customer analytics
            document.getElementById('new-customers').textContent = analytics.new_customers || 0;
            document.getElementById('returning-customers').textContent = analytics.returning_customers || 0;
            document.getElementById('avg-customer-value').textContent = `₱${(analytics.avg_customer_value || 0).toLocaleString()}`;
            document.getElementById('customer-retention').textContent = `${(analytics.customer_retention || 0).toFixed(1)}%`;

            // Initialize charts
            setTimeout(() => {
                initSalesTrendChart(analytics.sales_trend || []);
                initCategorySalesChart(analytics.category_sales || []);
                loadTopProducts(analytics.top_products || []);
            }, 100);
        } else {
            showError('Failed to load analytics data');
        }
    } catch (error) {
        console.error('Error loading analytics:', error);
        showError('Failed to load analytics data');
    }
}

function initSalesTrendChart(salesData) {
    const ctx = document.getElementById('sales-trend-chart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: salesData.map(item => new Date(item.date).toLocaleDateString()),
            datasets: [{
                label: 'Daily Sales (₱)',
                data: salesData.map(item => parseFloat(item.amount || 0)),
                borderColor: 'rgb(0, 245, 255)',
                backgroundColor: 'rgba(0, 245, 255, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { labels: { color: '#e2e8f0' } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { color: '#94a3b8' },
                    grid: { color: 'rgba(148, 163, 184, 0.1)' }
                },
                x: {
                    ticks: { color: '#94a3b8' },
                    grid: { color: 'rgba(148, 163, 184, 0.1)' }
                }
            }
        }
    });
}

function initCategorySalesChart(categoryData) {
    const ctx = document.getElementById('category-sales-chart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categoryData.map(item => item.category),
            datasets: [{
                data: categoryData.map(item => parseFloat(item.amount || 0)),
                backgroundColor: [
                    'rgba(0, 245, 255, 0.8)',
                    'rgba(34, 197, 94, 0.8)',
                    'rgba(168, 85, 247, 0.8)',
                    'rgba(255, 170, 0, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(59, 130, 246, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: { color: '#e2e8f0' },
                    position: 'bottom'
                }
            }
        }
    });
}

function loadTopProducts(products) {
    const container = document.getElementById('top-products-list');
    if (!container) return;

    if (products.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-3">No sales data available</div>';
        return;
    }

    container.innerHTML = products.slice(0, 5).map((product, index) => `
        <div class="list-group-item" style="background: transparent; border-color: var(--border-color); color: var(--text-primary);">
            <div class="d-flex justify-content-between align-items-center">
                <span class="badge bg-primary me-3">${index + 1}</span>
                <div class="flex-grow-1">
                    <strong>${product.name}</strong>
                    <br><small class="text-muted">${product.sold_quantity} units sold</small>
                </div>
                <span class="badge badge-primary">₱${parseFloat(product.total_revenue).toLocaleString()}</span>
            </div>
        </div>
    `).join('');
}

async function loadProductsPage() {
    const content = document.getElementById('page-content');
    const isStaff = currentUser.role === 'staff';
    const pageTitle = isStaff ? 'Product Lookup' : 'Products';
    const pageSubtitle = isStaff ? 'Search and view product information' : 'Manage your product catalog';

    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas ${isStaff ? 'fa-search' : 'fa-box'} me-2"></i>${pageTitle}</h1>
            <p class="page-subtitle">${pageSubtitle}</p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex gap-3 flex-grow-1">
                <select id="product-category-filter" class="form-select" style="max-width: 200px;">
                    <option value="all">All Categories</option>
                </select>
                <select id="product-status-filter" class="form-select" style="max-width: 180px;">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="low_stock">Low Stock</option>
                </select>
                <input type="text" id="product-search" class="form-control" placeholder="Search by name, SKU, or description..." style="max-width: 500px; flex-grow: 1;">
            </div>
            ${!isStaff ? `
            <button class="btn btn-primary" onclick="openProductModal()">
                <i class="fas fa-plus me-2"></i>Add Product
            </button>
            ` : ''}
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Products</h5>
            </div>
            <div class="card-body">
                <div id="products-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading products...</p>
                </div>

                <div id="products-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="products-table-body">
                                <!-- Products will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-products-message" class="text-center py-5 d-none">
                    <i class="fas fa-box fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Products Found</h5>
                    <p class="text-muted">Start by adding your first product.</p>
                    <button class="btn btn-primary" onclick="openProductModal()">
                        <i class="fas fa-plus me-2"></i>Add First Product
                    </button>
                </div>
            </div>
        </div>
    `;

    // Load categories for filter
    await loadCategoriesForFilter();

    // Load products
    await loadProducts();

    // Add event listeners
    document.getElementById('product-category-filter').addEventListener('change', () => loadProducts());
    document.getElementById('product-status-filter').addEventListener('change', () => loadProducts());
    document.getElementById('product-search').addEventListener('input', (e) => filterProducts(e.target.value));
}

async function loadCategoriesForFilter() {
    try {
        const response = await fetch(`${API_BASE}/categories/index.php`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();

        if (data.success) {
            const select = document.getElementById('product-category-filter');
            select.innerHTML = '<option value="all">All Categories</option>';

            const categories = data.data && data.data.categories ? data.data.categories : [];
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        devLog('Error loading categories:', error);
        showError('Failed to load categories');
    }
}

let allProducts = [];

async function loadProducts() {
    const loadingIndicator = document.getElementById('products-loading');
    const content = document.getElementById('products-content');
    const noProductsMessage = document.getElementById('no-products-message');
    const tbody = document.getElementById('products-table-body');

    const categoryFilter = document.getElementById('product-category-filter').value;
    const statusFilter = document.getElementById('product-status-filter').value;

    try {
        loadingIndicator.classList.remove('d-none');
        content.classList.add('d-none');
        noProductsMessage.classList.add('d-none');

        let url = `${API_BASE}/products/index.php`;
        const params = [];

        if (categoryFilter !== 'all') params.push(`category_id=${categoryFilter}`);

        // Handle low_stock filter separately
        if (statusFilter === 'low_stock') {
            params.push('low_stock=1');
        } else if (statusFilter !== 'all') {
            params.push(`status=${statusFilter}`);
        }

        if (params.length > 0) url += '?' + params.join('&');

        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        });
        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.data && data.data.products && data.data.products.length > 0) {
            allProducts = data.data.products;
            displayProducts(allProducts);
            content.classList.remove('d-none');
        } else {
            noProductsMessage.classList.remove('d-none');
        }
    } catch (error) {
        devLog('Error loading products:', error);
        loadingIndicator.classList.add('d-none');
        showError('Failed to load products. Please try again.');
    }
}

function displayProducts(products) {
    const tbody = document.getElementById('products-table-body');
    const isStaff = currentUser.role === 'staff';

        tbody.innerHTML = products.map(product => {
            // Handle relative paths that work with htaccess routing
            let imageUrl = 'assets/img/no-image.png';
            if (product.image_url) {
                imageUrl = product.image_url;
            }

        // Use quantity_on_hand from API (not stock_quantity)
        const stockQty = product.quantity_on_hand || product.quantity_available || 0;
        const reorderLevel = product.reorder_level || product.low_stock_threshold || 5;
        const stockStatus = stockQty <= reorderLevel ? 'danger' : stockQty <= (reorderLevel * 2) ? 'warning' : 'success';
        const statusBadge = product.is_active == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';

        // Handle category - API returns category_name field
        const categoryName = product.category_name || product.category?.name || (typeof product.category === 'string' ? product.category : 'N/A');

        return `
            <tr>
                <td>
                    <img src="${imageUrl}" alt="${product.name}" class="product-thumbnail" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                </td>
                <td>
                    <strong>${product.name}</strong>
                    ${product.description ? `<br><small class="text-muted">${product.description.substring(0, 50)}...</small>` : ''}
                </td>
                <td><code>${product.sku}</code></td>
                <td>${categoryName}</td>
                <td>
                    <div>
                        <strong class="text-primary">${formatCurrency(product.selling_price)}</strong>
                        ${!isStaff && product.cost_price ? `<br><small class="text-muted">Cost: ${formatCurrency(product.cost_price)}</small>` : ''}
                    </div>
                </td>
                <td>
                    <span class="badge bg-${stockStatus}">${stockQty}</span>
                    ${reorderLevel ? `<br><small class="text-muted">Min: ${reorderLevel}</small>` : ''}
                </td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button onclick="viewProduct(${product.id})" class="btn btn-outline-info" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${!isStaff ? `
                        <button onclick="editProduct(${product.id})" class="btn btn-outline-primary" title="Edit Product">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteProduct(${product.id})" class="btn btn-outline-danger" title="Delete Product">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function filterProducts(searchTerm) {
    const filtered = allProducts.filter(product =>
        product.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        product.sku.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (product.description && product.description.toLowerCase().includes(searchTerm.toLowerCase()))
    );
    displayProducts(filtered);
}

async function openProductModal(productId = null) {
    // Note: categories are loaded after the modal is inserted so the
    // '#product-category' element exists in the DOM. Calling the loader
    // before inserting the modal caused a "null" element and a crash.

    const modalContent = `
        <div class="modal fade" id="productModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title"><i class="fas fa-box me-2"></i><span id="product-modal-title">Add Product</span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="productForm" enctype="multipart/form-data">
                            <input type="hidden" id="product-id">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Product Name *</label>
                                            <input type="text" class="form-control" id="product-name" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">SKU *</label>
                                            <input type="text" class="form-control" id="product-sku" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Category *</label>
                                            <select class="form-select" id="product-category" required>
                                                <option value="">Select Category</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Description</label>
                                            <input type="text" class="form-control" id="product-description">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Cost Price</label>
                                            <input type="number" class="form-control" id="product-cost-price" min="0" step="0.01">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Selling Price *</label>
                                            <input type="number" class="form-control" id="product-selling-price" min="0" step="0.01" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Tax Rate (%)</label>
                                            <input type="number" class="form-control" id="product-tax-rate" min="0" max="100" step="0.01" value="0">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Stock Quantity</label>
                                            <input type="number" class="form-control" id="product-stock-quantity" min="0" value="0">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Low Stock Threshold</label>
                                            <input type="number" class="form-control" id="product-low-stock-threshold" min="0" value="5">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Reorder Point</label>
                                            <input type="number" class="form-control" id="product-reorder-point" min="0" value="10">
                                        </div>
                                    </div>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="product-active" checked>
                                        <label class="form-check-label" for="product-active">
                                            Active Product
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Product Image</label>
                                        <input type="file" class="form-control" id="product-image" accept="image/*">
                                        <div class="form-text">Supported formats: JPG, PNG, GIF. Max size: 2MB</div>
                                        <div id="current-image-preview" class="mt-2 d-none">
                                            <img id="current-image" src="" alt="Current image" class="img-fluid rounded" style="max-height: 200px;">
                                        </div>
                                    </div>
                                    <div class="border p-3 rounded">
                                        <h6>Product Summary</h6>
                                        <div class="mb-2">
                                            <strong>Margin:</strong> <span id="product-margin">0%</span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Stock Status:</strong> <span id="product-stock-status">In Stock</span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Final Price:</strong> <span id="product-final-price">₱0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="product-message" class="mt-3"></div>
                        </form>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveProductBtn">Save Product</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('productModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalContent);

    // Load categories for dropdown now that the modal markup exists
    await loadCategoriesForProduct();

    if (productId) {
        document.getElementById('product-modal-title').textContent = 'Edit Product';
        document.getElementById('saveProductBtn').textContent = 'Update Product';
        await loadProductForEdit(productId);
    } else {
        resetProductForm();
    }

    // Add event listeners for real-time calculations
    document.getElementById('product-cost-price').addEventListener('input', calculateProductSummary);
    document.getElementById('product-selling-price').addEventListener('input', calculateProductSummary);
    document.getElementById('product-tax-rate').addEventListener('input', calculateProductSummary);
    document.getElementById('product-stock-quantity').addEventListener('input', updateStockStatus);

    // Add event listener for save button
    document.getElementById('saveProductBtn').addEventListener('click', saveProduct);

    const modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();

    // Move focus into the modal to help screen readers and avoid aria-hidden/focus issues
    try {
        const pCloseBtn = document.querySelector('#productModal .btn-close');
        if (pCloseBtn) pCloseBtn.focus();
    } catch (e) { /* ignore */ }

    // Clean up modal when hidden
    document.getElementById('productModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

async function loadCategoriesForProduct() {
    try {
        const response = await fetch(`${API_BASE}/categories/index.php`);
        const data = await response.json();

        // Defensive checks: ensure the select element exists and API returned an array
        const select = document.getElementById('product-category');
        if (!select) {
            devLog('product-category select not found in DOM. Skipping category population.');
            return;
        }

        select.innerHTML = '<option value="">Select Category</option>';

        if (data.success && data.data && Array.isArray(data.data.categories)) {
            data.data.categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.name;
                select.appendChild(option);
            });
        } else {
            devLog('No categories returned or unexpected response:', data);
        }
    } catch (error) {
        devLog('Error loading categories:', error);
    }
}

function resetProductForm() {
    // Helper function to safely set element values
    const setElementValue = (id, value, isChecked = false) => {
        const element = document.getElementById(id);
        if (element) {
            if (isChecked) {
                element.checked = value;
            } else if (element.type === 'file') {
                element.value = '';
            } else {
                element.value = value;
            }
        }
    };

    // Helper function to safely manipulate elements
    const manipulateElement = (id, action) => {
        const element = document.getElementById(id);
        if (element) {
            action(element);
        }
    };

    setElementValue('product-id', '');
    setElementValue('product-name', '');
    setElementValue('product-sku', '');
    setElementValue('product-category', '');
    setElementValue('product-description', '');
    setElementValue('product-cost-price', '');
    setElementValue('product-selling-price', '');
    setElementValue('product-tax-rate', '0');
    setElementValue('product-stock-quantity', '0');
    setElementValue('product-low-stock-threshold', '5');
    setElementValue('product-reorder-point', '10');
    setElementValue('product-active', true, true);
    setElementValue('product-image', '');

    manipulateElement('current-image-preview', (el) => el.classList.add('d-none'));
    manipulateElement('product-message', (el) => el.innerHTML = '');

    calculateProductSummary();
    updateStockStatus();
}

async function loadProductForEdit(id) {
    try {
        const response = await fetch(`${API_BASE}/products/show.php?id=${id}`);
        const data = await response.json();

        if (data.success && data.data) {
            const product = data.data;
            document.getElementById('product-id').value = product.id;
            document.getElementById('product-name').value = product.name || '';
            document.getElementById('product-sku').value = product.sku || '';
            document.getElementById('product-category').value = product.category_id || '';
            document.getElementById('product-description').value = product.description || '';
            document.getElementById('product-cost-price').value = product.cost_price || '';
            document.getElementById('product-selling-price').value = product.selling_price || '';
            document.getElementById('product-tax-rate').value = product.tax_rate || '0';
            document.getElementById('product-stock-quantity').value = product.stock_quantity || '0';
            document.getElementById('product-low-stock-threshold').value = product.low_stock_threshold || '5';
            document.getElementById('product-reorder-point').value = product.reorder_point || '10';

            document.getElementById('product-active').checked = product.is_active == 1;

            // Show current image if exists
            if (product.image_url) {
                const currentImage = document.getElementById('current-image');

                // Handle both absolute paths (new uploads) and relative paths (old products)
                if (product.image_url.startsWith('/')) {
                    currentImage.src = product.image_url;
                } else if (product.image_url.startsWith('assets/')) {
                    currentImage.src = `/public/${product.image_url}`;
                } else {
                    currentImage.src = `/public/assets/img/products/${product.image_url}`;
                }

                document.getElementById('current-image-preview').classList.remove('d-none');
            }

            calculateProductSummary();
            updateStockStatus();
        } else {
            showError('Failed to load product details');
        }
    } catch (error) {
        devLog('Error loading product:', error);
        showError('Failed to load product details');
    }
}

function calculateProductSummary() {
    const costPrice = parseFloat(document.getElementById('product-cost-price').value) || 0;
    const sellingPrice = parseFloat(document.getElementById('product-selling-price').value) || 0;
    const taxRate = parseFloat(document.getElementById('product-tax-rate').value) || 0;

    const margin = costPrice > 0 ? ((sellingPrice - costPrice) / costPrice * 100) : 0;
    const finalPrice = sellingPrice * (1 + taxRate / 100);

    document.getElementById('product-margin').textContent = margin.toFixed(1) + '%';
    document.getElementById('product-final-price').textContent = formatCurrency(finalPrice);
}

function updateStockStatus() {
    const stockQuantity = parseInt(document.getElementById('product-stock-quantity').value) || 0;
    const lowStockThreshold = parseInt(document.getElementById('product-low-stock-threshold').value) || 5;

    let status = 'In Stock';
    if (stockQuantity === 0) status = 'Out of Stock';
    else if (stockQuantity <= lowStockThreshold) status = 'Low Stock';

    document.getElementById('product-stock-status').textContent = status;
}

async function saveProduct() {
    const productId = document.getElementById('product-id').value || '';
    const isEdit = productId !== '';

    try {
        // Step 1: Upload image first if selected
        let imageUrl = null;
        const imageFile = document.getElementById('product-image').files[0];
        if (imageFile) {
            const imageFormData = new FormData();
            imageFormData.append('image', imageFile);
            imageFormData.append('temp_upload', 'true');

            const imageResponse = await fetch(`${API_BASE}/products/upload-image.php`, {
                method: 'POST',
                body: imageFormData
            });

            const imageResult = await imageResponse.json();
            if (imageResult.success) {
                imageUrl = imageResult.data.image.url;
            } else {
                showError('Failed to upload image: ' + (imageResult.message || 'Unknown error'));
                return;
            }
        }

        // Step 2: Prepare product data
        const productData = {
            name: document.getElementById('product-name').value,
            sku: document.getElementById('product-sku').value,
            category_id: document.getElementById('product-category').value,
            description: document.getElementById('product-description').value,
            cost_price: document.getElementById('product-cost-price').value,
            selling_price: document.getElementById('product-selling-price').value,
            stock_quantity: document.getElementById('product-stock-quantity').value,
            reorder_level: document.getElementById('product-low-stock-threshold').value,
            is_active: document.getElementById('product-active').checked ? 1 : 0
        };

        // Add image URL if uploaded
        if (imageUrl) {
            productData.image_url = imageUrl;
        }

        // Add ID for edit
        if (isEdit) {
            productData.id = productId;
        }

        // Step 3: Create/Update product
        const url = isEdit ? `${API_BASE}/products/update.php` : `${API_BASE}/products/create.php`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(productData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess(`Product ${isEdit ? 'updated' : 'created'} successfully`);
            bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
            await loadProducts(); // Refresh the list
        } else {
            showError(result.message || `Failed to ${isEdit ? 'update' : 'create'} product`);
        }
    } catch (error) {
        devLog('Error saving product:', error);
        showError(`Failed to ${isEdit ? 'update' : 'create'} product: ` + error.message);
    }
}

async function viewProduct(id) {
    try {
        const response = await fetch(`${API_BASE}/products/show.php?id=${id}`);
        const data = await response.json();

        if (data.success && data.data) {
            const product = data.data;

            // Handle both absolute paths (new uploads) and relative paths (old products)
            let imageUrl = '/public/assets/img/no-image.png';
            if (product.image_url) {
                if (product.image_url.startsWith('/')) {
                    imageUrl = product.image_url;
                } else if (product.image_url.startsWith('assets/')) {
                    imageUrl = `/public/${product.image_url}`;
                } else {
                    imageUrl = `/public/assets/img/products/${product.image_url}`;
                }
            }

            const modalContent = `
                <div class="modal fade" id="viewProductModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                            <div class="modal-header" style="border-color: var(--border-color);">
                                <h5 class="modal-title">
                                    <i class="fas fa-box me-2"></i>
                                    ${product.name}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <img src="${imageUrl}" alt="${product.name}" class="img-fluid rounded mb-3">
                                        <div class="text-center">
                                            <h4 class="text-primary">${formatCurrency(product.selling_price)}</h4>
                                            ${product.cost_price ? `<small class="text-muted">Cost: ${formatCurrency(product.cost_price)}</small>` : ''}
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Name:</strong></td>
                                            <td>${product.name}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>SKU:</strong></td>
                                            <td><code>${product.sku}</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Category:</strong></td>
                                            <td>${product.category_name || 'N/A'}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                <span class="badge bg-${product.is_active == 1 ? 'success' : 'secondary'}">
                                                    ${product.is_active == 1 ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Stock:</strong></td>
                                            <td>
                                                <span class="badge bg-${(product.stock_quantity || 0) <= (product.low_stock_threshold || 5) ? 'danger' : 'success'}">
                                                    ${product.stock_quantity || 0}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Low Stock Alert:</strong></td>
                                            <td>${product.low_stock_threshold || 5}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Reorder Point:</strong></td>
                                            <td>${product.reorder_point || 10}</td>
                                        </tr>
                                    </table>
                                </div>
                                        </div>

                                        ${product.description ? `
                                            <div class="mt-3">
                                                <h6>Description</h6>
                                                <p class="text-muted">${product.description}</p>
                                            </div>
                                        ` : ''}

                                        <div class="mt-3">
                                            <h6>Financial Summary</h6>
                                            <div class="row">
                                                <div class="col-4">
                                                    <div class="text-center p-2 border rounded">
                                                        <div class="h6 text-primary">${formatCurrency(product.selling_price)}</div>
                                                        <small class="text-muted">Selling Price</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-center p-2 border rounded">
                                                        <div class="h6 text-success">${product.cost_price ? formatCurrency(product.cost_price) : 'N/A'}</div>
                                                        <small class="text-muted">Cost Price</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="text-center p-2 border rounded">
                                                        <div class="h6 text-info">${product.cost_price ? ((product.selling_price - product.cost_price) / product.cost_price * 100).toFixed(1) + '%' : 'N/A'}</div>
                                                        <small class="text-muted">Margin</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer" style="border-color: var(--border-color);">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button onclick="editProduct(${product.id})" class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i>Edit Product
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if present
            const existingModal = document.getElementById('viewProductModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalContent);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('viewProductModal'));
            modal.show();

            // Clean up modal when hidden
            document.getElementById('viewProductModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        } else {
            showError('Failed to load product details');
        }
    } catch (error) {
        console.error('Error loading product details:', error);
        showError('Failed to load product details');
    }
}

async function editProduct(id) {
    // Close view modal if open
    const viewModal = document.getElementById('viewProductModal');
    if (viewModal) {
        bootstrap.Modal.getInstance(viewModal).hide();
    }

    // Open edit modal
    openProductModal(id);
}

// Categories management page
async function loadCategoriesPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-layer-group me-2"></i>Categories</h1>
            <p class="page-subtitle">Manage product categories and organize your inventory</p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex gap-3 align-items-center">
                <div class="input-group" style="width: 900px;">
                    <input type="text" id="category-search" class="form-control" placeholder="Search categories..." style="border-radius: 25px 0 0 25px;">
                    <button class="btn btn-outline-secondary" type="button" onclick="searchCategories()" style="border-radius: 0 25px 25px 0;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            <button class="btn btn-primary" onclick="openCategoryModal()">
                <i class="fas fa-plus me-2"></i>Add Category
            </button>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Product Categories</h5>
            </div>
            <div class="card-body">
                <div id="categories-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading categories...</p>
                </div>

                <div id="categories-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Slug</th>
                                    <th>Description</th>
                                    <th>Icon</th>
                                    <th>Sort Order</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="categories-table-body">
                                <!-- Categories will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-categories-message" class="text-center py-5 d-none">
                    <i class="fas fa-box fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Categories Found</h5>
                    <p class="text-muted">Get started by creating your first category.</p>
                    <button class="btn btn-primary" onclick="openCategoryModal()">
                        <i class="fas fa-plus me-2"></i>Create First Category
                    </button>
                </div>
            </div>
        </div>
    `;

    // Load categories
    await loadCategories();

    // Add search event listener
    document.getElementById('category-search').addEventListener('input', (e) => searchCategories(e.target.value));
}

let allCategories = [];

async function loadCategories() {
    const loadingIndicator = document.getElementById('categories-loading');
    const content = document.getElementById('categories-content');
    const noCategoriesMessage = document.getElementById('no-categories-message');
    const tbody = document.getElementById('categories-table-body');

    const showInactive = document.getElementById('show-inactive-categories')?.checked || false;

    try {
        loadingIndicator.classList.remove('d-none');
        content.classList.add('d-none');
        noCategoriesMessage.classList.add('d-none');

        const response = await fetch(`${API_BASE}/categories/index.php?active_only=${!showInactive}`);
        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.data.categories && data.data.categories.length > 0) {
            allCategories = data.data.categories;
            displayCategories(allCategories);
            content.classList.remove('d-none');
        } else {
            noCategoriesMessage.classList.remove('d-none');
        }
    } catch (error) {
        devLog('Error loading categories:', error);
        loadingIndicator.classList.add('d-none');
        showError('Failed to load categories. Please try again.');
    }
}

function displayCategories(categories) {
    const tbody = document.getElementById('categories-table-body');
    tbody.innerHTML = categories.map((category, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>
                <strong>${category.name}</strong>
            </td>
            <td><code>${category.slug}</code></td>
            <td>
                ${category.description ? category.description.substring(0, 50) + (category.description.length > 50 ? '...' : '') : ''}
            </td>
            <td>
                ${category.icon ? `<i class="${category.icon} fa-fw"></i> ${category.icon}` : ''}
            </td>
            <td>${category.sort_order || 0}</td>
            <td>
                <span class="badge bg-${category.is_active == 1 ? 'success' : 'secondary'}">
                    ${category.is_active == 1 ? 'Active' : 'Inactive'}
                </span>
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button onclick="viewCategory(${category.id})" class="btn btn-outline-info" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editCategory(${category.id})" class="btn btn-outline-primary" title="Edit Category">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="toggleCategoryStatus(${category.id}, ${category.is_active})" class="btn btn-outline-${category.is_active == 1 ? 'danger' : 'success'}" title="${category.is_active == 1 ? 'Deactivate' : 'Activate'}">
                        <i class="fas fa-${category.is_active == 1 ? 'ban' : 'check'}"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function searchCategories(query = '') {
    const filtered = allCategories.filter(category =>
        category.name.toLowerCase().includes(query.toLowerCase()) ||
        category.slug.toLowerCase().includes(query.toLowerCase()) ||
        (category.description && category.description.toLowerCase().includes(query.toLowerCase()))
    );
    displayCategories(filtered);
}

async function openCategoryModal(categoryId = null) {
    const modalContent = `
        <div class="modal fade" id="categoryModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i><span id="category-modal-title">Add Category</span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="categoryForm">
                            <input type="hidden" id="category-id">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category Name *</label>
                                    <input type="text" class="form-control" id="category-name" required placeholder="Enter category name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Slug *</label>
                                    <input type="text" class="form-control" id="category-slug" required placeholder="url-friendly-slug" pattern="[a-z0-9\-]+" title="Only lowercase letters, numbers, and hyphens allowed">
                                    <div class="form-text">Auto-generated from name if left empty</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="category-description" rows="3" placeholder="Describe this category..."></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Icon Class</label>
                                    <input type="text" class="form-control" id="category-icon" placeholder="fas fa-box" value="fas fa-box">
                                    <div class="form-text">FontAwesome icon class (optional)</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sort Order</label>
                                    <input type="number" class="form-control" id="category-sort-order" min="0" value="0">
                                    <div class="form-text">Categories are sorted by this order</div>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="category-active" checked>
                                <label class="form-check-label" for="category-active">
                                    Active Category
                                </label>
                            </div>
                            <div id="category-message"></div>
                        </form>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveCategoryBtn">Save Category</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('categoryModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalContent);

    // Auto-generate slug from name
    document.getElementById('category-name').addEventListener('input', (e) => {
        const slugField = document.getElementById('category-slug');
        if (!slugField.dataset.userEdited) {
            slugField.value = e.target.value.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-');
        }
    });

    document.getElementById('category-slug').addEventListener('input', (e) => {
        e.target.dataset.userEdited = true;
    });

    if (categoryId) {
        document.getElementById('category-modal-title').textContent = 'Edit Category';
        document.getElementById('saveCategoryBtn').textContent = 'Update Category';
        await loadCategoryForEdit(categoryId);
    } else {
        resetCategoryForm();
    }

    // Add event listener for save button
    document.getElementById('saveCategoryBtn').addEventListener('click', saveCategory);

    const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();

    // Clean up modal when hidden
    document.getElementById('categoryModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

function resetCategoryForm() {
    document.getElementById('category-id').value = '';
    document.getElementById('category-name').value = '';
    document.getElementById('category-slug').value = '';
    document.getElementById('category-description').value = '';
    document.getElementById('category-icon').value = 'fas fa-box';
    document.getElementById('category-sort-order').value = '0';
    document.getElementById('category-active').checked = true;
    document.getElementById('category-message').innerHTML = '';
    delete document.getElementById('category-slug').dataset.userEdited;
}

async function loadCategoryForEdit(id) {
    try {
        const category = allCategories.find(c => c.id == id);
        if (!category) {
            showError('Category not found');
            return;
        }

        document.getElementById('category-id').value = category.id;
        document.getElementById('category-name').value = category.name || '';
        document.getElementById('category-slug').value = category.slug || '';
        document.getElementById('category-description').value = category.description || '';
        document.getElementById('category-icon').value = category.icon || 'fas fa-box';
        document.getElementById('category-sort-order').value = category.sort_order || 0;
        document.getElementById('category-active').checked = category.is_active == 1;
        document.getElementById('category-slug').dataset.userEdited = true;
    } catch (error) {
        devLog('Error loading category:', error);
        showError('Failed to load category details');
    }
}

async function saveCategory() {
    const formData = new FormData();
    formData.append('name', document.getElementById('category-name').value);
    formData.append('slug', document.getElementById('category-slug').value);
    formData.append('description', document.getElementById('category-description').value);
    formData.append('icon', document.getElementById('category-icon').value);
    formData.append('sort_order', document.getElementById('category-sort-order').value);
    formData.append('is_active', document.getElementById('category-active').checked ? 1 : 0);

    const categoryId = document.getElementById('category-id').value;
    const isEdit = categoryId !== '';

    const url = isEdit ? `${API_BASE}/categories/update.php?id=${categoryId}` : `${API_BASE}/categories/create.php`;

    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            showSuccess(`Category ${isEdit ? 'updated' : 'created'} successfully`);
            bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
            await loadCategories();
        } else {
            showError(result.message || `Failed to ${isEdit ? 'update' : 'create'} category`);
        }
    } catch (error) {
        devLog('Error saving category:', error);
        showError(`Failed to ${isEdit ? 'update' : 'create'} category`);
    }
}

async function viewCategory(id) {
    const category = allCategories.find(c => c.id == id);
    if (!category) return;

    // For now, just edit the category (same modal)
    await openCategoryModal(id);
}

async function editCategory(id) {
    await openCategoryModal(id);
}

async function toggleCategoryStatus(id, currentStatus) {
    const action = currentStatus == 1 ? 'deactivate' : 'activate';
    const newStatus = currentStatus == 1 ? 0 : 1;

    if (!await showConfirm(`Are you sure you want to ${action} this category?`, {
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} Category`,
        confirmText: action.charAt(0).toUpperCase() + action.slice(1),
        type: currentStatus == 1 ? 'danger' : 'success',
        icon: currentStatus == 1 ? 'fas fa-ban' : 'fas fa-check'
    })) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('is_active', newStatus);

        const response = await fetch(`${API_BASE}/categories/update.php?id=${id}`, {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            showSuccess(`Category ${action}d successfully`);
            await loadCategories();
        } else {
            showError(result.message || `Failed to ${action} category`);
        }
    } catch (error) {
        devLog('Error toggling category status:', error);
        showError(`Failed to ${action} category`);
    }
}

async function loadStockAdjustmentsPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-exchange-alt me-2"></i>Stock Adjustments</h1>
            <p class="page-subtitle">Track and manage inventory stock level changes</p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex gap-3 flex-wrap">
                <select id="adjustment-type-filter" class="form-select">
                    <option value="all">All Types</option>
                    <option value="add">Stock In (Add)</option>
                    <option value="remove">Stock Out (Remove)</option>
                    <option value="recount">Stock Recount</option>
                </select>

                <select id="adjustment-reason-filter" class="form-select">
                    <option value="all">All Reasons</option>
                    <option value="purchase_received">Purchase Received</option>
                    <option value="sale">Sale</option>
                    <option value="return">Return</option>
                    <option value="damaged">Damaged/Lost</option>
                    <option value="counting_error">Counting Error</option>
                    <option value="transfer">Transfer</option>
                    <option value="other">Other</option>
                </select>

                <input type="date" id="date-from-filter" class="form-control" style="width: auto;">
                <input type="date" id="date-to-filter" class="form-control" style="width: auto;">

                <input type="text" id="adjustment-search" class="form-control" placeholder="Search products..." style="width: 200px;">
            </div>
            <button class="btn btn-primary" onclick="openStockAdjustmentModal()">
                <i class="fas fa-plus me-2"></i>New Adjustment
            </button>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Stock Adjustment History</h5>
                    <div id="adjustments-summary" class="text-muted small">
                        Loading...
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div id="adjustments-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading stock adjustments...</p>
                </div>

                <div id="adjustments-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Adjustment #</th>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Type</th>
                                    <th>Before</th>
                                    <th>Adjusted</th>
                                    <th>After</th>
                                    <th>Reason</th>
                                    <th>Performed By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="adjustments-table-body">
                                <!-- Adjustments will be loaded here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav id="adjustments-pagination" class="mt-4" style="display: none;">
                        <ul class="pagination justify-content-center">
                            <li class="page-item" id="prev-page">
                                <a class="page-link" href="#" onclick="changePage(currentPage - 1)">Previous</a>
                            </li>
                            <li class="page-item" id="next-page">
                                <a class="page-link" href="#" onclick="changePage(currentPage + 1)">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>

                <div id="no-adjustments-message" class="text-center py-5 d-none">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Stock Adjustments Found</h5>
                    <p class="text-muted">Start by making your first stock adjustment.</p>
                    <button class="btn btn-primary" onclick="openStockAdjustmentModal()">
                        <i class="fas fa-plus me-2"></i>Create First Adjustment
                    </button>
                </div>
            </div>
        </div>
    `;

    // Initialize filters with empty date range (show all by default)
    // Users can set specific date ranges if needed
    document.getElementById('date-to-filter').value = '';
    document.getElementById('date-from-filter').value = '';

    // Load products for later use
    await loadProductsForAdjustments();

    // Load adjustments
    currentPage = 1;
    await loadStockAdjustments();

    // Add event listeners
    document.getElementById('adjustment-type-filter').addEventListener('change', () => loadStockAdjustments());
    document.getElementById('adjustment-reason-filter').addEventListener('change', () => loadStockAdjustments());
    document.getElementById('date-from-filter').addEventListener('change', () => loadStockAdjustments());
    document.getElementById('date-to-filter').addEventListener('change', () => loadStockAdjustments());
    document.getElementById('adjustment-search').addEventListener('input', debounce(() => loadStockAdjustments(), 500));
}

let allProductsForAdjustments = [];
let loadedAdjustments = []; // Store loaded adjustments for viewing details
const itemsPerPage = 20;

async function loadProductsForAdjustments() {
    try {
        const response = await fetch(`${API_BASE}/products/index.php?status=all`);
        const data = await response.json();

        if (data.success && data.data.products) {
            allProductsForAdjustments = data.data.products;
        }
    } catch (error) {
        devLog('Error loading products for adjustments:', error);
    }
}

async function loadStockAdjustments(page = 1) {
    const loadingIndicator = document.getElementById('adjustments-loading');
    const content = document.getElementById('adjustments-content');
    const noAdjustmentsMessage = document.getElementById('no-adjustments-message');
    const tbody = document.getElementById('adjustments-table-body');
    const summary = document.getElementById('adjustments-summary');

    const type = document.getElementById('adjustment-type-filter').value;
    const reason = document.getElementById('adjustment-reason-filter').value;
    const dateFrom = document.getElementById('date-from-filter').value;
    const dateTo = document.getElementById('date-to-filter').value;
    const search = document.getElementById('adjustment-search').value.trim();

    try {
        loadingIndicator.classList.remove('d-none');
        content.classList.add('d-none');
        noAdjustmentsMessage.classList.add('d-none');

        let url = `${API_BASE}/inventory/adjustments.php?limit=${itemsPerPage}&offset=${(page - 1) * itemsPerPage}`;

        if (type !== 'all') url += `&type=${type}`;
        if (reason !== 'all') url += `&reason=${reason}`;
        if (dateFrom) url += `&date_from=${dateFrom}`;
        if (dateTo) url += `&date_to=${dateTo}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;

        const response = await fetch(url);
        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.data.adjustments && data.data.adjustments.length > 0) {
            displayStockAdjustments(data.data.adjustments);
            content.classList.remove('d-none');

            // Update summary
            if (summary) {
                summary.textContent = `Showing ${data.data.adjustments.length} adjustment${data.data.adjustments.length === 1 ? '' : 's'}`;
            }

            // Show pagination if needed
            const pagination = document.getElementById('adjustments-pagination');
            if (data.data.adjustments.length >= itemsPerPage) {
                pagination.style.display = 'block';
            } else {
                pagination.style.display = 'none';
            }
        } else {
            noAdjustmentsMessage.classList.remove('d-none');
            if (summary) summary.textContent = 'No adjustments found';
        }
    } catch (error) {
        devLog('Error loading stock adjustments:', error);
        loadingIndicator.classList.add('d-none');
        showError('Failed to load stock adjustments. Please try again.');
    }
}

function displayStockAdjustments(adjustments) {
    const tbody = document.getElementById('adjustments-table-body');

    // Store adjustments for later viewing
    loadedAdjustments = adjustments || [];

    if (!adjustments || adjustments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No stock adjustments found</td></tr>';
        return;
    }

    tbody.innerHTML = adjustments.map(adjustment => {
        const typeBadge = getAdjustmentTypeBadge(adjustment.adjustment_type);
        const reasonBadge = getAdjustmentReasonBadge(adjustment.reason);
        const adjustedQuantity = adjustment.adjustment_type === 'add' ? `+${adjustment.quantity_adjusted}` :
                                adjustment.adjustment_type === 'remove' ? `-${adjustment.quantity_adjusted}` :
                                `${adjustment.quantity_adjusted}`;

        return `
            <tr>
                <td>
                    <strong class="text-primary">${adjustment.adjustment_number}</strong>
                </td>
                <td>${formatDate(adjustment.adjustment_date)}</td>
                <td>
                    <div>
                        <strong>${adjustment.product?.name || 'Unknown Product'}</strong>
                        <br><small class="text-muted">${adjustment.product?.sku || ''}</small>
                    </div>
                </td>
                <td>${typeBadge}</td>
                <td>
                    <strong>${adjustment.quantity_before}</strong>
                </td>
                <td class="${adjustment.adjustment_type === 'add' ? 'text-success' : adjustment.adjustment_type === 'remove' ? 'text-danger' : ''}">
                    <strong>${adjustedQuantity}</strong>
                </td>
                <td>
                    <strong>${adjustment.quantity_after}</strong>
                </td>
                <td>${reasonBadge}</td>
                <td>
                    ${adjustment.performed_by_name || 'System'}
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button onclick="viewAdjustmentDetails(${adjustment.id})" class="btn btn-outline-info" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${adjustment.notes ? `<button onclick="viewAdjustmentNotes('${adjustment.notes}')" class="btn btn-outline-secondary" title="View Notes">
                            <i class="fas fa-sticky-note"></i>
                        </button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function getAdjustmentTypeBadge(type) {
    const badges = {
        'add': '<span class="badge bg-success"><i class="fas fa-plus me-1"></i>Add Stock</span>',
        'remove': '<span class="badge bg-danger"><i class="fas fa-minus me-1"></i>Remove Stock</span>',
        'recount': '<span class="badge bg-warning"><i class="fas fa-recycle me-1"></i>Recount</span>'
    };
    return badges[type] || '<span class="badge bg-secondary">Unknown</span>';
}

function getAdjustmentReasonBadge(reason) {
    const badges = {
        'purchase_received': '<span class="badge bg-primary">Purchase Received</span>',
        'sale': '<span class="badge bg-success">Sale</span>',
        'return': '<span class="badge bg-info">Return</span>',
        'damaged': '<span class="badge bg-danger">Damaged/Lost</span>',
        'counting_error': '<span class="badge bg-warning">Counting Error</span>',
        'transfer': '<span class="badge bg-secondary">Transfer</span>',
        'other': '<span class="badge bg-dark">Other</span>'
    };
    return badges[reason] || '<span class="badge bg-light text-dark">Other</span>';
}

function changePage(page) {
    currentPage = Math.max(1, page);
    loadStockAdjustments(currentPage);
}

async function openStockAdjustmentModal() {
    const modalContent = `
        <div class="modal fade" id="stockAdjustmentModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title"><i class="fas fa-exchange-alt me-2"></i>Create Stock Adjustment</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="stockAdjustmentForm">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Product Search *</label>
                                    <div class="position-relative">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="adjustment-product-search" placeholder="Search products by name or SKU..." autocomplete="off" onkeydown="handleProductSearchKeydown(event)">
                                            <button class="btn btn-outline-secondary" type="button" id="clear-product-search" style="display: none;" onclick="clearProductSearch()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <div class="product-search-results" id="adjustment-product-search-results" style="
                                            position: absolute;
                                            top: 100%;
                                            left: 0;
                                            right: 0;
                                            background: var(--bg-card);
                                            border: 1px solid var(--border-color);
                                            border-top: none;
                                            border-radius: 0 0 8px 8px;
                                            max-height: 300px;
                                            overflow-y: auto;
                                            z-index: 1050;
                                            display: none;
                                            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                                        "></div>
                                        <div class="product-search-loading" id="adjustment-product-search-loading" style="display: none;">
                                            <div class="text-center py-2">
                                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                                Loading products...
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="adjustment-product" required>
                                    <div class="form-text" id="product-search-help">Start typing to search products. Minimum 2 characters. Shows current stock levels.</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Adjustment Type *</label>
                                    <select class="form-select" id="adjustment-type" required>
                                        <option value="">Select type...</option>
                                        <option value="add">Add Stock (Stock In)</option>
                                        <option value="remove">Remove Stock (Stock Out)</option>
                                        <option value="recount">Recount (Set to specific quantity)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Reason *</label>
                                    <select class="form-select" id="adjustment-reason" required>
                                        <option value="">Select reason...</option>
                                        <option value="purchase_received">Purchase Received</option>
                                        <option value="sale">Sale</option>
                                        <option value="return">Customer Return</option>
                                        <option value="damaged">Damaged/Lost</option>
                                        <option value="counting_error">Counting Error</option>
                                        <option value="transfer">Stock Transfer</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" id="quantity-label">Quantity to Adjust *</label>
                                    <input type="number" class="form-control" id="adjustment-quantity" min="1" required>
                                    <div class="form-text" id="quantity-help">For add/remove: enter how much to add/remove. For recount: enter the new total stock.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Current Stock</label>
                                    <input type="number" class="form-control" id="current-stock-display" readonly disabled>
                                    <div class="form-text">Read-only: shows current stock for reference</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Stock After Adjustment</label>
                                    <input type="number" class="form-control" id="new-stock-display" readonly disabled>
                                    <div class="form-text">Calculated automatically based on adjustment</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Adjustment Preview</label>
                                    <input type="text" class="form-control" id="adjustment-preview" readonly disabled style="font-weight: bold;">
                                    <div class="form-text">Shows how the stock will change</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" id="adjustment-notes" rows="3" placeholder="Optional notes explaining the adjustment..."></textarea>
                            </div>

                            <div id="adjustment-message"></div>
                        </form>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="createAdjustmentBtn">
                            <i class="fas fa-save me-2"></i>Create Adjustment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('stockAdjustmentModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalContent);

    // Set up interactive behavior
    setupAdjustmentFormInteractivity();

    const modal = new bootstrap.Modal(document.getElementById('stockAdjustmentModal'));
    modal.show();

    // Clean up modal when hidden
    document.getElementById('stockAdjustmentModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

function setupAdjustmentFormInteractivity() {
    const productSearchInput = document.getElementById('adjustment-product-search');
    const searchResults = document.getElementById('adjustment-product-search-results');
    const productSelect = document.getElementById('adjustment-product');
    const typeSelect = document.getElementById('adjustment-type');
    const quantityInput = document.getElementById('adjustment-quantity');
    const currentStockDisplay = document.getElementById('current-stock-display');
    const newStockDisplay = document.getElementById('new-stock-display');
    const adjustmentPreview = document.getElementById('adjustment-preview');
    const quantityLabel = document.getElementById('quantity-label');
    const quantityHelp = document.getElementById('quantity-help');

    // Search input handling
    productSearchInput.addEventListener('input', function(e) {
        const query = e.target.value.trim();
        if (query.length >= 2) {
            searchProductsForAdjustment(query);
        } else {
            searchResults.style.display = 'none';
        }
    });

    productSearchInput.addEventListener('focus', function() {
        if (productSearchInput.value.trim().length >= 2) {
            searchProductsForAdjustment(productSearchInput.value.trim());
        }
    });

    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!productSearchInput.closest('.position-relative').contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Update labels and calculations when type changes
    typeSelect.addEventListener('change', updateAdjustmentCalculations);

    // Update when quantity changes
    quantityInput.addEventListener('input', updateAdjustmentCalculations);

    // Add save button handler
    document.getElementById('createAdjustmentBtn').addEventListener('click', createStockAdjustment);

    function updateAdjustmentCalculations() {
        const productId = productSelect.value;
        const adjustmentType = typeSelect.value;
        const quantity = parseInt(quantityInput.value) || 0;
        const currentStock = parseInt(currentStockDisplay.value) || 0;

        if (!adjustmentType || !productId) return;

        let newStock, preview, label, help;

        switch (adjustmentType) {
            case 'add':
                newStock = currentStock + quantity;
                preview = `+${quantity} (Was ${currentStock}, Will be ${newStock})`;
                label = 'Quantity to Add *';
                help = 'Enter how many units to add to current stock';
                break;
            case 'remove':
                newStock = Math.max(0, currentStock - quantity);
                preview = `-${quantity} (Was ${currentStock}, Will be ${newStock})`;
                label = 'Quantity to Remove *';
                help = 'Enter how many units to remove from current stock';
                break;
            case 'recount':
                newStock = quantity;
                preview = `Recount to ${quantity} (Was ${currentStock}, Will be ${newStock})`;
                label = 'New Total Stock *';
                help = 'Enter the correct total stock quantity after recounting';
                break;
        }

        quantityLabel.textContent = label;
        quantityHelp.textContent = help;
        newStockDisplay.value = newStock;
        adjustmentPreview.value = preview;
        adjustmentPreview.style.color = adjustmentType === 'add' ? 'green' : adjustmentType === 'remove' ? 'red' : 'blue';
    }

    async function searchProductsForAdjustment(query) {
        try {
            const response = await fetch(`${API_BASE}/products/index.php?search=${encodeURIComponent(query)}&limit=10&status=all`);
            const data = await response.json();

            if (data.success && data.products && data.products.length > 0) {
                displaySearchResults(data.products);
            } else {
                searchResults.innerHTML = '<div class="text-center text-muted py-2">No products found</div>';
                searchResults.style.display = 'block';
            }
        } catch (error) {
            console.error('Error searching products:', error);
            searchResults.innerHTML = '<div class="text-center text-muted py-2">Error searching products</div>';
            searchResults.style.display = 'block';
        }
    }

    function displaySearchResults(products) {
        searchResults.innerHTML = products.map(product => `
            <div class="product-search-item px-3 py-2 border-bottom" data-product-id="${product.id}" style="cursor: pointer; background: var(--bg-secondary);">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-bold text-truncate">${product.name}</div>
                        <small class="text-muted">
                            SKU: ${product.sku || 'N/A'} • Stock: ${product.quantity_on_hand || 0} • Price: ₱${product.selling_price || 0}
                        </small>
                    </div>
                    <div class="text-end">
                        <small class="badge bg-${(product.quantity_on_hand || 0) > 5 ? 'success' : 'warning'}">
                            ${product.quantity_on_hand || 0} in stock
                        </small>
                    </div>
                </div>
            </div>
        `).join('');

        searchResults.style.display = 'block';

        // Add click handlers to search results
        searchResults.querySelectorAll('.product-search-item').forEach(item => {
            item.addEventListener('click', function() {
                const productId = this.dataset.productId;
                const product = products.find(p => p.id == productId);

                if (product) {
                    // Update hidden product field
                    productSelect.value = productId;

                    // Update search input to show selected product name
                    productSearchInput.value = product.name;

                    // Update current stock display
                    currentStockDisplay.value = product.quantity_on_hand || 0;

                    // Hide search results
                    searchResults.style.display = 'none';

                    // Update calculations
                    updateAdjustmentCalculations();
                }
            });

            // Hover effects
            item.addEventListener('mouseenter', function() {
                this.style.background = 'var(--accent-light, rgba(0, 245, 255, 0.1))';
            });
            item.addEventListener('mouseleave', function() {
                this.style.background = 'var(--bg-secondary)';
            });
        });
    }
}

async function createStockAdjustment() {
    const productSelect = document.getElementById('adjustment-product');
    const typeSelect = document.getElementById('adjustment-type');
    const reasonSelect = document.getElementById('adjustment-reason');
    const quantityInput = document.getElementById('adjustment-quantity');
    const notesTextarea = document.getElementById('adjustment-notes');

    // Validate form
    if (!productSelect.value || !typeSelect.value || !reasonSelect.value || !quantityInput.value) {
        showError('Please fill in all required fields');
        return;
    }

    const adjustmentData = {
        product_id: parseInt(productSelect.value),
        adjustment_type: typeSelect.value,
        quantity_adjusted: parseInt(quantityInput.value),
        reason: reasonSelect.value,
        notes: notesTextarea.value.trim() || null
    };

    try {
        const response = await fetch(`${API_BASE}/inventory/adjustments.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(adjustmentData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Stock adjustment created successfully');

            // Close modal and wait for it to fully close before reloading
            const modal = bootstrap.Modal.getInstance(document.getElementById('stockAdjustmentModal'));
            modal.hide();

            // Wait for modal to be completely hidden before reloading data
            document.getElementById('stockAdjustmentModal').addEventListener('hidden.bs.modal', async function onModalHidden() {
                // Remove the event listener to avoid multiple calls
                document.getElementById('stockAdjustmentModal').removeEventListener('hidden.bs.modal', onModalHidden);

                // Small delay to ensure smooth UI transition, then reload adjustments
                setTimeout(async () => {
                    await loadStockAdjustments();
                }, 150);
            });

        } else {
            showError(result.message || 'Failed to create stock adjustment');
        }
    } catch (error) {
        devLog('Error creating stock adjustment:', error);
        showError('Failed to create stock adjustment');
    }
}

async function viewAdjustmentDetails(adjustmentId) {
    // Find the adjustment from loaded data (use == to handle type coercion)
    const adjustment = loadedAdjustments.find(adj => adj.id == adjustmentId);

    if (!adjustment) {
        showError(`Adjustment #${adjustmentId} not found. Please refresh the page and try again.`);
        return;
    }

    // Create and show modal
    const modalHtml = `
        <div class="modal fade" id="adjustmentDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title">
                            <i class="fas fa-info-circle me-2"></i>Stock Adjustment Details
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Adjustment Number & Type -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="detail-card p-3" style="background: var(--bg-tertiary); border-radius: 8px;">
                                    <label class="text-muted small mb-1">Adjustment Number</label>
                                    <h6 class="mb-0 text-primary fw-bold">${adjustment.adjustment_number}</h6>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-card p-3" style="background: var(--bg-tertiary); border-radius: 8px;">
                                    <label class="text-muted small mb-1">Type</label>
                                    <div>${getAdjustmentTypeBadge(adjustment.adjustment_type)}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Information -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3" style="border-color: var(--border-color) !important;">
                                <i class="fas fa-box me-2"></i>Product Information
                            </h6>
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="text-muted small">Product Name</label>
                                    <p class="fw-bold mb-2">${adjustment.product?.name || 'Unknown Product'}</p>
                                </div>
                                <div class="col-md-4">
                                    <label class="text-muted small">SKU</label>
                                    <p class="mb-2"><code>${adjustment.product?.sku || 'N/A'}</code></p>
                                </div>
                            </div>
                        </div>

                        <!-- Quantity Changes -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3" style="border-color: var(--border-color) !important;">
                                <i class="fas fa-chart-line me-2"></i>Quantity Changes
                            </h6>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="p-3" style="background: var(--bg-tertiary); border-radius: 8px;">
                                        <label class="text-muted small d-block mb-1">Before</label>
                                        <h4 class="mb-0">${adjustment.quantity_before}</h4>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3" style="background: ${adjustment.adjustment_type === 'add' ? 'rgba(25, 135, 84, 0.1)' : adjustment.adjustment_type === 'remove' ? 'rgba(220, 53, 69, 0.1)' : 'rgba(255, 193, 7, 0.1)'}; border-radius: 8px;">
                                        <label class="text-muted small d-block mb-1">Adjusted</label>
                                        <h4 class="mb-0 ${adjustment.adjustment_type === 'add' ? 'text-success' : adjustment.adjustment_type === 'remove' ? 'text-danger' : 'text-warning'}">
                                            ${adjustment.adjustment_type === 'add' ? '+' : adjustment.adjustment_type === 'remove' ? '-' : ''}${adjustment.quantity_adjusted}
                                        </h4>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3" style="background: var(--bg-tertiary); border-radius: 8px;">
                                        <label class="text-muted small d-block mb-1">After</label>
                                        <h4 class="mb-0 text-primary">${adjustment.quantity_after}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reason & Details -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3" style="border-color: var(--border-color) !important;">
                                <i class="fas fa-clipboard-list me-2"></i>Adjustment Details
                            </h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">Reason</label>
                                    <div>${getAdjustmentReasonBadge(adjustment.reason)}</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">Date & Time</label>
                                    <p class="mb-0">${formatDate(adjustment.adjustment_date)}</p>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="text-muted small">Performed By</label>
                                    <p class="mb-0"><i class="fas fa-user me-2"></i>${adjustment.performed_by_name || 'System'}</p>
                                </div>
                                ${adjustment.notes ? `
                                <div class="col-md-12">
                                    <label class="text-muted small">Notes</label>
                                    <div class="p-3" style="background: var(--bg-tertiary); border-radius: 8px; border-left: 3px solid var(--accent);">
                                        <p class="mb-0">${adjustment.notes}</p>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>

                        <!-- Impact Summary -->
                        <div class="alert ${adjustment.adjustment_type === 'add' ? 'alert-success' : adjustment.adjustment_type === 'remove' ? 'alert-danger' : 'alert-warning'} mb-0">
                            <i class="fas ${adjustment.adjustment_type === 'add' ? 'fa-arrow-up' : adjustment.adjustment_type === 'remove' ? 'fa-arrow-down' : 'fa-sync-alt'} me-2"></i>
                            <strong>Impact:</strong> This adjustment ${adjustment.adjustment_type === 'add' ? 'increased' : adjustment.adjustment_type === 'remove' ? 'decreased' : 'recounted'}
                            the stock level ${adjustment.adjustment_type === 'add' ? 'by' : adjustment.adjustment_type === 'remove' ? 'by' : 'to'}
                            <strong>${Math.abs(adjustment.quantity_adjusted)}</strong> unit${Math.abs(adjustment.quantity_adjusted) !== 1 ? 's' : ''}
                            ${adjustment.adjustment_type === 'recount' ? ', resulting in a net change of <strong>' + (adjustment.quantity_after - adjustment.quantity_before) + '</strong> units' : ''}.
                        </div>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove any existing modal
    const existingModal = document.getElementById('adjustmentDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('adjustmentDetailsModal'));
    modal.show();

    // Clean up on close
    document.getElementById('adjustmentDetailsModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

function viewAdjustmentNotes(notes) {
    // Create a simple modal to display notes
    const modalHtml = `
        <div class="modal fade" id="notesModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title">
                            <i class="fas fa-sticky-note me-2"></i>Adjustment Notes
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="p-3" style="background: var(--bg-tertiary); border-radius: 8px; border-left: 3px solid var(--accent);">
                            <p class="mb-0" style="white-space: pre-wrap;">${notes || 'No notes available'}</p>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove any existing modal
    const existingModal = document.getElementById('notesModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('notesModal'));
    modal.show();

    // Clean up on close
    document.getElementById('notesModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Utility function for debouncing
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

async function loadSuppliersPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-truck me-2"></i>Suppliers</h1>
            <p class="page-subtitle">Manage supplier accounts and track performance</p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex gap-3 flex-wrap">
                <input type="text" id="supplier-search" class="form-control" placeholder="Search suppliers..." style="width: 200px;">
                <select id="supplier-status-filter" class="form-select" style="width: 200px;">
                    <option value="active">Active</option>
                    <option value="all">All Status</option>
                    <option value="inactive">Inactive</option>
                    <option value="pending_approval">Pending Approval</option>
                </select>
                <div class="form-check d-flex align-items-center ms-2">
                    <input class="form-check-input" type="checkbox" id="include-performance-data" checked>
                    <label class="form-check-label ms-2" for="include-performance-data">
                        Show Performance Data
                    </label>
                </div>
            </div>
            <button class="btn btn-primary" onclick="openSupplierModal()">
                <i class="fas fa-plus me-2"></i>Add Supplier
            </button>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-truck me-2"></i>Supplier Directory</h5>
                    <div id="suppliers-summary" class="text-muted small">
                        Loading...
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div id="suppliers-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading suppliers...</p>
                </div>

                <div id="suppliers-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Company Name</th>
                                    <th>Contact Person</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>City/Country</th>
                                    <th>Payment Terms</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="suppliers-table-body">
                                <!-- Suppliers will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-suppliers-message" class="text-center py-5 d-none">
                    <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Suppliers Found</h5>
                    <p class="text-muted">Start by adding your first supplier.</p>
                    <button class="btn btn-primary" onclick="openSupplierModal()">
                        <i class="fas fa-plus me-2"></i>Add First Supplier
                    </button>
                </div>
            </div>
        </div>
    `;

    // Load suppliers
    await loadSuppliers();

    // Add event listeners
    document.getElementById('supplier-status-filter').addEventListener('change', () => loadSuppliers());
    document.getElementById('supplier-search').addEventListener('input', debounce(() => loadSuppliers(), 500));
    document.getElementById('include-performance-data').addEventListener('change', () => loadSuppliers());
}

let allSuppliers = [];

async function loadSuppliers() {
    const loadingIndicator = document.getElementById('suppliers-loading');
    const content = document.getElementById('suppliers-content');
    const noSuppliersMessage = document.getElementById('no-suppliers-message');
    const tbody = document.getElementById('suppliers-table-body');
    const summary = document.getElementById('suppliers-summary');

    const status = document.getElementById('supplier-status-filter').value;
    const search = document.getElementById('supplier-search').value.trim();
    const includePerformance = document.getElementById('include-performance-data').checked;

    try {
        loadingIndicator.classList.remove('d-none');
        content.classList.add('d-none');
        noSuppliersMessage.classList.add('d-none');

        // Build URL with parameters - fetch all suppliers if not filtering by active only
        let url = `${API_BASE}/suppliers/index.php`;
        if (status !== 'active') {
            url += '?active_only=false';
        }

        const response = await fetch(url);
        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.data.suppliers && data.data.suppliers.length > 0) {
            allSuppliers = data.data.suppliers;
            // Use the selected status filter
            displaySuppliers(allSuppliers, status, search, includePerformance);
            content.classList.remove('d-none');

            // Update summary based on current filter
            if (summary) {
                const filteredCount = status === 'all'
                    ? allSuppliers.length
                    : allSuppliers.filter(s => {
                        if (status === 'active') return s.is_active == 1;
                        if (status === 'inactive') return s.is_active == 0;
                        if (status === 'pending_approval') return s.supplier_status === 'pending_approval';
                        return true;
                    }).length;
                summary.textContent = `${filteredCount} supplier${filteredCount === 1 ? '' : 's'} (${status})`;
            }
        } else {
            noSuppliersMessage.classList.remove('d-none');
            if (summary) summary.textContent = 'No suppliers found';
        }
    } catch (error) {
        devLog('Error loading suppliers:', error);
        loadingIndicator.classList.add('d-none');
        showError('Failed to load suppliers. Please try again.');
    }
}

function displaySuppliers(suppliers, statusFilter, searchFilter, includePerformance) {
    const tbody = document.getElementById('suppliers-table-body');
    const filtered = suppliers.filter(supplier => {
        // Status filter
        if (statusFilter !== 'all') {
            if (statusFilter === 'active' && !supplier.is_active) return false;
            if (statusFilter === 'inactive' && supplier.is_active) return false;
            if (statusFilter === 'pending_approval' && supplier.supplier_status !== 'pending_approval') return false;
        }

        // Search filter
        if (searchFilter) {
            const searchLower = searchFilter.toLowerCase();
            if (!(supplier.name?.toLowerCase().includes(searchLower) ||
                  supplier.email?.toLowerCase().includes(searchLower) ||
                  supplier.username?.toLowerCase().includes(searchLower))) {
                return false;
            }
        }

        return true;
    });

    tbody.innerHTML = filtered.map(supplier => `
        <tr>
            <td>
                <strong class="text-primary">${supplier.supplier_code || supplier.code || '—'}</strong>
            </td>
            <td>
                <strong>${supplier.company_name || '—'}</strong>
            </td>
            <td>
                ${supplier.contact_person || '—'}
            </td>
            <td>
                <a href="mailto:${supplier.supplier_email || supplier.email}">${supplier.supplier_email || supplier.email}</a>
            </td>
            <td>${supplier.phone || '—'}</td>
            <td>
                <div>
                    ${supplier.city && supplier.country ? `${supplier.city}, ${supplier.country}` : supplier.city || supplier.country || '—'}
                </div>
            </td>
            <td>
                <span class="badge bg-info">${supplier.payment_terms || 'Net 30'}</span>
            </td>
            <td>${getSupplierStatusBadge(supplier)}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button onclick="viewSupplier(${supplier.id})" class="btn btn-outline-info" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="toggleSupplierStatus(${supplier.id}, ${supplier.is_active})" class="btn btn-outline-${supplier.is_active ? 'warning' : 'success'}" title="${supplier.is_active ? 'Deactivate' : 'Activate'}">
                        <i class="fas fa-${supplier.is_active ? 'ban' : 'check'}"></i>
                    </button>
                    <button onclick="deleteSupplier(${supplier.id})" class="btn btn-outline-danger" title="Delete Supplier">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getSupplierStatusBadge(supplier) {
    const status = supplier.supplier_status || (supplier.is_active ? 'approved' : 'inactive');

    switch (status) {
        case 'approved':
            return '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Approved</span>';
        case 'pending_approval':
            return '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Pending</span>';
        case 'rejected':
            return '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Rejected</span>';
        default:
            return '<span class="badge bg-secondary">Inactive</span>';
    }
}

function formatPerformanceData(supplier) {
    // Get actual order count from supplier statistics if available
    const mockOrders = Math.floor(Math.random() * 50) + 1;
    return `<span class="badge bg-info">${mockOrders} orders</span>`;
}

function formatLastOrder(lastOrderDate) {
    if (!lastOrderDate) return '—';
    const date = new Date(lastOrderDate);
    const now = new Date();
    const daysDiff = Math.floor((now - date) / (1000 * 60 * 60 * 24));

    if (daysDiff === 0) return 'Today';
    if (daysDiff === 1) return 'Yesterday';
    if (daysDiff < 7) return `${daysDiff} days ago`;
    if (daysDiff < 30) return `${Math.floor(daysDiff / 7)} weeks ago`;
    return `${Math.floor(daysDiff / 30)} months ago`;
}

async function openSupplierModal(supplierId = null) {
    const modalContent = `
        <div class="modal fade" id="supplierModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title"><i class="fas fa-truck me-2"></i><span id="supplier-modal-title">Add Supplier</span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="supplierForm">
                            <input type="hidden" id="supplier-id">

                            <!-- User Information Section -->
                            <h6 class="mb-3 text-primary"><i class="fas fa-user me-2"></i>User Information</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="supplier-full-name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="supplier-username" required>
                                    <div class="form-text">Unique username for login</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="supplier-email" required>
                                    <div class="form-text">Used for login and notifications</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="supplier-phone">
                                </div>
                            </div>
                            <div class="row" id="password-fields" style="display: none;">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="supplier-password" required minlength="6">
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="supplier-confirm-password" required minlength="6">
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Supplier Information Section -->
                            <h6 class="mb-3 text-primary"><i class="fas fa-building me-2"></i>Supplier Information</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" id="supplier-company-name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Supplier Code</label>
                                    <input type="text" class="form-control" id="supplier-code" readonly>
                                    <div class="form-text">Auto-generated</div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" id="supplier-contact-person">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Supplier Email</label>
                                    <input type="email" class="form-control" id="supplier-supplier-email">
                                    <div class="form-text">Business email (if different from login)</div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Location Information Section -->
                            <h6 class="mb-3 text-primary"><i class="fas fa-map-marker-alt me-2"></i>Location Information</h6>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" id="supplier-address">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" id="supplier-city">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">State/Province</label>
                                    <input type="text" class="form-control" id="supplier-state">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="supplier-postal-code">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" class="form-control" id="supplier-country" value="Philippines">
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Business Information Section -->
                            <h6 class="mb-3 text-primary"><i class="fas fa-briefcase me-2"></i>Business Information</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tax ID</label>
                                    <input type="text" class="form-control" id="supplier-tax-id">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Payment Terms</label>
                                    <select class="form-select" id="supplier-payment-terms">
                                        <option value="Net 30">Net 30</option>
                                        <option value="Net 60">Net 60</option>
                                        <option value="Net 90">Net 90</option>
                                        <option value="COD">Cash on Delivery (COD)</option>
                                        <option value="Prepaid">Prepaid</option>
                                        <option value="Custom">Custom</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" id="supplier-notes" rows="3"></textarea>
                            </div>

                            <hr class="my-4">

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="supplier-active">
                                <label class="form-check-label" for="supplier-active">
                                    Active Supplier Account
                                </label>
                                <div class="form-text">Inactive suppliers cannot log in or access their dashboard</div>
                            </div>
                            <div id="supplier-message"></div>
                        </form>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveSupplierBtn">
                            <i class="fas fa-save me-2"></i>Save Supplier
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('supplierModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalContent);

    if (supplierId) {
        document.getElementById('supplier-modal-title').textContent = 'Edit Supplier';
        document.getElementById('saveSupplierBtn').textContent = 'Update Supplier';
        await loadSupplierForEdit(supplierId);
    } else {
        resetSupplierForm();
        // Show password fields for new suppliers
        document.getElementById('password-fields').style.display = 'block';
    }

    // Add event listener for save button
    document.getElementById('saveSupplierBtn').addEventListener('click', () => saveSupplier(supplierId));

    const modal = new bootstrap.Modal(document.getElementById('supplierModal'));
    modal.show();

    // Clean up modal when hidden
    document.getElementById('supplierModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

function resetSupplierForm() {
    // User Information
    document.getElementById('supplier-id').value = '';
    document.getElementById('supplier-full-name').value = '';
    document.getElementById('supplier-username').value = '';
    document.getElementById('supplier-email').value = '';
    document.getElementById('supplier-phone').value = '';
    document.getElementById('supplier-password').value = '';
    document.getElementById('supplier-confirm-password').value = '';
    document.getElementById('supplier-active').checked = true;

    // Supplier Information
    document.getElementById('supplier-company-name').value = '';
    document.getElementById('supplier-code').value = '';
    document.getElementById('supplier-contact-person').value = '';
    document.getElementById('supplier-supplier-email').value = '';

    // Location Information
    document.getElementById('supplier-address').value = '';
    document.getElementById('supplier-city').value = '';
    document.getElementById('supplier-state').value = '';
    document.getElementById('supplier-postal-code').value = '';
    document.getElementById('supplier-country').value = 'Philippines';

    // Business Information
    document.getElementById('supplier-tax-id').value = '';
    document.getElementById('supplier-payment-terms').value = 'Net 30';
    document.getElementById('supplier-notes').value = '';

    document.getElementById('supplier-message').innerHTML = '';
}

async function loadSupplierForEdit(id) {
    try {
        const supplier = allSuppliers.find(s => s.id == id);
        if (!supplier) {
            showError('Supplier not found');
            return;
        }

        // User Information
        document.getElementById('supplier-id').value = supplier.id;
        document.getElementById('supplier-full-name').value = supplier.name || '';
        document.getElementById('supplier-username').value = supplier.username || '';
        document.getElementById('supplier-email').value = supplier.email || '';
        document.getElementById('supplier-phone').value = (supplier.phone && supplier.phone !== 'N/A') ? supplier.phone : '';
        document.getElementById('supplier-active').checked = supplier.is_active == 1;
        document.getElementById('supplier-password').value = '';
        document.getElementById('supplier-confirm-password').value = '';

        // Supplier Information
        document.getElementById('supplier-company-name').value = supplier.company_name || '';
        document.getElementById('supplier-code').value = supplier.supplier_code || '';
        document.getElementById('supplier-contact-person').value = supplier.contact_person || '';
        document.getElementById('supplier-supplier-email').value = supplier.supplier_email || '';

        // Location Information
        document.getElementById('supplier-address').value = supplier.address || '';
        document.getElementById('supplier-city').value = supplier.city || '';
        document.getElementById('supplier-state').value = supplier.state || '';
        document.getElementById('supplier-postal-code').value = supplier.postal_code || '';
        document.getElementById('supplier-country').value = supplier.country || 'Philippines';

        // Business Information
        document.getElementById('supplier-tax-id').value = supplier.tax_id || '';
        document.getElementById('supplier-payment-terms').value = supplier.payment_terms || 'Net 30';
        document.getElementById('supplier-notes').value = supplier.notes || '';

        // Hide password fields for editing
        document.getElementById('password-fields').style.display = 'none';
        document.getElementById('supplier-password').required = false;
        document.getElementById('supplier-confirm-password').required = false;
    } catch (error) {
        devLog('Error loading supplier:', error);
        showError('Failed to load supplier details');
    }
}

async function saveSupplier(supplierId) {
    const formData = {
        id: document.getElementById('supplier-id').value || null,
        // User Information
        full_name: document.getElementById('supplier-full-name').value.trim(),
        username: document.getElementById('supplier-username').value.trim(),
        email: document.getElementById('supplier-email').value.trim(),
        phone: document.getElementById('supplier-phone').value.trim(),
        is_active: document.getElementById('supplier-active').checked ? 1 : 0,
        role: 'supplier',

        // Supplier Information
        company_name: document.getElementById('supplier-company-name').value.trim(),
        supplier_code: document.getElementById('supplier-code').value.trim(),
        contact_person: document.getElementById('supplier-contact-person').value.trim(),
        supplier_email: document.getElementById('supplier-supplier-email').value.trim(),

        // Location Information
        address: document.getElementById('supplier-address').value.trim(),
        city: document.getElementById('supplier-city').value.trim(),
        state: document.getElementById('supplier-state').value.trim(),
        postal_code: document.getElementById('supplier-postal-code').value.trim(),
        country: document.getElementById('supplier-country').value.trim(),

        // Business Information
        tax_id: document.getElementById('supplier-tax-id').value.trim(),
        payment_terms: document.getElementById('supplier-payment-terms').value,
        notes: document.getElementById('supplier-notes').value.trim()
    };

    const password = document.getElementById('supplier-password').value;
    const confirmPassword = document.getElementById('supplier-confirm-password').value;

    // Validate password for new suppliers
    if (!supplierId || (supplierId && password)) {
        if (password.length < 6) {
            showError('Password must be at least 6 characters long');
            return;
        }
        if (password !== confirmPassword) {
            showError('Passwords do not match');
            return;
        }
        if (password) {
            formData.password = password;
        }
    }

    // Validate required fields
    if (!formData.full_name || !formData.username || !formData.email || !formData.company_name) {
        showError('Please fill in all required fields (Full Name, Username, Email, Company Name)');
        return;
    }

    const isEdit = supplierId !== null;
    const url = isEdit ? `${API_BASE}/suppliers/update.php` : `${API_BASE}/suppliers/create.php`;

    try {
        // For create, we need to use the register endpoint that creates supplier account
        if (!isEdit) {
            const registerFormData = {
                full_name: formData.full_name,
                username: formData.username,
                email: formData.email,
                phone: formData.phone,
                password: formData.password,
                company: formData.company_name,

                // Supplier-specific fields
                company_name: formData.company_name,
                contact_person: formData.contact_person,
                supplier_email: formData.supplier_email,
                address: formData.address,
                city: formData.city,
                state: formData.state,
                postal_code: formData.postal_code,
                country: formData.country,
                tax_id: formData.tax_id,
                payment_terms: formData.payment_terms,
                notes: formData.notes
            };

            const response = await fetch(`${API_BASE}/suppliers/register.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(registerFormData)
            });

            const result = await response.json();
            if (result.success) {
                showSuccess('Supplier registered successfully');
                bootstrap.Modal.getInstance(document.getElementById('supplierModal')).hide();
                await loadSuppliers();
            } else {
                showError(result.message || 'Failed to register supplier');
            }
        } else {
            // For updates, use the update endpoint
            const response = await fetch(`${API_BASE}/suppliers/update.php?id=${supplierId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const result = await response.json();
            if (result.success) {
                showSuccess('Supplier updated successfully');
                bootstrap.Modal.getInstance(document.getElementById('supplierModal')).hide();
                await loadSuppliers();
            } else {
                showError(result.message || 'Failed to update supplier');
            }
        }
    } catch (error) {
        devLog('Error saving supplier:', error);
        showError('Failed to save supplier');
    }
}

async function viewSupplier(id) {
    try {
        const supplier = allSuppliers.find(s => s.id == id);
        if (!supplier) return;

        // Show detailed supplier information (for now, just open edit modal)
        await openSupplierModal(id);
    } catch (error) {
        showError('Failed to load supplier details');
    }
}

async function editSupplier(id) {
    await openSupplierModal(id);
}

async function toggleSupplierStatus(id, currentStatus) {
    const isDeactivating = currentStatus == 1;
    const action = isDeactivating ? 'deactivate' : 'activate';
    const newStatus = isDeactivating ? 0 : 1;

    if (!await showConfirm(
        isDeactivating
            ? `Are you sure you want to deactivate this supplier?`
            : `Are you sure you want to activate this supplier?`,
        {
            title: isDeactivating ? 'Deactivate Supplier' : 'Activate Supplier',
            confirmText: isDeactivating ? 'Deactivate' : 'Activate',
            type: isDeactivating ? 'warning' : 'success',
            icon: isDeactivating ? 'fas fa-ban' : 'fas fa-check'
        }
    )) {
        return;
    }

    try {
        const formData = { is_active: newStatus };
        const response = await fetch(`${API_BASE}/suppliers/update.php?id=${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess(`Supplier ${action}d successfully`);
            await loadSuppliers();
        } else {
            showError(result.message || `Failed to ${action} supplier`);
        }
    } catch (error) {
        devLog('Error toggling supplier status:', error);
        showError(`Failed to ${action} supplier`);
    }
}

async function deleteSupplier(id) {
    if (!await showConfirm(
        'Are you sure you want to permanently delete this supplier? This action cannot be undone.',
        {
            title: 'Delete Supplier',
            confirmText: 'Delete Permanently',
            type: 'danger',
            icon: 'fas fa-trash'
        }
    )) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/suppliers/delete.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Supplier deleted permanently');
            await loadSuppliers();
        } else {
            showError(result.message || 'Failed to delete supplier');
        }
    } catch (error) {
        devLog('Error deleting supplier:', error);
        showError('Failed to delete supplier');
    }
}

async function approveSupplier(id) {
    if (!await showConfirm('Are you sure you want to approve this supplier?', {
        title: 'Approve Supplier',
        confirmText: 'Approve',
        type: 'success',
        icon: 'fas fa-check'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/suppliers/approve.php?id=${id}`, {
            method: 'POST'
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Supplier approved successfully');
            await loadSuppliers();
        } else {
            showError(result.message || 'Failed to approve supplier');
        }
    } catch (error) {
        showError('Failed to approve supplier');
    }
}

async function rejectSupplier(id) {
    const reason = await showPrompt('Please provide a reason for rejecting this supplier:', {
        title: 'Reject Supplier',
        required: true
    });

    if (!reason) return;

    try {
        const response = await fetch(`${API_BASE}/suppliers/reject.php?id=${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reason })
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Supplier rejected successfully');
            await loadSuppliers();
        } else {
            showError(result.message || 'Failed to reject supplier');
        }
    } catch (error) {
        showError('Failed to reject supplier');
    }
}

async function loadPurchaseOrdersPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-file-invoice me-2"></i>Purchase Orders</h1>
            <p class="page-subtitle">Manage supplier purchase orders and procurement</p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex gap-3 flex-wrap">
                <select id="po-status-filter" class="form-select">
                    <option value="all">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="partially_received">Partially Received</option>
                    <option value="completed">Completed</option>
                </select>

                <select id="po-supplier-filter" class="form-select">
                    <option value="all">All Suppliers</option>
                </select>

                <input type="text" id="po-search" class="form-control" placeholder="Search by PO number..." style="width: 200px;">
            </div>
            <button class="btn btn-primary" onclick="openPurchaseOrderModal()">
                <i class="fas fa-plus me-2"></i>Create PO
            </button>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Purchase Orders</h5>
                    <div id="po-summary" class="text-muted small">
                        Loading...
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div id="po-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading purchase orders...</p>
                </div>

                <div id="po-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>PO Number</th>
                                    <th>Order Date</th>
                                    <th>Expected Delivery</th>
                                    <th>Supplier</th>
                                    <th>Items</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="po-table-body">
                                <!-- Purchase orders will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-po-message" class="text-center py-5 d-none">
                    <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Purchase Orders Found</h5>
                    <p class="text-muted">Start by creating your first purchase order.</p>
                    <button class="btn btn-primary" onclick="openPurchaseOrderModal()">
                        <i class="fas fa-plus me-2"></i>Create First PO
                    </button>
                </div>
            </div>
        </div>
    `;

    // Load suppliers for filter dropdown
    await loadSuppliersForPOFilter();

    // Load purchase orders
    await loadPurchaseOrders();

    // Add event listeners
    document.getElementById('po-status-filter').addEventListener('change', () => loadPurchaseOrders());
    document.getElementById('po-supplier-filter').addEventListener('change', () => loadPurchaseOrders());
    document.getElementById('po-search').addEventListener('input', debounce(() => loadPurchaseOrders(), 500));
}

let allPurchaseOrders = [];
let allSuppliersForPO = [];

async function loadSuppliersForPOFilter() {
    try {
        const response = await fetch(`${API_BASE}/suppliers/index.php`);
        const data = await response.json();

        if (data.success && data.data.suppliers) {
            allSuppliersForPO = data.data.suppliers;
            const select = document.getElementById('po-supplier-filter');
            select.innerHTML = '<option value="all">All Suppliers</option>';

            allSuppliersForPO.forEach(supplier => {
                const option = document.createElement('option');
                option.value = supplier.id;
                option.textContent = supplier.name;
                select.appendChild(option);
            });
        }
    } catch (error) {
        devLog('Error loading suppliers for PO filter:', error);
    }
}

async function loadPurchaseOrders() {
    const loadingIndicator = document.getElementById('po-loading');
    const content = document.getElementById('po-content');
    const noPOMessage = document.getElementById('no-po-message');
    const tbody = document.getElementById('po-table-body');
    const summary = document.getElementById('po-summary');

    const statusFilter = document.getElementById('po-status-filter').value;
    const supplierFilter = document.getElementById('po-supplier-filter').value;
    const searchFilter = document.getElementById('po-search').value.trim();

    try {
        loadingIndicator.classList.remove('d-none');
        content.classList.add('d-none');
        noPOMessage.classList.add('d-none');

        let url = `${API_BASE}/purchase_orders/index.php`;
        const params = [];

        if (statusFilter !== 'all') params.push(`status=${encodeURIComponent(statusFilter)}`);
        if (supplierFilter !== 'all') params.push(`supplier_id=${supplierFilter}`);

        if (params.length > 0) url += '?' + params.join('&');

        const response = await fetch(url);
        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        // Check both data.purchase_orders (old format) and data.data.purchase_orders (new format)
        const purchaseOrders = data.data?.purchase_orders || data.purchase_orders || [];

        if (data.success && purchaseOrders.length > 0) {
            allPurchaseOrders = purchaseOrders;

            // Apply client-side filtering
            let filtered = allPurchaseOrders;
            if (searchFilter) {
                filtered = filtered.filter(po =>
                    po.po_number?.toLowerCase().includes(searchFilter.toLowerCase())
                );
            }

            displayPurchaseOrders(filtered);
            content.classList.remove('d-none');

            // Update summary
            if (summary) {
                const approved = allPurchaseOrders.filter(po => po.status === 'approved').length;
                const pending = allPurchaseOrders.filter(po => po.status === 'pending' || po.status === 'pending_supplier').length;
                const completed = allPurchaseOrders.filter(po => po.status === 'completed' || po.status === 'delivered' || po.status === 'received').length;
                summary.textContent = `${allPurchaseOrders.length} PO${allPurchaseOrders.length === 1 ? '' : 's'} • ${approved} approved • ${pending} pending • ${completed} completed`;
            }
        } else {
            noPOMessage.classList.remove('d-none');
            if (summary) summary.textContent = 'No purchase orders found';
        }
    } catch (error) {
        devLog('Error loading purchase orders:', error);
        loadingIndicator.classList.add('d-none');
        showError('Failed to load purchase orders. Please try again.');
    }
}

function displayPurchaseOrders(purchaseOrders) {
    const tbody = document.getElementById('po-table-body');
    tbody.innerHTML = purchaseOrders.map(po => `
        <tr>
            <td>
                <strong class="text-primary">${po.po_number}</strong>
            </td>
            <td>${formatDate(po.order_date)}</td>
            <td>${formatDate(po.expected_delivery_date) || '—'}</td>
            <td>
                <div>
                    <div><strong>${po.supplier_name || 'Unknown Supplier'}</strong></div>
                    ${po.supplier_code ? `<small class="text-muted">${po.supplier_code}</small>` : ''}
                </div>
            </td>
            <td>
                <span class="badge bg-info">${po.item_count} item${po.item_count === 1 ? '' : 's'}</span>
                <br><small class="text-muted">Qty: ${po.total_quantity}</small>
            </td>
            <td>
                <strong class="text-primary">${formatCurrency(po.total_amount)}</strong>
            </td>
            <td>${getPOStatusBadge(po.status)}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button onclick="viewPurchaseOrder(${po.id})" class="btn btn-outline-info" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${!['approved', 'rejected', 'ordered', 'partially_received', 'received'].includes(po.status) ? `
                        <button onclick="editPurchaseOrder(${po.id})" class="btn btn-outline-primary" title="Edit Order">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deletePurchaseOrder(${po.id})" class="btn btn-outline-danger" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function getPOStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Pending</span>',
        'pending_supplier': '<span class="badge bg-warning"><i class="fas fa-hourglass-half me-1"></i>Awaiting Supplier</span>',
        'approved': '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Approved</span>',
        'rejected': '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Rejected</span>',
        'partially_received': '<span class="badge bg-info"><i class="fas fa-box-open me-1"></i>Partial Received</span>',
        'completed': '<span class="badge bg-primary"><i class="fas fa-check-double me-1"></i>Completed</span>',
        'ordered': '<span class="badge bg-info"><i class="fas fa-shipping-fast me-1"></i>Ordered</span>',
        'received': '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Received</span>'
    };
    return badges[status] || `<span class="badge bg-secondary">${status || 'Unknown'}</span>`;
}

// Purchase Order Action Functions
async function editPurchaseOrder(id) {
    // Check if PO is approved before allowing edit
    try {
        const response = await fetch(`${API_BASE}/purchase_orders/show.php?id=${id}`);
        const data = await response.json();
        const po = data.purchase_order || data.data?.purchase_order;

        if (po && ['approved', 'rejected', 'ordered', 'partially_received', 'received'].includes(po.status)) {
            showError('Cannot edit an approved or rejected purchase order. Only viewing is allowed.');
            return;
        }
    } catch (error) {
        console.error('Error checking PO status:', error);
    }

    await openPurchaseOrderModal(id);
}

async function approvePurchaseOrder(id) {
    if (!await showConfirm('Are you sure you want to approve this purchase order?', {
        title: 'Approve Purchase Order',
        confirmText: 'Approve',
        type: 'success'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/purchase_orders/approve.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const data = await response.json();
        if (data.success) {
            showSuccess('Purchase order approved successfully');
            await loadPurchaseOrders();
        } else {
            showError(data.message || 'Failed to approve purchase order');
        }
    } catch (error) {
        console.error('Error approving PO:', error);
        showError('Failed to approve purchase order');
    }
}

async function rejectPurchaseOrder(id) {
    if (!await showConfirm('Are you sure you want to reject this purchase order?', {
        title: 'Reject Purchase Order',
        confirmText: 'Reject',
        type: 'danger'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/purchase_orders/reject.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const data = await response.json();
        if (data.success) {
            showSuccess('Purchase order rejected');
            await loadPurchaseOrders();
        } else {
            showError(data.message || 'Failed to reject purchase order');
        }
    } catch (error) {
        console.error('Error rejecting PO:', error);
        showError('Failed to reject purchase order');
    }
}

async function deletePurchaseOrder(id) {
    // Check if PO is approved before allowing delete
    try {
        const response = await fetch(`${API_BASE}/purchase_orders/show.php?id=${id}`);
        const data = await response.json();
        const po = data.purchase_order || data.data?.purchase_order;

        if (po && ['approved', 'rejected', 'ordered', 'partially_received', 'received'].includes(po.status)) {
            showError('Cannot delete an approved or rejected purchase order. Only viewing is allowed.');
            return;
        }
    } catch (error) {
        console.error('Error checking PO status:', error);
    }

    if (!await showConfirm('Are you sure you want to delete this purchase order? This action cannot be undone.', {
        title: 'Delete Purchase Order',
        confirmText: 'Delete',
        type: 'danger',
        icon: 'fas fa-trash'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/purchase_orders/delete.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const data = await response.json();
        if (data.success) {
            showSuccess('Purchase order deleted successfully');
            await loadPurchaseOrders();
        } else {
            showError(data.message || 'Failed to delete purchase order');
        }
    } catch (error) {
        console.error('Error deleting PO:', error);
        showError('Failed to delete purchase order');
    }
}

async function openPurchaseOrderModal(poId = null) {
    // Load products and suppliers for item selection
    await Promise.all([
        loadProductsForPO(),
        loadSuppliersForPOModal()
    ]);

    const modalContent = `
        <div class="modal fade" id="purchaseOrderModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content" style="background: linear-gradient(135deg, #0f1629 0%, #1a1f3a 100%); color: var(--text-primary); border: 1px solid rgba(0, 245, 255, 0.2); border-radius: 1rem;">
                    <div class="modal-header" style="border-color: var(--border-color); border-bottom: 1px solid rgba(0, 245, 255, 0.2);">
                        <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i><span id="po-modal-title">Create Purchase Order</span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="purchaseOrderForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">PO Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="po-number" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Supplier <span class="text-danger">*</span></label>
                                    <select class="form-select" id="po-supplier" required>
                                        <option value="">Select Supplier</option>
                                        ${allSuppliersForPO.map(supplier => `
                                            <option value="${supplier.id}">${supplier.company_name || supplier.full_name}${supplier.contact_person ? ' - ' + supplier.contact_person : ''}</option>
                                        `).join('')}
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Order Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="po-order-date" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Expected Delivery Date</label>
                                    <input type="date" class="form-control" id="po-expected-delivery">
                                </div>
                            </div>

                            <!-- Order Items Section -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0"><i class="fas fa-box me-2"></i>Order Items</h6>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="addPOItem()" style="box-shadow: 0 2px 8px rgba(0, 245, 255, 0.3);">
                                        <i class="fas fa-plus me-1"></i>Add Item
                                    </button>
                                </div>
                                <div id="po-items-container">
                                    <!-- Items will be added here -->
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3"><i class="fas fa-calculator me-2"></i>Order Summary</h6>
                                    <div id="po-summary" class="p-3 rounded" style="background: rgba(0, 245, 255, 0.05); border: 1px solid rgba(0, 245, 255, 0.2);">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Total Items:</span>
                                            <strong id="total-items" class="text-primary">0</strong>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Total Quantity:</span>
                                            <strong id="total-quantity" class="text-primary">0</strong>
                                        </div>
                                        <hr style="border-color: rgba(0, 245, 255, 0.2);">
                                        <div class="d-flex justify-content-between">
                                            <span><strong>Total Amount:</strong></span>
                                            <strong id="total-amount" class="text-success" style="font-size: 1.2rem;">₱0.00</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-3"><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                                    <textarea class="form-control" id="po-notes" rows="7" placeholder="Add any additional notes or special instructions for this purchase order..." style="resize: none;"></textarea>
                                </div>
                            </div>

                            <div id="po-message" class="mt-3"></div>
                        </form>
                    </div>
                    <div class="modal-footer" style="border-top: 1px solid rgba(0, 245, 255, 0.2);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="savePoBtn">
                            <i class="fas fa-save me-2"></i>Save Purchase Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('purchaseOrderModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalContent);

    if (poId) {
        document.getElementById('po-modal-title').textContent = 'Edit Purchase Order';
        document.getElementById('savePoBtn').textContent = 'Update Purchase Order';
        await loadPurchaseOrderForEdit(poId);
    } else {
        resetPurchaseOrderForm();

        // Add first empty item row for new orders
        addPOItem();

        // Set default order date to today
        document.getElementById('po-order-date').value = new Date().toISOString().split('T')[0];
    }

    // Add event listener for save button
    document.getElementById('savePoBtn').addEventListener('click', () => savePurchaseOrder(poId));

    const modal = new bootstrap.Modal(document.getElementById('purchaseOrderModal'));
    modal.show();

    // Clean up modal when hidden
    document.getElementById('purchaseOrderModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

let allProductsForPO = [];
let poItems = [];

async function loadProductsForPO() {
    try {
        const response = await fetch(`${API_BASE}/products/index.php?status=all`);
        const data = await response.json();

        if (data.success && data.data && data.data.products) {
            allProductsForPO = data.data.products;
        } else if (data.success && data.products) {
            // Fallback for old format
            allProductsForPO = data.products;
        } else {
            showError('Failed to load products. Please try again.');
        }
    } catch (error) {
        console.error('Error loading products for PO:', error);
        showError('Failed to load products: ' + error.message);
    }
}

async function loadSuppliersForPOModal() {
    try {
        const response = await fetch(`${API_BASE}/suppliers/index.php`);
        const data = await response.json();

        if (data.success && data.data && data.data.suppliers) {
            allSuppliersForPO = data.data.suppliers;
        }
    } catch (error) {
        console.error('Error loading suppliers for PO:', error);
    }
}

function resetPurchaseOrderForm() {
    document.getElementById('po-number').value = generatePONumber();
    document.getElementById('po-supplier').value = '';
    document.getElementById('po-order-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('po-expected-delivery').value = '';
    document.getElementById('po-notes').value = '';
    poItems = [];
    document.getElementById('po-items-container').innerHTML = '';
    updatePOSummary();
}

function generatePONumber() {
    const today = new Date();
    const dateStr = today.getFullYear().toString().slice(-2) +
                   String(today.getMonth() + 1).padStart(2, '0') +
                   String(today.getDate()).padStart(2, '0');
    return `PO-${dateStr}-001`;
}

function addPOItem(item = null) {
    const container = document.getElementById('po-items-container');
    const index = poItems.length;

    const itemRow = document.createElement('div');
    itemRow.className = 'po-item-row border rounded p-3 mb-3';
    itemRow.style.background = 'rgba(15, 23, 42, 0.6)';
    itemRow.style.borderColor = 'rgba(0, 245, 255, 0.2)';
    itemRow.dataset.index = index;

        // Simplified but improved layout with product search
        itemRow.innerHTML = `
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Product Search <span class="text-danger">*</span></label>
                    <div class="position-relative">
                        <input type="text" class="form-control product-search-input"
                               placeholder="Search products by name or SKU..." autocomplete="off">
                        <div class="product-search-results" style="
                            position: absolute;
                            top: 100%;
                            left: 0;
                            right: 0;
                            background: var(--bg-card);
                            border: 1px solid var(--border-color);
                            border-top: none;
                            border-radius: 0 0 8px 8px;
                            max-height: 200px;
                            overflow-y: auto;
                            z-index: 1050;
                            display: none;
                            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                        ">
                        </div>
                        <select class="form-select product-select d-none" required>
                            <option value="">Select a product...</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-3 mb-2">
                    <label class="form-label">Quantity <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <button type="button" class="btn btn-outline-secondary qty-minus" style="z-index: 0;">
                            <i class="fas fa-minus"></i>
                        </button>
    <input type="number" class="form-control quantity-input text-center" min="1" value="1" required
                               style="border-left: 0; border-right: 0; font-weight: bold; font-size: 1.1rem;" readonly>
                        <button type="button" class="btn btn-outline-secondary qty-plus" style="z-index: 0;">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-2 mb-2">
                    <label class="form-label">Unit Cost <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" class="form-control unit-cost-input" min="0" step="0.01" placeholder="0.00" required readonly>
                    </div>
                </div>

                <div class="col-md-2 mb-2">
                    <label class="form-label">Total</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="text" class="form-control total-input" readonly style="background-color: rgba(0, 245, 255, 0.05); font-weight: bold;">
                    </div>
                </div>

                <div class="col-md-12 mb-2">
                    <label class="form-label">Item Notes</label>
                    <input type="text" class="form-control notes-input" placeholder="Optional notes for this item...">
                </div>

                <div class="col-md-12">

                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removePOItem(${index})" title="Remove Item">
                        <i class="fas fa-trash me-1"></i>Remove Item
                    </button>
                </div>
            </div>

        `;

    container.appendChild(itemRow);

    // Set initial values if editing
    if (item) {
        const productSelect = itemRow.querySelector('.product-select');
        const quantityInput = itemRow.querySelector('.quantity-input');
        const unitCostInput = itemRow.querySelector('.unit-cost-input');
        const notesInput = itemRow.querySelector('.notes-input');
        const searchInput = itemRow.querySelector('.product-search-input');

        const productId = item.product?.id || item.product_id;
        const productName = item.product?.name || item.product_name || '';
        const unitCost = item.unit_cost || item.unit_price || 0;

        // Create option for the selected product
        if (productId) {
            const option = document.createElement('option');
            option.value = productId;
            option.textContent = productName;
            option.dataset.price = unitCost;
            productSelect.appendChild(option);
            productSelect.value = productId;

            // Update search input to show product name
            if (searchInput && productName) {
                searchInput.value = productName;
            }
        }

        quantityInput.value = item.quantity_ordered || item.quantity;
        unitCostInput.value = unitCost;
        notesInput.value = item.notes || '';

        updateItemTotal(itemRow);
    }

    // Add event listeners
    itemRow.querySelector('.product-select').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const unitCostInput = itemRow.querySelector('.unit-cost-input');
        if (selectedOption.dataset.price) {
            unitCostInput.value = parseFloat(selectedOption.dataset.price);
        }
        updateProductDisplay(itemRow);
        updateItemTotal(itemRow);
    });

    // Product search functionality
    const searchInput = itemRow.querySelector('.product-search-input');
    const searchResults = itemRow.querySelector('.product-search-results');
    const productSelect = itemRow.querySelector('.product-select');

    searchInput.addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase().trim();
        if (query.length >= 2) {
            showProductSearchResults(query, searchResults, productSelect, itemRow);
        } else {
            searchResults.style.display = 'none';
        }
    });

    searchInput.addEventListener('focus', function() {
        if (searchInput.value.trim().length >= 2) {
            showProductSearchResults(searchInput.value.trim(), searchResults, productSelect, itemRow);
        }
    });

    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!itemRow.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Quantity +/- button event listeners
    const quantityInput = itemRow.querySelector('.quantity-input');
    const qtyMinusBtn = itemRow.querySelector('.qty-minus');
    const qtyPlusBtn = itemRow.querySelector('.qty-plus');

    qtyMinusBtn.addEventListener('click', () => {
        const currentValue = parseInt(quantityInput.value) || 1;
        if (currentValue > 1) {
            quantityInput.value = currentValue - 1;
            updateItemTotal(itemRow);
        }
    });

    qtyPlusBtn.addEventListener('click', () => {
        const currentValue = parseInt(quantityInput.value) || 0;
        quantityInput.value = currentValue + 1;
        updateItemTotal(itemRow);
    });

    quantityInput.addEventListener('input', () => updateItemTotal(itemRow));
    itemRow.querySelector('.unit-cost-input').addEventListener('input', () => updateItemTotal(itemRow));

    // Auto-focus on product select for first item
    if (index === 0 && !item) {
        setTimeout(() => itemRow.querySelector('.product-select').focus(), 100);
    }

    poItems.push({
        index,
        product_id: item?.product_id || '',
        quantity: item?.quantity_ordered || 1,
        unit_cost: item?.unit_cost || 0,
        notes: item?.notes || ''
    });

    updatePOSummary();
}

function removePOItem(index) {
    const itemRows = document.querySelectorAll('.po-item-row');
    itemRows[index].remove();

    poItems.splice(index, 1);
    updatePOSummary();

    // Renumber remaining items
    document.querySelectorAll('.po-item-row').forEach((row, i) => {
        row.dataset.index = i;
        const removeBtn = row.querySelector('button[onclick*="removePOItem"]');
        if (removeBtn) {
            removeBtn.setAttribute('onclick', `removePOItem(${i})`);
        }
    });
}

function updateItemTotal(itemRow) {
    const quantity = parseInt(itemRow.querySelector('.quantity-input').value) || 0;
    const unitCost = parseFloat(itemRow.querySelector('.unit-cost-input').value) || 0;
    const totalInput = itemRow.querySelector('.total-input');

    const total = quantity * unitCost;
    totalInput.value = total.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    updatePOSummary();
}

function updateProductDisplay(itemRow) {
    // Function currently disabled - display element not in HTML structure
    // TODO: Re-implement when adding product display feature
    return;
    /*
    const productSelect = itemRow.querySelector('.product-select');
    const displayDiv = itemRow.querySelector('.selected-product-display');

    // Get the selected product from data attributes or find it
    const selectedProductId = productSelect.value || itemRow.dataset.selectedProductId;
    const selectedProduct = itemRow.dataset.selectedProductName;

    if (selectedProductId && selectedProduct) {
        // Find the full product data
        const product = allProductsForPO.find(p => p.id == selectedProductId);

        if (displayDiv) {
            displayDiv.innerHTML = `
                <small class="text-success">✓ Selected: <strong>${selectedProduct}</strong></small>
                ${product ? `<br><small class="text-muted">Current Stock: ${product.quantity_on_hand || 0}</small>` : ''}
            `;
        }
    } else {
        if (displayDiv) {
            displayDiv.innerHTML = '';
        }
    }
    */
}

function showProductSearchResults(query, searchResults, productSelect, itemRow) {
    const filteredProducts = allProductsForPO.filter(product => {
        const searchTerm = query.toLowerCase();
        return product.name.toLowerCase().includes(searchTerm) ||
               product.sku.toLowerCase().includes(searchTerm);
    }).slice(0, 10); // Limit to 10 results

    if (filteredProducts.length === 0) {
        searchResults.innerHTML = '<div class="text-center text-muted py-2">No products found</div>';
        searchResults.style.display = 'block';
        return;
    }

    searchResults.innerHTML = filteredProducts.map(product => `
        <div class="product-search-item px-3 py-2 border-bottom" style="cursor: pointer; background: var(--bg-secondary);" data-product-id="${product.id}">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold">${product.name}</div>
                    <small class="text-muted">SKU: ${product.sku} | Stock: ${product.quantity_on_hand || 0} | Price: ₱${product.selling_price}</small>
                </div>
            </div>
        </div>
    `).join('');

    searchResults.style.display = 'block';

    // Add click handlers
    searchResults.querySelectorAll('.product-search-item').forEach(item => {
        item.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const product = allProductsForPO.find(p => p.id == productId);

            if (product) {
                // Update hidden select - first ensure the option exists
                let option = productSelect.querySelector(`option[value="${productId}"]`);
                if (!option) {
                    option = document.createElement('option');
                    option.value = productId;
                    option.textContent = product.name;
                    option.dataset.price = product.selling_price || 0;
                    productSelect.appendChild(option);
                }
                productSelect.value = productId;

                // Update search input to show selected product
                const searchInput = itemRow.querySelector('.product-search-input');
                searchInput.value = product.name;

                // Update unit cost
                const unitCostInput = itemRow.querySelector('.unit-cost-input');
                unitCostInput.value = product.selling_price || 0;

                // Update display
                updateProductDisplay(itemRow);

                // Hide search results
                searchResults.style.display = 'none';

                // Calculate total
                updateItemTotal(itemRow);
            }
        });

        // Add hover effect
        item.addEventListener('mouseenter', function() {
            this.style.background = 'var(--accent-light, rgba(0, 245, 255, 0.1))';
        });
        item.addEventListener('mouseleave', function() {
            this.style.background = 'var(--bg-secondary)';
        });
    });
}

function updatePOSummary() {
    const summaryDiv = document.getElementById('po-summary');
    const totalItemsEl = document.getElementById('total-items');
    const totalQuantityEl = document.getElementById('total-quantity');
    const totalAmountEl = document.getElementById('total-amount');

    const itemRows = document.querySelectorAll('.po-item-row');
    let totalItems = itemRows.length;
    let totalQuantity = 0;
    let totalAmount = 0;

    itemRows.forEach(row => {
        const quantity = parseInt(row.querySelector('.quantity-input').value) || 0;
        const unitCost = parseFloat(row.querySelector('.unit-cost-input').value) || 0;

        totalQuantity += quantity;
        totalAmount += quantity * unitCost;
    });

    totalItemsEl.textContent = totalItems;
    totalQuantityEl.textContent = totalQuantity;
    totalAmountEl.textContent = formatCurrency(totalAmount);
}

async function savePurchaseOrder(poId) {
    const saveBtn = document.getElementById('savePoBtn');
    const originalBtnText = saveBtn.innerHTML;

    try {
        // Show loading state
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';

        // Validate form
        const poNumber = document.getElementById('po-number').value;
        const supplierId = document.getElementById('po-supplier').value;
        const orderDate = document.getElementById('po-order-date').value;

        if (!poNumber || !supplierId || !orderDate) {
            showError('Please fill in all required fields');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnText;
            return;
        }

        // Validate items
        const itemRows = document.querySelectorAll('.po-item-row');
        if (itemRows.length === 0) {
            showError('Please add at least one item to the purchase order');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnText;
            return;
        }

    // Build items array
    const items = [];
    let hasValidItems = true;

    itemRows.forEach(row => {
        const productSelect = row.querySelector('.product-select');
        const quantityInput = row.querySelector('.quantity-input');
        const unitCostInput = row.querySelector('.unit-cost-input');
        const notesInput = row.querySelector('.notes-input');

        const productId = productSelect.value;
        const quantity = parseInt(quantityInput.value);
        const unitCost = parseFloat(unitCostInput.value);

        if (!productId || isNaN(quantity) || quantity <= 0 || isNaN(unitCost) || unitCost < 0) {
            hasValidItems = false;
        }

        items.push({
            product_id: productId,
            quantity_ordered: quantity,
            unit_cost: unitCost,
            total_cost: quantity * unitCost,
            notes: notesInput.value
        });
    });

        if (!hasValidItems) {
            showError('Please fill in all required fields for each item');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnText;
            return;
        }

        const poData = {
            po_number: poNumber,
            supplier_id: supplierId,
            order_date: orderDate,
            expected_delivery_date: document.getElementById('po-expected-delivery').value || null,
            total_amount: document.getElementById('total-amount').textContent.replace('₱', '').replace(/,/g, ''),
            notes: document.getElementById('po-notes').value,
            items: items
        };

        const isEdit = poId !== null;

        // Add ID to request body when editing
        if (isEdit) {
            poData.id = poId;
        }
        const url = isEdit ? `${API_BASE}/purchase_orders/update.php?id=${poId}` : `${API_BASE}/purchase_orders/create.php`;

        const response = await fetch(url, {
            method: isEdit ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(poData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess(`Purchase order ${isEdit ? 'updated' : 'created'} successfully`);
            bootstrap.Modal.getInstance(document.getElementById('purchaseOrderModal')).hide();

            // Check if we're on the purchase orders page
            if (document.getElementById('po-table-body')) {
                // We're on the PO page, reload the list
                await loadPurchaseOrders();
            } else {
                // Navigate to the purchase orders page
                if (typeof navigateTo === 'function') {
                    navigateTo('purchase-orders');
                } else {
                    // Fallback: reload to PO page
                    window.location.href = window.location.href.split('?')[0] + '?page=purchase-orders';
                }
            }
        } else {
            showError(result.message || `Failed to ${isEdit ? 'update' : 'create'} purchase order`);
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnText;
        }
    } catch (error) {
        devLog('Error saving purchase order:', error);
        showError(`Failed to ${isEdit ? 'update' : 'create'} purchase order`);
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalBtnText;
    }
}

async function loadPurchaseOrderForEdit(id) {
    try {
        const response = await fetch(`${API_BASE}/purchase_orders/show.php?id=${id}`);
        const data = await response.json();

        if (data.success && (data.purchase_order || data.data?.purchase_order)) {
            const po = data.purchase_order || data.data?.purchase_order;

            document.getElementById('po-number').value = po.po_number;
            document.getElementById('po-supplier').value = po.supplier?.id || '';
            document.getElementById('po-order-date').value = po.order_date;
            document.getElementById('po-expected-delivery').value = po.expected_delivery || '';
            document.getElementById('po-notes').value = po.notes || '';

            // Clear existing items and add the loaded ones
            document.getElementById('po-items-container').innerHTML = '';
            po.items.forEach(item => {
                addPOItem(item);
            });

            updatePOSummary();
        } else {
            console.error('Invalid API response for edit:', data);
            showError('Failed to load purchase order details - Invalid response format');
        }
    } catch (error) {
        console.error('Error loading PO for edit:', error);
        devLog('Error loading PO for edit:', error);
        showError('Failed to load purchase order details - ' + error.message);
    }
}

async function viewPurchaseOrder(id) {
    try {
        const response = await fetch(`${API_BASE}/purchase_orders/show.php?id=${id}`);
        const data = await response.json();

        if (data.success && (data.purchase_order || data.data?.purchase_order)) {
            const po = data.purchase_order || data.data?.purchase_order;

            const modalContent = `
                <div class="modal fade" id="viewPOModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                            <div class="modal-header" style="border-color: var(--border-color);">
                                <h5 class="modal-title">
                                    <i class="fas fa-file-invoice me-2"></i>Purchase Order ${po.po_number}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Order Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Status:</strong></td>
                                                <td>${getPOStatusBadge(po.status)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Order Date:</strong></td>
                                                <td>${formatDate(po.order_date)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Expected Delivery:</strong></td>
                                                <td>${formatDate(po.expected_delivery)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Amount:</strong></td>
                                                <td><strong class="text-primary">${formatCurrency(po.total_amount)}</strong></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Total Items:</strong></td>
                                                <td>${po.items?.length || 0}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Supplier Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Name:</strong></td>
                                                <td>${po.supplier?.name || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Code:</strong></td>
                                                <td>${po.supplier?.code || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td>${po.supplier?.email || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Created By:</strong></td>
                                                <td>${po.created_by?.name || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Approved By:</strong></td>
                                                <td>${po.approved_by?.name || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <h6 class="text-primary mb-3">Order Items</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>SKU</th>
                                                <th>Category</th>
                                                <th>Qty Ordered</th>
                                                <th>Qty Received</th>
                                                <th>Unit Cost</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${(po.items || []).map(item => `
                                                <tr>
                                                    <td>${item.product?.name || 'Unknown'}</td>
                                                    <td><code>${item.product?.sku || 'N/A'}</code></td>
                                                    <td>${item.product?.category || 'N/A'}</td>
                                                    <td>${item.quantity_ordered}</td>
                                                    <td>${item.quantity_received || 0}</td>
                                                    <td>${formatCurrency(item.unit_cost)}</td>
                                                    <td><strong>${formatCurrency(item.total_cost)}</strong></td>
                                                    <td>
                                                        ${item.quantity_ordered === item.quantity_received ?
                                                            '<span class="badge bg-success">Complete</span>' :
                                                            item.quantity_received > 0 ?
                                                                '<span class="badge bg-warning">Partial</span>' :
                                                                '<span class="badge bg-secondary">Pending</span>'}
                                                    </td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>

                                ${po.notes ? `
                                    <div class="mt-3">
                                        <h6 class="text-primary">Notes</h6>
                                        <p class="text-muted">${po.notes}</p>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="modal-footer" style="border-color: var(--border-color);">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button onclick="editPurchaseOrder(${po.id})" class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i>Edit Order
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if present
            const existingModal = document.getElementById('viewPOModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalContent);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('viewPOModal'));
            modal.show();

            // Clean up modal when hidden
            document.getElementById('viewPOModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        } else {
            console.error('Invalid API response format (v2):', data);
            showError('Failed to load purchase order details - Invalid response format');
        }
    } catch (error) {
        console.error('Error loading PO details:', error);
        devLog('Error loading PO details:', error);
        showError('Failed to load purchase order details - ' + error.message);
    }
}

async function editPurchaseOrder(id) {
    // Check if PO is approved before allowing edit
    try {
        const response = await fetch(`${API_BASE}/purchase_orders/show.php?id=${id}`);
        const data = await response.json();
        const po = data.purchase_order || data.data?.purchase_order;

        if (po && ['approved', 'rejected', 'ordered', 'partially_received', 'received'].includes(po.status)) {
            showError('Cannot edit an approved or rejected purchase order. Only viewing is allowed.');
            return;
        }
    } catch (error) {
        console.error('Error checking PO status:', error);
    }

    await openPurchaseOrderModal(id);
}

async function approvePurchaseOrder(id) {
    if (!await showConfirm('Are you sure you want to approve this purchase order?', {
        title: 'Approve Purchase Order',
        confirmText: 'Approve',
        type: 'success',
        icon: 'fas fa-check'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/purchase_orders/approve.php?id=${id}`, {
            method: 'POST'
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Purchase order approved successfully');
            await loadPurchaseOrders();
        } else {
            showError(result.message || 'Failed to approve purchase order');
        }
    } catch (error) {
        showError('Failed to approve purchase order');
    }
}

async function rejectPurchaseOrder(id) {
    const reason = await showPrompt('Please provide a reason for rejecting this purchase order:', {
        title: 'Reject Purchase Order',
        required: true
    });

    if (!reason) return;

    try {
        const response = await fetch(`${API_BASE}/purchase_orders/reject.php?id=${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reason })
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Purchase order rejected successfully');
            await loadPurchaseOrders();
        } else {
            showError(result.message || 'Failed to reject purchase order');
        }
    } catch (error) {
        showError('Failed to reject purchase order');
    }
}

async function deletePurchaseOrder(id) {
    // Check if PO is approved before allowing delete
    try {
        const response = await fetch(`${API_BASE}/purchase_orders/show.php?id=${id}`);
        const data = await response.json();
        const po = data.purchase_order || data.data?.purchase_order;

        if (po && ['approved', 'rejected', 'ordered', 'partially_received', 'received'].includes(po.status)) {
            showError('Cannot delete an approved or rejected purchase order. Only viewing is allowed.');
            return;
        }
    } catch (error) {
        console.error('Error checking PO status:', error);
    }

    if (!await showConfirm('Are you sure you want to delete this purchase order? This action cannot be undone.', {
        title: 'Delete Purchase Order',
        confirmText: 'Delete',
        type: 'danger',
        icon: 'fas fa-trash'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/purchase_orders/delete.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Purchase order deleted successfully');
            await loadPurchaseOrders();
        } else {
            showError(result.message || 'Failed to delete purchase order');
        }
    } catch (error) {
        console.error('Error deleting purchase order:', error);
        showError('Failed to delete purchase order');
    }
}

async function loadGRNPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-clipboard-check me-2"></i>Goods Received Notes</h1>
            <p class="page-subtitle">Process delivery receipts, quality inspection, and inventory updates</p>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex gap-3 flex-wrap">
                <select id="grn-status-filter" class="form-select">
                    <option value="all">All Status</option>
                    <option value="pending">Pending Inspection</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="rejected">Rejected</option>
                </select>

                <select id="grn-po-filter" class="form-select">
                    <option value="all">All Purchase Orders</option>
                </select>

                <input type="text" id="grn-search" class="form-control" placeholder="Search by GRN number..." style="width: 200px;">
            </div>
            <button class="btn btn-primary" onclick="openGRNModal()">
                <i class="fas fa-plus me-2"></i>Create GRN
            </button>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Goods Receipt Notes</h5>
                    <div id="grn-summary" class="text-muted small">
                        Loading...
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div id="grn-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading goods received notes...</p>
                </div>

                <div id="grn-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>GRN Number</th>
                                    <th>PO #</th>
                                    <th>Supplier</th>
                                    <th>Received Date</th>
                                    <th>Items</th>
                                    <th>Received Qty</th>
                                    <th>Accepted Qty</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="grn-table-body">
                                <!-- GRNs will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-grn-message" class="text-center py-5 d-none">
                    <i class="fas fa-clipboard-check fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Goods Received Notes Found</h5>
                    <p class="text-muted">Start processing your deliveries with your first GRN.</p>
                    <button class="btn btn-primary" onclick="openGRNModal()">
                        <i class="fas fa-plus me-2"></i>Create First GRN
                    </button>
                </div>
            </div>
        </div>
    `;

    // Load POs for filter dropdown
    await loadPOsForGRNFilter();

    // Load GRNs
    await loadGRNs();

    // Add event listeners
    document.getElementById('grn-status-filter').addEventListener('change', () => loadGRNs());
    document.getElementById('grn-po-filter').addEventListener('change', () => loadGRNs());
    document.getElementById('grn-search').addEventListener('input', debounce(() => loadGRNs(), 500));
}

let allGRNs = [];
let allPOsForGRN = [];

async function loadPOsForGRNFilter() {
    try {
        const response = await fetch(`${API_BASE}/purchase_orders/index.php?status=approved`);
        const data = await response.json();

        if (data.success && data.purchase_orders) {
            allPOsForGRN = data.purchase_orders;
            const select = document.getElementById('grn-po-filter');
            select.innerHTML = '<option value="all">All Purchase Orders</option>';

            allPOsForGRN.forEach(po => {
                const option = document.createElement('option');
                option.value = po.id;
                option.textContent = po.po_number;
                select.appendChild(option);
            });
        }
    } catch (error) {
        devLog('Error loading POs for GRN filter:', error);
    }
}

async function loadGRNs() {
    const loadingIndicator = document.getElementById('grn-loading');
    const content = document.getElementById('grn-content');
    const noGRNMessage = document.getElementById('no-grn-message');
    const tbody = document.getElementById('grn-table-body');
    const summary = document.getElementById('grn-summary');

    const statusFilter = document.getElementById('grn-status-filter').value;
    const poFilter = document.getElementById('grn-po-filter').value;
    const searchFilter = document.getElementById('grn-search').value.trim();

    try {
        loadingIndicator.classList.remove('d-none');
        content.classList.add('d-none');
        noGRNMessage.classList.add('d-none');

        let url = `${API_BASE}/grn/index.php`;
        const params = [];

        if (statusFilter !== 'all') params.push(`status=${encodeURIComponent(statusFilter)}`);
        if (poFilter !== 'all') params.push(`po_id=${poFilter}`);

        if (params.length > 0) url += '?' + params.join('&');

        const response = await fetch(url);
        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.grns && data.grns.length > 0) {
            allGRNs = data.grns;

            // Apply client-side filtering
            let filtered = allGRNs;
            if (searchFilter) {
                filtered = filtered.filter(grn =>
                    grn.grn_number?.toLowerCase().includes(searchFilter.toLowerCase())
                );
            }

            displayGRNs(filtered);
            content.classList.remove('d-none');

            // Update summary
            if (summary) {
                const completed = allGRNs.filter(grn => grn.inspection_status === 'completed').length;
                const pending = allGRNs.filter(grn => grn.inspection_status === 'pending').length;
                const inProgress = allGRNs.filter(grn => grn.inspection_status === 'in_progress').length;
                summary.textContent = `${allGRNs.length} GRN${allGRNs.length === 1 ? '' : 's'} • ${completed} completed • ${inProgress} in progress • ${pending} pending`;
            }
        } else {
            noGRNMessage.classList.remove('d-none');
            if (summary) summary.textContent = 'No goods received notes found';
        }
    } catch (error) {
        devLog('Error loading GRNs:', error);
        loadingIndicator.classList.add('d-none');
        showError('Failed to load goods received notes. Please try again.');
    }
}

function displayGRNs(grns) {
    const tbody = document.getElementById('grn-table-body');
    tbody.innerHTML = grns.map(grn => `
        <tr>
            <td>
                <strong class="text-primary">${grn.grn_number}</strong>
            </td>
            <td>
                ${grn.po ? `<a href="javascript:void(0)" onclick="viewPOFromGRN(${grn.po.id})">${grn.po.number}</a>` : 'N/A'}
            </td>
            <td>
                <div>
                    <div><strong>${grn.supplier_name || 'Unknown Supplier'}</strong></div>
                </div>
            </td>
            <td>${formatDate(grn.received_date)}</td>
            <td>
                <span class="badge bg-info">${grn.item_count} item${grn.item_count === 1 ? '' : 's'}</span>
            </td>
            <td>
                <strong>${grn.total_quantity_received}</strong>
            </td>
            <td>
                <span class="badge bg-success">${grn.total_quantity_accepted}</span>
                ${grn.total_quantity_received !== grn.total_quantity_accepted ?
                    `<br><small class="text-danger">${grn.total_quantity_received - grn.total_quantity_accepted} rejected</small>` : ''}
            </td>
            <td>${getGRNStatusBadge(grn.inspection_status)}</td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button onclick="viewGRN(${grn.id})" class="btn btn-outline-info" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button onclick="editGRN(${grn.id})" class="btn btn-outline-primary" title="Edit GRN">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${grn.inspection_status !== 'completed' ?
                        `<button onclick="finishGRN(${grn.id})" class="btn btn-outline-success" title="Mark Complete">
                            <i class="fas fa-check"></i>
                        </button>` : ''}
                    <button onclick="deleteGRN(${grn.id})" class="btn btn-outline-danger" title="Delete GRN">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getGRNStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning"><i class="fas fa-clock me-1"></i>Pending</span>',
        'in_progress': '<span class="badge bg-primary"><i class="fas fa-cog me-1"></i>In Progress</span>',
        'completed': '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Completed</span>',
        'rejected': '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Rejected</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

async function openGRNModal(grnId = null) {
    // Load approved POs for selection
    await loadApprovedPOsForGRN();

    const modalContent = `
        <div class="modal fade" id="grnModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i><span id="grn-modal-title">Create Goods Received Note</span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">GRN Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="grn-number" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Order <span class="text-danger">*</span></label>
                                <select class="form-select" id="grn-po" required>
                                    <option value="">Select Purchase Order</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Received Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="grn-received-date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Inspection Status</label>
                                <select class="form-select" id="grn-status">
                                    <option value="pending">Pending Inspection</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>

                        <!-- PO Items Section (auto-loaded) -->
                        <div id="grn-po-items-section" class="mb-4 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Delivery Items & Quality Inspection</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="acceptAllItems()">
                                    <i class="fas fa-check-circle me-1"></i>Accept All
                                </button>
                            </div>

                            <div class="alert alert-info alert-sm">
                                <i class="fas fa-info-circle me-2"></i>
                                Review quantities received against ordered amounts. Mark items as accepted or rejected during quality inspection.
                            </div>

                            <div id="grn-po-items-container">
                                <!-- PO items will be loaded here -->
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h6>Inspection Summary</h6>
                                <div id="grn-inspection-summary">
                                    <div class="mb-2"><strong>Total Items:</strong> <span id="inspection-total-items">0</span></div>
                                    <div class="mb-2"><strong>Items Received:</strong> <span id="inspection-total-received">0</span></div>
                                    <div class="mb-2"><strong>Items Accepted:</strong> <span id="inspection-total-accepted">0</span></div>
                                    <div class="mb-2"><strong>Items Rejected:</strong> <span id="inspection-total-rejected">0</span></div>
                                    <div class="mb-2"><strong>Total Value:</strong> <span id="inspection-total-value">₱0.00</span></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Delivery Notes</label>
                                <textarea class="form-control" id="grn-notes" rows="4" placeholder="Driver name, vehicle details, condition of goods, etc..."></textarea>
                            </div>
                        </div>

                        <div id="grn-message" class="mt-3"></div>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveGRNBtn">
                            <i class="fas fa-save me-2"></i>Save GRN
                        </button>
                        <button type="button" class="btn btn-success" id="completeGRNBtn" style="display: none;">
                            <i class="fas fa-check-double me-2"></i>Complete Inspection
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('grnModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalContent);

    if (grnId) {
        document.getElementById('grn-modal-title').textContent = 'Edit Goods Received Note';
        document.getElementById('saveGRNBtn').textContent = 'Update GRN';
        await loadGRNForEdit(grnId);
    } else {
        resetGRNForm();
        // Set default received date to today
        document.getElementById('grn-received-date').value = new Date().toISOString().split('T')[0];
    }

    // Add event listeners
    document.getElementById('grn-po').addEventListener('change', loadPOItemsForGRN);
    document.getElementById('saveGRNBtn').addEventListener('click', () => saveGRN(grnId));
    document.getElementById('completeGRNBtn').addEventListener('click', () => completeGRN(grnId));

    const modal = new bootstrap.Modal(document.getElementById('grnModal'));
    modal.show();

    // Clean up modal when hidden
    document.getElementById('grnModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

let approvedPOsForGRN = [];
let currentGRNItems = [];

async function loadApprovedPOsForGRN() {
    try {
        const response = await fetch(`${API_BASE}/purchase_orders/index.php?status=approved`);
        const data = await response.json();

        if (data.success && data.purchase_orders) {
            approvedPOsForGRN = data.purchase_orders;
            const select = document.getElementById('grn-po');
            select.innerHTML = '<option value="">Select Purchase Order</option>';

            approvedPOsForGRN.forEach(po => {
                const option = document.createElement('option');
                option.value = po.id;
                option.textContent = `${po.po_number} - ${po.supplier?.name || 'Unknown'} - ${formatDate(po.expected_delivery)}`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        devLog('Error loading approved POs:', error);
    }
}

async function loadPOItemsForGRN() {
    const poId = document.getElementById('grn-po').value;
    const itemsContainer = document.getElementById('grn-po-items-container');
    const itemsSection = document.getElementById('grn-po-items-section');

    if (!poId) {
        itemsSection.classList.add('d-none');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/purchase_orders/show.php?id=${poId}`);
        const data = await response.json();

        if (data.success && data.purchase_order) {
            const po = data.purchase_order;
            currentGRNItems = po.items || [];

            // Display PO items for inspection
            displayPOItemsForGRN(po);
            itemsSection.classList.remove('d-none');
            updateInspectionSummary();
        }
    } catch (error) {
        devLog('Error loading PO items for GRN:', error);
        showError('Failed to load purchase order items');
    }
}

function displayPOItemsForGRN(po) {
    const itemsContainer = document.getElementById('grn-po-items-container');
    itemsContainer.innerHTML = '';

    if (!po.items || po.items.length === 0) {
        itemsContainer.innerHTML = '<div class="alert alert-warning">No items found in this purchase order.</div>';
        return;
    }

    po.items.forEach((item, index) => {
        const itemRow = document.createElement('div');
        itemRow.className = 'po-grn-item-row border rounded p-3 mb-3 bg-light';
        itemRow.dataset.index = index;

        itemRow.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-2">
                        <strong>${item.product?.name || 'Unknown Product'}</strong>
                        <br><small class="text-muted">SKU: ${item.product?.sku || 'N/A'}</small>
                        <br><small class="text-muted">Category: ${item.product?.category || 'N/A'}</small>
                    </div>
                    <div>
                        <span class="badge bg-info">Ordered: ${item.quantity_ordered}</span>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Received *</label>
                            <input type="number" class="form-control quantity-received-input" min="0" value="${item.quantity_ordered}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Accepted *</label>
                            <input type="number" class="form-control quantity-accepted-input" min="0" value="${item.quantity_ordered}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Rejected</label>
                            <input type="number" class="form-control quantity-rejected-input" min="0" value="0" readonly>
                            <small class="form-text">(Auto-calculated)</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit Cost</label>
                            <input type="number" class="form-control unit-cost-input" min="0" step="0.01" value="${item.unit_cost}" readonly>
                            <small class="form-text">(From PO)</small>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <label class="form-label">Quality Notes</label>
                            <input type="text" class="form-control quality-notes-input" placeholder="Good condition, damaged, missing parts, etc...">
                        </div>
                    </div>
                </div>
            </div>
        `;

        itemsContainer.appendChild(itemRow);

        // Add event listeners for calculations
        const receivedInput = itemRow.querySelector('.quantity-received-input');
        const acceptedInput = itemRow.querySelector('.quantity-accepted-input');
        const rejectedInput = itemRow.querySelector('.quantity-rejected-input');

        receivedInput.addEventListener('input', () => {
            const received = parseInt(receivedInput.value) || 0;
            const accepted = Math.min(parseInt(acceptedInput.value) || 0, received);
            acceptedInput.value = accepted;
            rejectedInput.value = received - accepted;
            updateInspectionSummary();
        });

        acceptedInput.addEventListener('input', () => {
            const received = parseInt(receivedInput.value) || 0;
            const accepted = parseInt(acceptedInput.value) || 0;
            rejectedInput.value = Math.max(0, received - accepted);
            updateInspectionSummary();
        });
    });

    updateInspectionSummary();
}

function updateInspectionSummary() {
    const totalItemsEl = document.getElementById('inspection-total-items');
    const totalReceivedEl = document.getElementById('inspection-total-received');
    const totalAcceptedEl = document.getElementById('inspection-total-accepted');
    const totalRejectedEl = document.getElementById('inspection-total-rejected');
    const totalValueEl = document.getElementById('inspection-total-value');

    const itemRows = document.querySelectorAll('.po-grn-item-row');
    let totalItems = itemRows.length;
    let totalReceived = 0;
    let totalAccepted = 0;
    let totalRejected = 0;
    let totalValue = 0;

    itemRows.forEach(row => {
        const received = parseInt(row.querySelector('.quantity-received-input').value) || 0;
        const accepted = parseInt(row.querySelector('.quantity-accepted-input').value) || 0;
        const unitCost = parseFloat(row.querySelector('.unit-cost-input').value) || 0;

        totalReceived += received;
        totalAccepted += accepted;
        totalRejected += (received - accepted);
        totalValue += (accepted * unitCost);
    });

    totalItemsEl.textContent = totalItems;
    totalReceivedEl.textContent = totalReceived;
    totalAcceptedEl.textContent = totalAccepted;
    totalRejectedEl.textContent = totalRejected;
    totalValueEl.textContent = formatCurrency(totalValue);
}

function acceptAllItems() {
    const itemRows = document.querySelectorAll('.po-grn-item-row');

    itemRows.forEach(row => {
        const receivedInput = row.querySelector('.quantity-received-input');
        const acceptedInput = row.querySelector('.quantity-accepted-input');
        const received = parseInt(receivedInput.value) || 0;

        acceptedInput.value = received;
        const rejectedInput = row.querySelector('.quantity-rejected-input');
        rejectedInput.value = 0;
    });

    updateInspectionSummary();
}

function resetGRNForm() {
    document.getElementById('grn-number').value = generateGRNNumber();
    document.getElementById('grn-po').value = '';
    document.getElementById('grn-received-date').value = '';
    document.getElementById('grn-status').value = 'pending';
    document.getElementById('grn-notes').value = '';
    document.getElementById('grn-po-items-section').classList.add('d-none');
    document.getElementById('grn-po-items-container').innerHTML = '';
    document.getElementById('saveGRNBtn').style.display = 'inline-block';
    document.getElementById('completeGRNBtn').style.display = 'none';
    currentGRNItems = [];
    updateInspectionSummary();
}

function generateGRNNumber() {
    const today = new Date();
    const dateStr = today.getFullYear().toString().slice(-2) +
                   String(today.getMonth() + 1).padStart(2, '0') +
                   String(today.getDate()).padStart(2, '0');
    return `GRN-${dateStr}-001`;
}

async function saveGRN(grnId) {
    // Validate form
    const grnNumber = document.getElementById('grn-number').value;
    const poId = document.getElementById('grn-po').value;
    const receivedDate = document.getElementById('grn-received-date').value;

    if (!grnNumber || !poId || !receivedDate) {
        showError('Please fill in all required fields');
        return;
    }

    // Build items array
    const items = [];
    const itemRows = document.querySelectorAll('.po-grn-item-row');

    itemRows.forEach((row, index) => {
        const received = parseInt(row.querySelector('.quantity-received-input').value) || 0;
        const accepted = parseInt(row.querySelector('.quantity-accepted-input').value) || 0;
        const rejected = parseInt(row.querySelector('.quantity-rejected-input').value) || 0;
        const unitCost = parseFloat(row.querySelector('.unit-cost-input').value) || 0;
        const notes = row.querySelector('.quality-notes-input').value;

        if (currentGRNItems[index]) {
            items.push({
                po_item_id: currentGRNItems[index].id,
                product_id: currentGRNItems[index].product?.id,
                quantity_received: received,
                quantity_accepted: accepted,
                quantity_rejected: rejected,
                unit_cost: unitCost,
                notes: notes
            });
        }
    });

    if (items.length === 0) {
        showError('Please add at least one item to inspect');
        return;
    }

    const grnData = {
        grn_number: grnNumber,
        po_id: poId,
        received_date: receivedDate,
        inspection_status: document.getElementById('grn-status').value,
        notes: document.getElementById('grn-notes').value,
        items: items
    };

    const isEdit = grnId !== null;
    const url = isEdit ? `${API_BASE}/grn/update.php?id=${grnId}` : `${API_BASE}/grn/create.php`;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(grnData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess(`Goods received note ${isEdit ? 'updated' : 'created'} successfully`);
            bootstrap.Modal.getInstance(document.getElementById('grnModal')).hide();
            await loadGRNs();
        } else {
            showError(result.message || `Failed to ${isEdit ? 'update' : 'create'} goods received note`);
        }
    } catch (error) {
        devLog('Error saving GRN:', error);
        showError(`Failed to ${isEdit ? 'update' : 'create'} goods received note`);
    }
}

async function loadGRNForEdit(id) {
    try {
        const response = await fetch(`${API_BASE}/grn/show.php?id=${id}`);
        const data = await response.json();

        if (data.success && data.grn) {
            const grn = data.grn;

            document.getElementById('grn-number').value = grn.grn_number;
            document.getElementById('grn-po').value = grn.po?.id || '';
            document.getElementById('grn-received-date').value = grn.received_date;
            document.getElementById('grn-status').value = grn.inspection_status;
            document.getElementById('grn-notes').value = grn.notes || '';

            // Load PO items will trigger when PO is selected
            if (grn.po?.id) {
                // Load PO items after a short delay to ensure PO is selected
                setTimeout(async () => {
                    await loadPOItemsForGRN();
                    // Update quantities from GRN
                    const itemRows = document.querySelectorAll('.po-grn-item-row');
                    itemRows.forEach((row, index) => {
                        if (grn.items && grn.items[index]) {
                            const item = grn.items[index];
                            row.querySelector('.quantity-received-input').value = item.quantity_received;
                            row.querySelector('.quantity-accepted-input').value = item.quantity_accepted;
                            row.querySelector('.quantity-rejected-input').value = item.quantity_rejected;
                            row.querySelector('.quality-notes-input').value = item.notes || '';
                        }
                    });
                    updateInspectionSummary();
                }, 500);
            }
        } else {
            showError('Failed to load GRN details');
        }
    } catch (error) {
        devLog('Error loading GRN for edit:', error);
        showError('Failed to load GRN details');
    }
}

async function completeGRN(grnId) {
    if (!await showConfirm('Marking this GRN as complete will update inventory and finalize the receipt. This action cannot be undone.', {
        title: 'Complete Goods Inspection',
        confirmText: 'Complete & Update Inventory',
        type: 'success',
        icon: 'fas fa-check-double'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/grn/complete.php?id=${grnId}`, {
            method: 'POST'
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('GRN completed successfully and inventory updated');
            bootstrap.Modal.getInstance(document.getElementById('grnModal')).hide();
            await loadGRNs();
        } else {
            showError(result.message || 'Failed to complete GRN');
        }
    } catch (error) {
        showError('Failed to complete GRN');
    }
}

async function viewGRN(id) {
    try {
        const response = await fetch(`${API_BASE}/grn/show.php?id=${id}`);
        const data = await response.json();

        if (data.success && data.grn) {
            const grn = data.grn;

            const modalContent = `
                <div class="modal fade" id="viewGRNModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                            <div class="modal-header" style="border-color: var(--border-color);">
                                <h5 class="modal-title">
                                    <i class="fas fa-clipboard-check me-2"></i>GRN ${grn.grn_number}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Receipt Information</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>Status:</strong></td>
                                                <td>${getGRNStatusBadge(grn.inspection_status)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Received Date:</strong></td>
                                                <td>${formatDate(grn.received_date)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>GRN Number:</strong></td>
                                                <td><code>${grn.grn_number}</code></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Received By:</strong></td>
                                                <td>${grn.received_by?.name || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-primary mb-3">Purchase Order Details</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td><strong>PO Number:</strong></td>
                                                <td><a href="javascript:void(0)" onclick="viewPOFromGRN(${grn.po?.id})">${grn.po?.number || 'N/A'}</a></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Order Date:</strong></td>
                                                <td>${formatDate(grn.po?.order_date)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Expected Delivery:</strong></td>
                                                <td>${formatDate(grn.po?.expected_delivery)}</td>
                                            </tr>
                                            <tr>
                                                <td><strong>Supplier:</strong></td>
                                                <td>${grn.supplier?.name || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <h6 class="text-primary mb-3">Quality Inspection Results</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>SKU</th>
                                                <th>Ordered</th>
                                                <th>Received</th>
                                                <th>Accepted</th>
                                                <th>Rejected</th>
                                                <th>Unit Cost</th>
                                                <th>Line Total</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${(grn.items || []).map(item => {
                                                let statusBadge = '';
                                                const ordered = item.po_details?.quantity_ordered || 0;
                                                const received = item.quantity_received;

                                                if (item.quantity_accepted === 0) {
                                                    statusBadge = '<span class="badge bg-danger">Rejected</span>';
                                                } else if (received > ordered) {
                                                    statusBadge = '<span class="badge bg-warning">Over Received</span>';
                                                } else if (item.quantity_accepted < received) {
                                                    statusBadge = '<span class="badge bg-warning">Partial Accept</span>';
                                                } else {
                                                    statusBadge = '<span class="badge bg-success">Accepted</span>';
                                                }

                                                return `
                                                    <tr>
                                                        <td>${item.product?.name || 'Unknown'}</td>
                                                        <td><code>${item.product?.sku || 'N/A'}</code></td>
                                                        <td>${ordered}</td>
                                                        <td>${received}</td>
                                                        <td><strong class="text-success">${item.quantity_accepted}</strong></td>
                                                        <td><span class="text-danger">${item.quantity_rejected}</span></td>
                                                        <td>${formatCurrency(item.unit_cost)}</td>
                                                        <td><strong>${formatCurrency(item.line_total)}</strong></td>
                                                        <td>${statusBadge}</td>
                                                    </tr>
                                                `;
                                            }).join('')}
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Inspection Summary -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="border rounded p-3 bg-light">
                                            <h6 class="text-primary">Inspection Summary</h6>
                                            <div class="row">
                                                <div class="col-6">
                                                    <small>Total Received:</small>
                                                    <div class="h5 text-primary">${grn.totals?.quantity_received || 0}</div>
                                                </div>
                                                <div class="col-6">
                                                    <small>Total Accepted:</small>
                                                    <div class="h5 text-success">${grn.totals?.quantity_accepted || 0}</div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-6">
                                                    <small>Total Rejected:</small>
                                                    <div class="h6 text-danger">${grn.totals?.quantity_rejected || 0}</div>
                                                </div>
                                                <div class="col-6">
                                                    <small>Total Value:</small>
                                                    <div class="h6 text-info">${formatCurrency(grn.totals?.total_value || 0)}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        ${grn.notes ? `
                                            <h6>Delivery Notes</h6>
                                            <p class="text-muted">${grn.notes}</p>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer" style="border-color: var(--border-color);">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button onclick="editGRN(${grn.id})" class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i>Edit GRN
                                </button>
                                ${grn.inspection_status !== 'completed' ? `
                                    <button onclick="finishGRN(${grn.id})" class="btn btn-success">
                                        <i class="fas fa-check-double me-2"></i>Complete
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if present
            const existingModal = document.getElementById('viewGRNModal');
            if (existingModal) {
                existingModal.remove();
            }

            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalContent);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('viewGRNModal'));
            modal.show();

            // Clean up modal when hidden
            document.getElementById('viewGRNModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        } else {
            showError('Failed to load GRN details');
        }
    } catch (error) {
        devLog('Error loading GRN details:', error);
        showError('Failed to load GRN details');
    }
}

async function editGRN(id) {
    await openGRNModal(id);
}

async function finishGRN(id) {
    if (!await showConfirm('Completing this GRN will finalize the inspection and update inventory. This action cannot be undone.', {
        title: 'Complete Goods Receiving',
        confirmText: 'Complete & Update Inventory',
        type: 'success',
        icon: 'fas fa-check-double'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/grn/complete.php?id=${id}`, {
            method: 'POST'
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('GRN completed successfully and inventory updated');
            await loadGRNs();
        } else {
            showError(result.message || 'Failed to complete GRN');
        }
    } catch (error) {
        showError('Failed to complete GRN');
    }
}

async function deleteGRN(id) {
    if (!await showConfirm('Are you sure you want to delete this goods received note? This action cannot be undone.', {
        title: 'Delete Goods Received Note',
        confirmText: 'Delete',
        type: 'danger',
        icon: 'fas fa-trash'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/grn/delete.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('GRN deleted successfully');
            await loadGRNs();
        } else {
            showError(result.message || 'Failed to delete GRN');
        }
    } catch (error) {
        console.error('Error deleting GRN:', error);
        showError('Failed to delete GRN');
    }
}

function viewPOFromGRN(poId) {
    if (window.currentUser?.role === 'supplier') {
        showPage('po-history');
        // Could scroll to the specific PO, but for now just navigate to history
    } else {
        showPage('purchase-orders');
    }
}

async function loadPOSPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-cash-register me-2"></i>Point of Sale</h1>
            <p class="page-subtitle">Process direct customer sales transactions</p>
        </div>

        <!-- Transaction Summary Row -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="today-sales">₱0</div>
                            <div class="stat-label">Today's Sales</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span id="sales-change">+0%</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="today-transactions">0</div>
                            <div class="stat-label">Transactions</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-neutral">
                        <i class="fas fa-minus"></i>
                        <span id="transactions-change">0</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="avg-transaction">₱0</div>
                            <div class="stat-label">Avg Transaction</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">
                            <i class="fas fa-calculator"></i>
                        </div>
                    </div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span id="avg-change">+0%</span>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="monthly-target">₱0</div>
                            <div class="stat-label">Monthly Target</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(255, 170, 0, 0.1); color: var(--warning);">
                            <i class="fas fa-bullseye"></i>
                        </div>
                    </div>
                    <div class="progress-bar mt-2" style="height: 6px;">
                        <div class="progress-fill" style="width: 0%; background: var(--warning);"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Product Selection Panel -->
            <div class="col-lg-8">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Product Selection</h5>
                            <div class="d-flex gap-2">
                                <select id="pos-product-category-filter" class="form-select form-select-sm" onchange="filterPOSProducts()">
                                    <option value="all">All Categories</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" onclick="refreshProductGrid()">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Search Bar -->
                        <div class="mb-3">
                            <input type="text" id="pos-product-search" class="form-control"
                                   placeholder="Search products by name or SKU..." onkeyup="filterPOSProducts()">
                        </div>

                        <!-- Product Grid -->
                        <div id="products-grid" class="row g-3 overflow-auto" style="max-height: 500px;">
                            <!-- Products will be loaded here -->
                        </div>

                        <div id="no-products-message" class="text-center py-5 d-none">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No products found matching your search.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Panel -->
            <div class="col-lg-4">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Current Transaction</h5>
                            <button class="btn btn-sm btn-outline-danger" onclick="clearTransaction()">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Customer Info -->
                        <div class="mb-3 p-3 border rounded" style="background: var(--bg-secondary);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0"><i class="fas fa-user me-1"></i>Customer</h6>
                                <button class="btn btn-sm btn-outline-primary" onclick="addCustomer()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div id="customer-info" class="text-muted small">
                                Walk-in Customer
                            </div>
                        </div>

                        <!-- Transaction Items -->
                        <div id="transaction-items" class="mb-3" style="max-height: 300px; overflow-y: auto;">
                            <div id="empty-cart-message" class="text-center text-muted py-4">
                                <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                <p>No items in cart</p>
                            </div>
                        </div>

                        <!-- Transaction Summary -->
                        <div id="transaction-summary" class="border-top pt-3 d-none">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span id="subtotal-amount">₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax (12%):</span>
                                <span id="tax-amount">₱0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-3 fw-bold">
                                <span>Total:</span>
                                <span id="total-amount">₱0.00</span>
                            </div>

                            <!-- Payment Section -->
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select id="payment-method" class="form-select">
                                    <option value="cash">Cash</option>
                                    <option value="card">Card</option>
                                    <option value="online">Online Payment</option>
                                </select>
                            </div>

                            <div id="cash-payment-section" class="mb-3">
                                <label class="form-label">Cash Received</label>
                                <input type="number" id="cash-received" class="form-control" min="0" step="0.01" placeholder="0.00">
                                <div class="mt-2">
                                    <small class="text-muted">Change: <strong id="change-amount">₱0.00</strong></small>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-lg" onclick="processPayment()">
                                    <i class="fas fa-credit-card me-2"></i>Complete Sale
                                </button>
                                <button class="btn btn-warning" onclick="holdTransaction()">
                                    <i class="fas fa-pause me-2"></i>Hold Transaction
                                </button>
                            </div>
                        </div>

                        <div id="transaction-message" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction History Panel -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                    <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Today's Transactions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Transaction #</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="transactions-history">
                                    <!-- Transactions will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Initialize POS system
    await initializePOS();

    // Load initial data
    await loadProductsForPOS();
    await loadCategoriesForPOS();
    await loadTransactionHistory();

    // Set up event listeners
    setUpPOSEventListeners();
}

let posState = {
    cart: [],
    customer: null,
    currentTransaction: null
};

let allPOSProducts = [];
let posCategories = [];

async function initializePOS() {
    // Reset POS state
    posState.cart = [];
    posState.customer = { id: null, name: 'Walk-in Customer' };
    posState.currentTransaction = null;

    // Initialize UI elements
    updateCartDisplay();
}

async function loadProductsForPOS() {
    try {
        const response = await fetch(`${API_BASE}/products/index.php`);
        const data = await response.json();

        if (data.success) {
            allPOSProducts = data.products || [];
            renderProductGrid(allPOSProducts);
        } else {
            showPOSMessage('Error loading products: ' + (data.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        devLog('Error loading products for POS:', error);
        showPOSMessage('Failed to load products', 'error');
    }
}

async function loadCategoriesForPOS() {
    try {
        const response = await fetch(`${API_BASE}/categories/index.php`);
        const data = await response.json();

        if (data.success) {
            posCategories = data.data.categories || [];
            const select = document.getElementById('pos-product-category-filter');
            if (select) {
                select.innerHTML = '<option value="all">All Categories</option>';

                posCategories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category.id;
                    option.textContent = category.name;
                    select.appendChild(option);
                });
            }
        }
    } catch (error) {
        devLog('Error loading categories:', error);
    }
}

function renderProductGrid(products) {
    const grid = document.getElementById('products-grid');
    const noProducts = document.getElementById('no-products-message');

    if (products.length === 0) {
        grid.innerHTML = '';
        noProducts.classList.remove('d-none');
        return;
    }

    noProducts.classList.add('d-none');
    grid.innerHTML = products.map(product => `
        <div class="col-md-3 col-sm-4">
            <div class="pos-product-card card h-100" style="background: var(--bg-secondary); border: 1px solid var(--border-color); cursor: pointer;" onclick="addToCart(${product.id})">
                <div class="card-body p-2 text-center">
                    ${product.image_url ? `
                        <img src="${API_BASE}/../uploads/products/${product.image_url}"
                             alt="${product.name}"
                             class="img-fluid mb-2"
                             style="max-height: 80px; object-fit: contain;">
                    ` : `
                        <div class="bg-light mb-2 d-flex align-items-center justify-content-center" style="height: 80px;">
                            <i class="fas fa-image text-muted"></i>
                        </div>
                    `}
                    <h6 class="card-title mb-1 text-truncate" title="${product.name}">${product.name}</h6>
                    <p class="card-text small mb-2"><strong>${formatCurrency(product.selling_price)}</strong></p>
                    <small class="text-muted">${product.stock_quantity} in stock</small>
                </div>
            </div>
        </div>
    `).join('');
}

function filterPOSProducts() {
    const searchTerm = document.getElementById('pos-product-search')?.value.toLowerCase() || '';
    const categoryFilter = document.getElementById('pos-product-category-filter')?.value || 'all';

    let filtered = allPOSProducts;

    if (searchTerm) {
        filtered = filtered.filter(product =>
            product.name.toLowerCase().includes(searchTerm) ||
            (product.sku && product.sku.toLowerCase().includes(searchTerm))
        );
    }

    if (categoryFilter !== 'all') {
        filtered = filtered.filter(product => product.category_id == categoryFilter);
    }

    renderProductGrid(filtered);
}

function addToCart(productId) {
    const product = allPOSProducts.find(p => p.id == productId);
    if (!product) {
        showPOSMessage('Product not found', 'error');
        return;
    }

    if (product.stock_quantity <= 0) {
        showPOSMessage('Product is out of stock', 'error');
        return;
    }

    // Check if product is already in cart
    const existingItem = posState.cart.find(item => item.product_id === productId);

    if (existingItem) {
        existingItem.quantity += 1;
        if (existingItem.quantity > product.stock_quantity) {
            showPOSMessage('Cannot add more items - insufficient stock', 'error');
            existingItem.quantity = product.stock_quantity;
        }
    } else {
        posState.cart.push({
            product_id: productId,
            name: product.name,
            sku: product.sku,
            price: parseFloat(product.selling_price),
            quantity: 1,
            max_quantity: product.stock_quantity
        });
    }

    updateCartDisplay();
    showPOSMessage(`Added ${product.name} to cart`, 'success');
}

function updateCartItemQuantity(productId, newQuantity) {
    const item = posState.cart.find(item => item.product_id == productId);
    if (!item) return;

    newQuantity = Math.max(0, Math.min(newQuantity, item.max_quantity));
    if (newQuantity === 0) {
        removeFromCart(productId);
    } else {
        item.quantity = newQuantity;
        updateCartDisplay();
    }
}

function removeFromCart(productId) {
    posState.cart = posState.cart.filter(item => item.product_id != productId);
    updateCartDisplay();
}

function updateCartDisplay() {
    const cartItems = document.getElementById('transaction-items');
    const cartSummary = document.getElementById('transaction-summary');
    const emptyCart = document.getElementById('empty-cart-message');

    if (posState.cart.length === 0) {
        emptyCart.classList.remove('d-none');
        cartSummary.classList.add('d-none');
        cartItems.innerHTML = '<div id="empty-cart-message" class="text-center text-muted py-4"><i class="fas fa-shopping-cart fa-2x mb-2"></i><p>No items in cart</p></div>';
        return;
    }

    emptyCart.classList.add('d-none');
    cartSummary.classList.remove('d-none');

    cartItems.innerHTML = posState.cart.map(item => `
        <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
            <div class="flex-grow-1 me-2">
                <div class="fw-bold">${item.name}</div>
                <small class="text-muted">${item.sku}</small>
                <div class="input-group input-group-sm mt-1" style="width: 120px;">
                    <button class="btn btn-outline-secondary btn-sm" onclick="updateCartItemQuantity(${item.product_id}, ${item.quantity - 1})">-</button>
                    <input type="number" class="form-control form-control-sm text-center" value="${item.quantity}"
                           onchange="updateCartItemQuantity(${item.product_id}, parseInt(this.value) || 0)" min="1" max="${item.max_quantity}">
                    <button class="btn btn-outline-secondary btn-sm" onclick="updateCartItemQuantity(${item.product_id}, ${item.quantity + 1})">+</button>
                </div>
            </div>
            <div class="text-end">
                <div class="fw-bold">${formatCurrency(item.price * item.quantity)}</div>
                <button class="btn btn-sm btn-outline-danger mt-1" onclick="removeFromCart(${item.product_id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');

    updateTransactionSummary();
}

function updateTransactionSummary() {
    const subtotal = posState.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const taxRate = 0.12; // 12% VAT
    const tax = subtotal * taxRate;
    const total = subtotal + tax;

    document.getElementById('subtotal-amount').textContent = formatCurrency(subtotal);
    document.getElementById('tax-amount').textContent = formatCurrency(tax);
    document.getElementById('total-amount').textContent = formatCurrency(total);

    // Update cash change calculation
    calculateCashChange();
}

function calculateCashChange() {
    const cashReceivedEl = document.getElementById('cash-received');
    const changeEl = document.getElementById('change-amount');
    const paymentMethod = document.getElementById('payment-method').value;

    if (paymentMethod !== 'cash') {
        cashReceivedEl.closest('#cash-payment-section').classList.add('d-none');
        return;
    }

    cashReceivedEl.closest('#cash-payment-section').classList.remove('d-none');
    const cashReceived = parseFloat(cashReceivedEl.value) || 0;
    const total = parseFloat(document.getElementById('total-amount').textContent.replace('₱', '').replace(/,/g, ''));

    const change = cashReceived - total;
    changeEl.textContent = change >= 0 ? formatCurrency(change) : '₱0.00';

    if (change < 0) {
        changeEl.style.color = 'red';
    } else {
        changeEl.style.color = 'green';
    }
}

async function processPayment() {
    if (posState.cart.length === 0) {
        showPOSMessage('No items in cart', 'error');
        return;
    }

    const totalAmount = parseFloat(document.getElementById('total-amount').textContent.replace('₱', '').replace(/,/g, ''));
    const paymentMethod = document.getElementById('payment-method').value;
    const cashReceived = parseFloat(document.getElementById('cash-received').value) || 0;

    if (paymentMethod === 'cash') {
        if (cashReceived < totalAmount) {
            showPOSMessage('Insufficient cash received', 'error');
            return;
        }
    }

    try {
        const transactionData = {
            customer_id: posState.customer.id,
            items: posState.cart.map(item => ({
                product_id: item.product_id,
                quantity: item.quantity,
                unit_price: item.price,
                total_price: item.price * item.quantity
            })),
            total_amount: totalAmount,
            payment_method: paymentMethod,
            cash_received: paymentMethod === 'cash' ? cashReceived : null,
            change_given: paymentMethod === 'cash' ? (cashReceived - totalAmount) : null,
            notes: null
        };

        const response = await fetch(`${API_BASE}/pos/create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(transactionData)
        });

        const result = await response.json();

        if (result.success) {
            showPOSMessage(`Transaction completed successfully! Receipt #${result.data.transaction_number}`, 'success');

            // Reset POS state
            posState.cart = [];
            posState.currentTransaction = null;
            updateCartDisplay();
            await loadTransactionHistory();

            // Reset payment fields
            document.getElementById('payment-method').value = 'cash';
            document.getElementById('cash-received').value = '';

        } else {
            showPOSMessage(result.message || 'Failed to process payment', 'error');
        }

    } catch (error) {
        devLog('Error processing payment:', error);
        showPOSMessage('Failed to process payment', 'error');
    }
}

function holdTransaction() {
    if (posState.cart.length === 0) {
        showPOSMessage('No items to hold', 'error');
        return;
    }

    // For now, just store in local storage
    const heldTransaction = {
        cart: [...posState.cart],
        customer: { ...posState.customer },
        timestamp: new Date().toISOString()
    };

    localStorage.setItem('held_pos_transaction', JSON.stringify(heldTransaction));
    clearTransaction();
    showPOSMessage('Transaction held successfully', 'success');
}

function clearTransaction() {
    posState.cart = [];
    posState.currentTransaction = null;
    updateCartDisplay();
    showPOSMessage('Transaction cleared', 'info');
}

function addCustomer() {
    showPOSMessage('Customer management feature coming soon', 'info');
    // TODO: Implement customer selection/addition modal
}

async function loadTransactionHistory() {
    try {
        const response = await fetch(`${API_BASE}/pos/transactions.php?limit=20`);
        const data = await response.json();

        if (data.success) {
            renderTransactionHistory(data.data.transactions || []);
        }
    } catch (error) {
        devLog('Error loading transaction history:', error);
    }
}

function renderTransactionHistory(transactions) {
    const tbody = document.getElementById('transactions-history');

    if (transactions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No transactions today</td></tr>';
        return;
    }

    tbody.innerHTML = transactions.map(transaction => `
        <tr>
            <td>${new Date(transaction.created_at).toLocaleTimeString()}</td>
            <td><strong>${transaction.transaction_number}</strong></td>
            <td>${transaction.customer_name || 'Walk-in'}</td>
            <td>${transaction.item_count} item${transaction.item_count === 1 ? '' : 's'}</td>
            <td>${formatCurrency(transaction.total_amount)}</td>
            <td><span class="badge bg-info">${transaction.payment_method}</span></td>
            <td><span class="badge bg-success">Completed</span></td>
            <td>
                <button onclick="viewTransaction(${transaction.id})" class="btn btn-sm btn-outline-info">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function viewTransaction(id) {
    showPOSMessage('Transaction view feature coming soon', 'info');
    // TODO: Implement transaction details modal
}

function refreshProductGrid() {
    renderProductGrid(allPOSProducts);
}

function setUpPOSEventListeners() {
    // Payment method change
    document.getElementById('payment-method').addEventListener('change', calculateCashChange);

    // Cash received input
    document.getElementById('cash-received').addEventListener('input', calculateCashChange);

    // Category filter
    document.getElementById('product-category-filter').addEventListener('change', filterProducts);

    // Product search debounced
    const searchInput = document.getElementById('product-search');
    searchInput.addEventListener('input', debounce(filterProducts, 300));
}

function showPOSMessage(message, type = 'info') {
    const messageEl = document.getElementById('transaction-message');

    const alertClass = {
        'success': 'alert-success',
        'error': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    }[type] || 'alert-info';

    messageEl.innerHTML = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;

    // Auto-hide after 5 seconds
    setTimeout(() => {
        messageEl.innerHTML = '';
    }, 5000);
}

// Global debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

async function loadSalesPage() {
    const content = document.getElementById('page-content');
    const isStaff = currentUser.role === 'staff';

    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-receipt me-2"></i>Sales History</h1>
            <p class="page-subtitle">View and manage sales transactions</p>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="total-sales-count">0</div>
                            <div class="stat-label">Total Sales</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="total-revenue">₱0</div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="avg-sale-value">₱0</div>
                            <div class="stat-label">Avg Sale Value</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">
                            <i class="fas fa-calculator"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="today-sales-count">0</div>
                            <div class="stat-label">Today's Sales</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: var(--warning);">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" id="sales-date-from" class="form-control" value="${new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" id="sales-date-to" class="form-control" value="${new Date().toISOString().split('T')[0]}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Payment Method</label>
                        <select id="sales-payment-filter" class="form-select">
                            <option value="all">All Methods</option>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="gcash">GCash</option>
                            <option value="paymaya">PayMaya</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" id="sales-search" class="form-control" placeholder="Sale number, customer...">
                    </div>
                </div>
                <div class="mt-3">
                    <button onclick="loadSalesHistory()" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                    <button onclick="resetSalesFilters()" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i>Reset
                    </button>
                    ${!isStaff ? `
                    <button onclick="exportSales()" class="btn btn-outline-success">
                        <i class="fas fa-file-excel me-2"></i>Export
                    </button>
                    ` : ''}
                </div>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Sales Transactions</h5>
            </div>
            <div class="card-body">
                <div id="sales-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading sales...</p>
                </div>

                <div id="sales-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Sale #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="sales-table-body">
                                <!-- Sales will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-sales-message" class="text-center py-5 d-none">
                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Sales Found</h5>
                    <p class="text-muted">Try adjusting your search filters.</p>
                </div>
            </div>
        </div>
    `;

    // Load initial sales
    await loadSalesHistory();
}

async function loadSalesHistory() {
    const loadingIndicator = document.getElementById('sales-loading');
    const content = document.getElementById('sales-content');
    const noSalesMessage = document.getElementById('no-sales-message');

    try {
        loadingIndicator.classList.remove('d-none');
        content.classList.add('d-none');
        noSalesMessage.classList.add('d-none');

        // Get filter values
        const dateFrom = document.getElementById('sales-date-from').value;
        const dateTo = document.getElementById('sales-date-to').value;
        const paymentFilter = document.getElementById('sales-payment-filter').value;
        const searchTerm = document.getElementById('sales-search').value;

        // Build URL with filters
        let url = `${API_BASE}/sales/index.php?`;
        const params = [];
        if (dateFrom) params.push(`date_from=${dateFrom}`);
        if (dateTo) params.push(`date_to=${dateTo}`);
        if (paymentFilter !== 'all') params.push(`payment_method=${paymentFilter}`);
        if (searchTerm) params.push(`search=${encodeURIComponent(searchTerm)}`);

        url += params.join('&');

        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin'
        });
        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.data && data.data.length > 0) {
            displaySalesHistory(data.data);
            updateSalesStats(data.data);
            content.classList.remove('d-none');
        } else {
            noSalesMessage.classList.remove('d-none');
        }
    } catch (error) {
        devLog('Error loading sales:', error);
        loadingIndicator.classList.add('d-none');
        noSalesMessage.classList.remove('d-none');
        showError('Failed to load sales history');
    }
}

function displaySalesHistory(sales) {
    const tbody = document.getElementById('sales-table-body');
    const isStaff = currentUser.role === 'staff';

    tbody.innerHTML = sales.map(sale => {
        const statusBadge = sale.payment_status === 'paid'
            ? '<span class="badge bg-success">Paid</span>'
            : '<span class="badge bg-warning">Pending</span>';

        return `
            <tr>
                <td><strong>${sale.sale_number}</strong></td>
                <td>${new Date(sale.sale_date).toLocaleDateString()}<br>
                    <small class="text-muted">${new Date(sale.sale_date).toLocaleTimeString()}</small>
                </td>
                <td>${sale.customer_name || 'Walk-in'}</td>
                <td>${sale.item_count || 0} items</td>
                <td><strong>${formatCurrency(sale.total_amount)}</strong></td>
                <td><span class="badge bg-info">${sale.payment_method}</span></td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button onclick="viewSaleDetails(${sale.id})" class="btn btn-outline-info" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="printSaleReceipt(${sale.id})" class="btn btn-outline-primary" title="Print Receipt">
                            <i class="fas fa-print"></i>
                        </button>
                        ${!isStaff ? `
                        <button onclick="deleteSale(${sale.id})" class="btn btn-outline-danger" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function updateSalesStats(sales) {
    const totalCount = sales.length;
    const totalRevenue = sales.reduce((sum, sale) => sum + parseFloat(sale.total_amount || 0), 0);
    const avgValue = totalCount > 0 ? totalRevenue / totalCount : 0;
    const today = new Date().toDateString();
    const todayCount = sales.filter(sale => new Date(sale.sale_date).toDateString() === today).length;

    document.getElementById('total-sales-count').textContent = totalCount;
    document.getElementById('total-revenue').textContent = formatCurrency(totalRevenue);
    document.getElementById('avg-sale-value').textContent = formatCurrency(avgValue);
    document.getElementById('today-sales-count').textContent = todayCount;
}

function resetSalesFilters() {
    document.getElementById('sales-date-from').value = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    document.getElementById('sales-date-to').value = new Date().toISOString().split('T')[0];
    document.getElementById('sales-payment-filter').value = 'all';
    document.getElementById('sales-search').value = '';
    loadSalesHistory();
}

async function viewSaleDetails(saleId) {
    try {
        const response = await fetch(`${API_BASE}/sales/show.php?id=${saleId}`);
        const data = await response.json();

        if (data.success) {
            showSaleDetailsModal(data.data);
        } else {
            showError('Failed to load sale details');
        }
    } catch (error) {
        devLog('Error loading sale details:', error);
        showError('Failed to load sale details');
    }
}

function showSaleDetailsModal(sale) {
    const modalHTML = `
        <div class="modal fade" id="saleDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title"><i class="fas fa-receipt me-2"></i>Sale Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Sale Information</h6>
                                <p><strong>Sale Number:</strong> ${sale.sale_number}</p>
                                <p><strong>Date:</strong> ${new Date(sale.sale_date).toLocaleString()}</p>
                                <p><strong>Customer:</strong> ${sale.customer_name || 'Walk-in'}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Payment Information</h6>
                                <p><strong>Payment Method:</strong> ${sale.payment_method}</p>
                                <p><strong>Status:</strong> ${sale.payment_status}</p>
                                <p><strong>Created By:</strong> ${sale.created_by_name || 'N/A'}</p>
                            </div>
                        </div>

                        <h6>Items</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>Qty</th>
                                        <th>Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${(sale.items || []).map(item => `
                                        <tr>
                                            <td>${item.product_name}</td>
                                            <td><code>${item.sku || 'N/A'}</code></td>
                                            <td>${item.quantity}</td>
                                            <td>${formatCurrency(item.unit_price)}</td>
                                            <td>${formatCurrency(item.quantity * item.unit_price)}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="4" class="text-end">Total:</th>
                                        <th>${formatCurrency(sale.total_amount)}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="printSaleReceipt(${sale.id})">
                            <i class="fas fa-print me-2"></i>Print Receipt
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existingModal = document.getElementById('saleDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to document
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('saleDetailsModal'));
    modal.show();
}

function printSaleReceipt(saleId) {
    window.open(`${API_BASE}/sales/receipt.php?id=${saleId}`, '_blank');
}

async function deleteSale(saleId) {
    if (await showConfirm('Are you sure you want to delete this sale?', {
        title: 'Delete Sale',
        confirmText: 'Delete',
        type: 'danger',
        icon: 'fas fa-trash'
    })) {
        try {
            const response = await fetch(`${API_BASE}/sales/delete.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: saleId }),
                credentials: 'same-origin'
            });
            const data = await response.json();

            if (data.success) {
                showSuccess('Sale deleted successfully');
                loadSalesHistory();
            } else {
                showError(data.message || 'Failed to delete sale');
            }
        } catch (error) {
            devLog('Error deleting sale:', error);
            showError('Failed to delete sale');
        }
    }
}

function exportSales() {
    const dateFrom = document.getElementById('sales-date-from').value;
    const dateTo = document.getElementById('sales-date-to').value;
    const paymentFilter = document.getElementById('sales-payment-filter').value;

    let url = `${API_BASE}/sales/export-csv.php?`;
    const params = [];
    if (dateFrom) params.push(`date_from=${dateFrom}`);
    if (dateTo) params.push(`date_to=${dateTo}`);
    if (paymentFilter !== 'all') params.push(`payment_method=${paymentFilter}`);

    window.open(url + params.join('&'), '_blank');
}

async function loadUsersPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-users me-2"></i>User Management</h1>
            <p class="page-subtitle">Manage system users and permissions</p>
        </div>

        <!-- User Stats -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="total-users">0</div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="active-users">0</div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="inactive-users">0</div>
                            <div class="stat-label">Inactive Users</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                            <i class="fas fa-user-times"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" id="admin-users">0</div>
                            <div class="stat-label">Administrators</div>
                        </div>
                        <div class="stat-icon" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex gap-3 flex-wrap">
                <select id="user-role-filter" class="form-select">
                    <option value="all">All Roles</option>
                    <option value="admin">Administrators</option>
                    <option value="inventory_manager">Inventory Managers</option>
                    <option value="purchasing_officer">Purchasing Officers</option>
                    <option value="staff">Staff</option>
                </select>

                <select id="user-status-filter" class="form-select">
                    <option value="all">All Status</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>

                <input type="text" id="user-search" class="form-control" placeholder="Search users..." style="width: 200px;">
            </div>
            <button class="btn btn-primary" onclick="openUserModal()">
                <i class="fas fa-user-plus me-2"></i>Add User
            </button>
        </div>

        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>System Users</h5>
            </div>
            <div class="card-body">
                <div id="users-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading users...</p>
                </div>

                <div id="users-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-table-body">
                                <!-- Users will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-users-message" class="text-center py-5 d-none">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Users Found</h5>
                    <p class="text-muted">Start by adding your first user.</p>
                    <button class="btn btn-primary" onclick="openUserModal()">
                        <i class="fas fa-user-plus me-2"></i>Add First User
                    </button>
                </div>
            </div>
        </div>

        <!-- Pending Suppliers Section -->
        <div class="card mt-4" style="background: var(--bg-card); border: 1px solid var(--border-color);">
            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Supplier Approvals</h5>
            </div>
            <div class="card-body">
                <div id="pending-suppliers-loading" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading pending suppliers...</p>
                </div>

                <div id="pending-suppliers-content" class="d-none">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Company</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Applied Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pending-suppliers-table-body">
                                <!-- Pending suppliers will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="no-pending-suppliers-message" class="text-center py-3 d-none">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <p class="text-muted">No pending supplier approvals.</p>
                </div>
            </div>
        </div>
    `;

    // Load users and pending suppliers
    await loadUsers();
    await loadPendingSuppliers();

    // Add event listeners
    document.getElementById('user-role-filter').addEventListener('change', () => loadUsers());
    document.getElementById('user-status-filter').addEventListener('change', () => loadUsers());
    document.getElementById('user-search').addEventListener('input', debounce(() => loadUsers(), 500));
}

let allUsers = [];

async function loadUsers() {
    const loadingIndicator = document.getElementById('users-loading');
    const content = document.getElementById('users-content');
    const noUsersMessage = document.getElementById('no-users-message');
    const tbody = document.getElementById('users-table-body');

    const roleFilter = document.getElementById('user-role-filter').value;
    const statusFilter = document.getElementById('user-status-filter').value;
    const searchFilter = document.getElementById('user-search').value.trim();

    try {
        loadingIndicator.classList.remove('d-none');
        content.classList.add('d-none');
        noUsersMessage.classList.add('d-none');

        let url = `${API_BASE}/users/index.php`;

        const response = await fetch(url);
        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.users) {
            allUsers = data.users || [];

            // Apply client-side filtering
            let filtered = allUsers.filter(user => {
                // Role filter
                if (roleFilter !== 'all' && user.role !== roleFilter) return false;

                // Status filter
                if (statusFilter !== 'all') {
                    if (statusFilter === 'active' && user.is_active != 1) return false;
                    if (statusFilter === 'inactive' && user.is_active == 1) return false;
                }

                // Search filter
                if (searchFilter) {
                    const searchLower = searchFilter.toLowerCase();
                    if (!(user.username?.toLowerCase().includes(searchLower) ||
                          user.full_name?.toLowerCase().includes(searchLower) ||
                          user.email?.toLowerCase().includes(searchLower))) {
                        return false;
                    }
                }

                return true;
            });

            displayUsers(filtered);
            content.classList.remove('d-none');
            updateUserStats(allUsers);
        } else {
            noUsersMessage.classList.remove('d-none');
            updateUserStats([]);
        }
    } catch (error) {
        devLog('Error loading users:', error);
        loadingIndicator.classList.add('d-none');
        showError('Failed to load users. Please try again.');
    }
}

function displayUsers(users) {
    const tbody = document.getElementById('users-table-body');
    tbody.innerHTML = users.map(user => {
        const statusBadge = user.is_active == 1
            ? '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Active</span>'
            : '<span class="badge bg-secondary"><i class="fas fa-times me-1"></i>Inactive</span>';

        const roleBadge = getRoleBadge(user.role);

        const lastLogin = user.last_login ? formatDate(user.last_login) : 'Never';

        return `
            <tr>
                <td>
                    <strong>${user.username}</strong>
                </td>
                <td>${user.full_name || '—'}</td>
                <td>
                    <a href="mailto:${user.email}">${user.email}</a>
                </td>
                <td>${roleBadge}</td>
                <td>${statusBadge}</td>
                <td class="text-muted small">${lastLogin}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button onclick="viewUser(${user.id})" class="btn btn-outline-info" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="editUser(${user.id})" class="btn btn-outline-primary" title="Edit User">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="toggleUserStatus(${user.id}, ${user.is_active})" class="btn btn-outline-${user.is_active == 1 ? 'danger' : 'success'}" title="${user.is_active == 1 ? 'Deactivate' : 'Activate'}">
                            <i class="fas fa-${user.is_active == 1 ? 'ban' : 'check'}"></i>
                        </button>
                        <button onclick="resetUserPassword(${user.id})" class="btn btn-outline-warning" title="Reset Password">
                            <i class="fas fa-key"></i>
                        </button>
                        <button onclick="deleteUser(${user.id})" class="btn btn-outline-danger" title="Delete User">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function getRoleBadge(role) {
    const badges = {
        'admin': '<span class="badge bg-danger"><i class="fas fa-crown me-1"></i>Administrator</span>',
        'inventory_manager': '<span class="badge bg-primary"><i class="fas fa-warehouse me-1"></i>Inventory Manager</span>',
        'purchasing_officer': '<span class="badge bg-info"><i class="fas fa-shopping-cart me-1"></i>Purchasing Officer</span>',
        'staff': '<span class="badge bg-secondary"><i class="fas fa-user me-1"></i>Staff</span>'
    };
    return badges[role] || '<span class="badge bg-light text-dark">Unknown</span>';
}

function updateUserStats(users) {
    const totalUsers = users.length;
    const activeUsers = users.filter(u => u.is_active == 1).length;
    const inactiveUsers = totalUsers - activeUsers;
    const adminUsers = users.filter(u => u.role === 'admin').length;

    document.getElementById('total-users').textContent = totalUsers;
    document.getElementById('active-users').textContent = activeUsers;
    document.getElementById('inactive-users').textContent = inactiveUsers;
    document.getElementById('admin-users').textContent = adminUsers;
}

async function loadPendingSuppliers() {
    const loadingIndicator = document.getElementById('pending-suppliers-loading');
    const content = document.getElementById('pending-suppliers-content');
    const noPendingMessage = document.getElementById('no-pending-suppliers-message');
    const tbody = document.getElementById('pending-suppliers-table-body');

    try {
        loadingIndicator.classList.remove('d-none');
        content.classList.add('d-none');
        noPendingMessage.classList.add('d-none');

        const response = await fetch(`${API_BASE}/users/pending-suppliers.php`);
        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.data && data.data.suppliers && data.data.suppliers.length > 0) {
            tbody.innerHTML = data.data.suppliers.map(supplier => `
                <tr>
                    <td>
                        <strong>${supplier.username}</strong>
                    </td>
                    <td>${supplier.name || '—'}</td>
                    <td>
                        <a href="mailto:${supplier.email}">${supplier.email}</a>
                    </td>
                    <td>${supplier.phone || '—'}</td>
                    <td>${formatDate(supplier.created_at)}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button onclick="approveSupplierFromUsers(${supplier.id})" class="btn btn-outline-success" title="Approve Supplier">
                                <i class="fas fa-check"></i>
                            </button>
                            <button onclick="rejectSupplierFromUsers(${supplier.id})" class="btn btn-outline-danger" title="Reject Supplier">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join('');
            content.classList.remove('d-none');
        } else {
            noPendingMessage.classList.remove('d-none');
        }
    } catch (error) {
        devLog('Error loading pending suppliers:', error);
        loadingIndicator.classList.add('d-none');
    }
}

async function openUserModal(userId = null) {
    const modalContent = `
        <div class="modal fade" id="userModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title"><i class="fas fa-user me-2"></i><span id="user-modal-title">Add User</span></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="userForm">
                            <input type="hidden" id="user-id">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="user-username" required>
                                    <div class="form-text">Unique login username</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="user-full-name" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="user-email" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Role *</label>
                                    <select class="form-select" id="user-role" required>
                                        <option value="">Select Role</option>
                                        <option value="admin">Administrator</option>
                                        <option value="inventory_manager">Inventory Manager</option>
                                        <option value="purchasing_officer">Purchasing Officer</option>
                                        <option value="staff">Staff</option>
                                    </select>
                                </div>
                            </div>
                            <div id="password-fields" class="mb-3">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Password *</label>
                                        <input type="password" class="form-control" id="user-password" required minlength="6">
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Confirm Password *</label>
                                        <input type="password" class="form-control" id="user-confirm-password" required minlength="6">
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="user-active" checked>
                                <label class="form-check-label" for="user-active">
                                    Active User Account
                                </label>
                                <div class="form-text">Inactive users cannot log in to the system</div>
                            </div>
                            <div id="user-message"></div>
                        </form>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveUserBtn">
                            <i class="fas fa-save me-2"></i>Save User
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('userModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalContent);

    if (userId) {
        document.getElementById('user-modal-title').textContent = 'Edit User';
        document.getElementById('saveUserBtn').textContent = 'Update User';
        await loadUserForEdit(userId);
    } else {
        resetUserForm();
    }

    // Add event listener for save button
    document.getElementById('saveUserBtn').addEventListener('click', () => saveUser(userId));

    const modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();

    // Clean up modal when hidden
    document.getElementById('userModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

function resetUserForm() {
    document.getElementById('user-id').value = '';
    document.getElementById('user-username').value = '';
    document.getElementById('user-full-name').value = '';
    document.getElementById('user-email').value = '';
    document.getElementById('user-role').value = '';
    document.getElementById('user-password').value = '';
    document.getElementById('user-confirm-password').value = '';
    document.getElementById('user-active').checked = true;
    document.getElementById('user-message').innerHTML = '';
    document.getElementById('password-fields').style.display = 'block';
    document.getElementById('user-password').required = true;
    document.getElementById('user-confirm-password').required = true;
}

async function loadUserForEdit(id) {
    try {
        const user = allUsers.find(u => u.id == id);
        if (!user) {
            showError('User not found');
            return;
        }

        document.getElementById('user-id').value = user.id;
        document.getElementById('user-username').value = user.username || '';
        document.getElementById('user-full-name').value = user.full_name || '';
        document.getElementById('user-email').value = user.email || '';
        document.getElementById('user-role').value = user.role || '';
        document.getElementById('user-active').checked = user.is_active == 1;

        // Hide password fields for editing
        document.getElementById('password-fields').style.display = 'none';
        document.getElementById('user-password').required = false;
        document.getElementById('user-confirm-password').required = false;
    } catch (error) {
        devLog('Error loading user:', error);
        showError('Failed to load user details');
    }
}

async function saveUser(userId) {
    const formData = {
        username: document.getElementById('user-username').value.trim(),
        full_name: document.getElementById('user-full-name').value.trim(),
        email: document.getElementById('user-email').value.trim(),
        role: document.getElementById('user-role').value,
        is_active: document.getElementById('user-active').checked ? 1 : 0
    };

    const password = document.getElementById('user-password').value;
    const confirmPassword = document.getElementById('user-confirm-password').value;

    // Validate required fields
    if (!formData.username || !formData.full_name || !formData.email || !formData.role) {
        showError('Please fill in all required fields');
        return;
    }

    // Validate password for new users
    if (!userId || (userId && password)) {
        if (password.length < 6) {
            showError('Password must be at least 6 characters long');
            return;
        }
        if (password !== confirmPassword) {
            showError('Passwords do not match');
            return;
        }
        if (password) {
            formData.password = password;
        }
    }

    const isEdit = userId !== null;
    const url = isEdit ? `${API_BASE}/users/update.php` : `${API_BASE}/users/create.php`;

    // Add user ID to formData when editing
    if (isEdit) {
        formData.id = userId;
    }

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess(`User ${isEdit ? 'updated' : 'created'} successfully`);
            bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
            await loadUsers();
        } else {
            showError(result.message || `Failed to ${isEdit ? 'update' : 'create'} user`);
        }
    } catch (error) {
        devLog('Error saving user:', error);
        showError(`Failed to ${isEdit ? 'update' : 'create'} user`);
    }
}

async function viewUser(id) {
    const user = allUsers.find(u => u.id == id);
    if (!user) return;

    // For now, just edit the user (same modal)
    await openUserModal(id);
}

async function editUser(id) {
    await openUserModal(id);
}

async function toggleUserStatus(id, currentStatus) {
    const action = currentStatus == 1 ? 'deactivate' : 'activate';
    const newStatus = currentStatus == 1 ? 0 : 1;

    if (!await showConfirm(`Are you sure you want to ${action} this user?`, {
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} User`,
        confirmText: action.charAt(0).toUpperCase() + action.slice(1),
        type: currentStatus == 1 ? 'danger' : 'success',
        icon: currentStatus == 1 ? 'fas fa-ban' : 'fas fa-check'
    })) {
        return;
    }

    try {
        const formData = { is_active: newStatus };
        const response = await fetch(`${API_BASE}/users/update.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...formData, id: id })
        });

        const result = await response.json();
        if (result.success) {
            showSuccess(`User ${newStatus ? 'activated' : 'deactivated'} successfully`);
            await loadUsers();
        } else {
            showError(result.message || `Failed to ${action} user`);
        }
    } catch (error) {
        devLog('Error toggling user status:', error);
        showError(`Failed to ${action} user`);
    }
}

async function resetUserPassword(id) {
    const newPassword = await showPrompt('Enter a new password for this user:', {
        title: 'Reset User Password',
        required: true,
        inputType: 'password'
    });

    if (!newPassword) return;

    if (newPassword.length < 6) {
        showError('Password must be at least 6 characters long');
        return;
    }

    if (!await showConfirm('Are you sure you want to reset this user\'s password? This cannot be undone and the user will need to use the new password to log in.', {
        title: 'Confirm Password Reset',
        confirmText: 'Reset Password',
        type: 'warning'
    })) {
        return;
    }

    try {
        // For now, implement password reset through update endpoint with special handling
        // TODO: Create dedicated password reset endpoint
        const response = await fetch(`${API_BASE}/users/reset-password.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: id, new_password: newPassword })
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Password reset successfully');
        } else {
            showError(result.message || 'Failed to reset password');
        }
    } catch (error) {
        devLog('Error resetting password:', error);
        showError('Failed to reset password');
    }
}

async function deleteUser(id) {
    const user = allUsers.find(u => u.id == id);
    if (!user) return;

    if (!await showConfirm(`Are you sure you want to delete "${user.full_name || user.username}"? This action cannot be undone.`, {
        title: 'Delete User',
        confirmText: 'Delete',
        type: 'danger',
        icon: 'fas fa-trash'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/users/delete.php`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('User deleted successfully');
            await loadUsers();
        } else {
            showError(result.message || 'Failed to delete user');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showError('Failed to delete user');
    }
}

async function approveSupplierFromUsers(id) {
    if (!await showConfirm('Are you sure you want to approve this supplier? They will be able to access the supplier dashboard.', {
        title: 'Approve Supplier',
        confirmText: 'Approve',
        type: 'success',
        icon: 'fas fa-check'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/suppliers/approve.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Supplier approved successfully');
            await loadPendingSuppliers();
        } else {
            showError(result.message || 'Failed to approve supplier');
        }
    } catch (error) {
        showError('Failed to approve supplier');
    }
}

async function rejectSupplierFromUsers(id) {
    const reason = await showPrompt('Please provide a reason for rejecting this supplier:', {
        title: 'Reject Supplier',
        required: true
    });

    if (!reason) return;

    try {
        const response = await fetch(`${API_BASE}/suppliers/reject.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, reason: reason })
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Supplier rejected successfully');
            await loadPendingSuppliers();
        } else {
            showError(result.message || 'Failed to reject supplier');
        }
    } catch (error) {
        showError('Failed to reject supplier');
    }
}

async function loadActivityLogsPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-history me-2"></i>Activity Logs</h1>
            <p class="page-subtitle">System activity and audit logs</p>
        </div>
        <div class="alert alert-info">
            <i class="fas fa-tools me-2"></i>Activity logs interface is under development.
        </div>
    `;
}

async function loadOrdersPage() {
    const content = document.getElementById('page-content');
    content.innerHTML = `
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-shopping-cart me-2"></i>Customer Orders</h1>
            <p class="page-subtitle">View and manage customer orders</p>
        </div>
        <div class="alert alert-info">
            <i class="fas fa-tools me-2"></i>Customer orders interface is under development.
        </div>
    `;
}

async function deleteProduct(id) {
    const product = allProducts.find(p => p.id == id);
    if (!product) return;

    if (!await showConfirm(`Are you sure you want to delete "${product.name}"? This action cannot be undone.`, {
        title: 'Delete Product',
        confirmText: 'Delete',
        type: 'danger',
        icon: 'fas fa-trash'
    })) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE}/products/delete.php`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Product deleted successfully');
            await loadProducts(); // Refresh the list
        } else {
            showError(result.message || 'Failed to delete product');
        }
    } catch (error) {
        console.error('Error deleting product:', error);
        showError('Failed to delete product');
    }
}

// Stock Adjustments functions
async function loadStockAdjustments(page = 1) {
    const loadingIndicator = document.getElementById('adjustments-loading');
    const content = document.getElementById('adjustments-content');
    const noAdjustmentsMessage = document.getElementById('no-adjustments-message');
    const tbody = document.getElementById('adjustments-table-body');
    const summary = document.getElementById('adjustments-summary');

    const type = document.getElementById('adjustment-type-filter').value;
    const reason = document.getElementById('adjustment-reason-filter').value;
    const dateFrom = document.getElementById('date-from-filter').value;
    const dateTo = document.getElementById('date-to-filter').value;
    const search = document.getElementById('adjustment-search').value.trim();

    try {
        loadingIndicator.classList.remove('d-none');
        content.classList.add('d-none');
        noAdjustmentsMessage.classList.add('d-none');

        let url = `${API_BASE}/inventory/adjustments.php?limit=${itemsPerPage}&offset=${(page - 1) * itemsPerPage}`;

        if (type !== 'all') url += `&type=${type}`;
        if (reason !== 'all') url += `&reason=${reason}`;
        if (dateFrom) url += `&date_from=${dateFrom}`;
        if (dateTo) url += `&date_to=${dateTo}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;

        const response = await fetch(url);
        const data = await response.json();

        loadingIndicator.classList.add('d-none');

        if (data.success && data.data.adjustments && data.data.adjustments.length > 0) {
            displayStockAdjustments(data.data.adjustments);
            content.classList.remove('d-none');

            // Update summary
            if (summary) {
                summary.textContent = `Showing ${data.data.adjustments.length} adjustment${data.data.adjustments.length === 1 ? '' : 's'}`;
            }

            // Show pagination if needed
            const pagination = document.getElementById('adjustments-pagination');
            if (data.data.adjustments.length >= itemsPerPage) {
                pagination.style.display = 'block';
            } else {
                pagination.style.display = 'none';
            }
        } else {
            noAdjustmentsMessage.classList.remove('d-none');
            if (summary) summary.textContent = 'No adjustments found';
        }
    } catch (error) {
        devLog('Error loading stock adjustments:', error);
        loadingIndicator.classList.add('d-none');
        showError('Failed to load stock adjustments. Please try again.');
    }
}

function displayStockAdjustments(adjustments) {
    // Store adjustments for later viewing
    loadedAdjustments = adjustments || [];

    const tbody = document.getElementById('adjustments-table-body');
    tbody.innerHTML = adjustments.map(adjustment => {
        const typeBadge = getAdjustmentTypeBadge(adjustment.adjustment_type);
        const reasonBadge = getAdjustmentReasonBadge(adjustment.reason);
        const adjustedQuantity = adjustment.adjustment_type === 'add' ? `+${adjustment.quantity_adjusted}` :
                                adjustment.adjustment_type === 'remove' ? `-${adjustment.quantity_adjusted}` :
                                `${adjustment.quantity_adjusted}`;

        return `
            <tr>
                <td>
                    <strong class="text-primary">${adjustment.adjustment_number}</strong>
                </td>
                <td>${formatDate(adjustment.adjustment_date)}</td>
                <td>
                    <div>
                        <strong>${adjustment.product?.name || 'Unknown Product'}</strong>
                        <br><small class="text-muted">${adjustment.product?.sku || ''}</small>
                    </div>
                </td>
                <td>${typeBadge}</td>
                <td>
                    <strong>${adjustment.quantity_before}</strong>
                </td>
                <td class="${adjustment.adjustment_type === 'add' ? 'text-success' : adjustment.adjustment_type === 'remove' ? 'text-danger' : ''}">
                    <strong>${adjustedQuantity}</strong>
                </td>
                <td>
                    <strong>${adjustment.quantity_after}</strong>
                </td>
                <td>${reasonBadge}</td>
                <td>
                    ${adjustment.performed_by_name || 'System'}
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button onclick="viewAdjustmentDetails(${adjustment.id})" class="btn btn-outline-info" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${adjustment.notes ? `<button onclick="viewAdjustmentNotes('${adjustment.notes}')" class="btn btn-outline-secondary" title="View Notes">
                            <i class="fas fa-sticky-note"></i>
                        </button>` : ''}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function getAdjustmentTypeBadge(type) {
    const badges = {
        'add': '<span class="badge bg-success"><i class="fas fa-plus me-1"></i>Add Stock</span>',
        'remove': '<span class="badge bg-danger"><i class="fas fa-minus me-1"></i>Remove Stock</span>',
        'recount': '<span class="badge bg-warning"><i class="fas fa-recycle me-1"></i>Recount</span>'
    };
    return badges[type] || '<span class="badge bg-secondary">Unknown</span>';
}

function getAdjustmentReasonBadge(reason) {
    const badges = {
        'purchase_received': '<span class="badge bg-primary">Purchase Received</span>',
        'sale': '<span class="badge bg-success">Sale</span>',
        'return': '<span class="badge bg-info">Return</span>',
        'damaged': '<span class="badge bg-danger">Damaged/Lost</span>',
        'counting_error': '<span class="badge bg-warning">Counting Error</span>',
        'transfer': '<span class="badge bg-secondary">Transfer</span>',
        'other': '<span class="badge bg-dark">Other</span>'
    };
    return badges[reason] || '<span class="badge bg-light text-dark">Other</span>';
}

function changePage(page) {
    currentPage = Math.max(1, page);
    loadStockAdjustments(currentPage);
}

async function openStockAdjustmentModal() {
    const modalContent = `
        <div class="modal fade" id="stockAdjustmentModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title"><i class="fas fa-exchange-alt me-2"></i>Create Stock Adjustment</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="stockAdjustmentForm">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Product Search *</label>
                                    <div class="position-relative">
                                        <input type="text" class="form-control" id="adjustment-product-search" placeholder="Search products by name or SKU..." autocomplete="off">
                                        <div class="product-search-results" id="adjustment-product-search-results" style="
                                            position: absolute;
                                            top: 100%;
                                            left: 0;
                                            right: 0;
                                            background: var(--bg-card);
                                            border: 1px solid var(--border-color);
                                            border-top: none;
                                            border-radius: 0 0 8px 8px;
                                            max-height: 300px;
                                            overflow-y: auto;
                                            z-index: 1050;
                                            display: none;
                                            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                                        "></div>
                                    </div>
                                    <input type="hidden" id="adjustment-product" required>
                                    <div class="form-text" id="product-search-help">Type to search products. Shows current stock levels for easy selection.</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Adjustment Type *</label>
                                    <select class="form-select" id="adjustment-type" required>
                                        <option value="">Select type...</option>
                                        <option value="add">Add Stock (Stock In)</option>
                                        <option value="remove">Remove Stock (Stock Out)</option>
                                        <option value="recount">Recount (Set to specific quantity)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Reason *</label>
                                    <select class="form-select" id="adjustment-reason" required>
                                        <option value="">Select reason...</option>
                                        <option value="purchase_received">Purchase Received</option>
                                        <option value="sale">Sale</option>
                                        <option value="return">Customer Return</option>
                                        <option value="damaged">Damaged/Lost</option>
                                        <option value="counting_error">Counting Error</option>
                                        <option value="transfer">Stock Transfer</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" id="quantity-label">Quantity to Adjust *</label>
                                    <input type="number" class="form-control" id="adjustment-quantity" min="1" required>
                                    <div class="form-text" id="quantity-help">For add/remove: enter how much to add/remove. For recount: enter the new total stock.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Current Stock</label>
                                    <input type="number" class="form-control" id="current-stock-display" readonly disabled>
                                    <div class="form-text">Read-only: shows current stock for reference</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Stock After Adjustment</label>
                                    <input type="number" class="form-control" id="new-stock-display" readonly disabled>
                                    <div class="form-text">Calculated automatically based on adjustment</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Adjustment Preview</label>
                                    <input type="text" class="form-control" id="adjustment-preview" readonly disabled style="font-weight: bold;">
                                    <div class="form-text">Shows how the stock will change</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" id="adjustment-notes" rows="3" placeholder="Optional notes explaining the adjustment..."></textarea>
                            </div>

                            <div id="adjustment-message"></div>
                        </form>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="createAdjustmentBtn">
                            <i class="fas fa-save me-2"></i>Create Adjustment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    const existingModal = document.getElementById('stockAdjustmentModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalContent);

    // Set up interactive behavior
    setupAdjustmentFormInteractivity();

    const modal = new bootstrap.Modal(document.getElementById('stockAdjustmentModal'));
    modal.show();

    // Clean up modal when hidden
    document.getElementById('stockAdjustmentModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

function setupAdjustmentFormInteractivity() {
    const productSearchInput = document.getElementById('adjustment-product-search');
    const searchResults = document.getElementById('adjustment-product-search-results');
    const productHiddenInput = document.getElementById('adjustment-product');
    const typeSelect = document.getElementById('adjustment-type');
    const quantityInput = document.getElementById('adjustment-quantity');
    const currentStockDisplay = document.getElementById('current-stock-display');
    const newStockDisplay = document.getElementById('new-stock-display');
    const adjustmentPreview = document.getElementById('adjustment-preview');
    const quantityLabel = document.getElementById('quantity-label');
    const quantityHelp = document.getElementById('quantity-help');

    // Product search functionality
    let productsForSearch = [];
    let selectedProductId = null;
    let selectedProductStock = 0;

    // Load products for search
    fetch(`${API_BASE}/products/index.php?status=all&limit=1000`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data && data.data.products) {
                productsForSearch = data.data.products;
            }
        })
        .catch(error => devLog('Error loading products for search:', error));

    productSearchInput.addEventListener('input', debounce(function(e) {
        const query = e.target.value.trim();
        if (query.length >= 2) {
            searchProductsForAdjustment(query);
        } else {
            searchResults.style.display = 'none';
        }
    }, 300));

    productSearchInput.addEventListener('focus', function() {
        if (productSearchInput.value.trim().length >= 2) {
            searchProductsForAdjustment(productSearchInput.value.trim());
        }
    });

    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!productSearchInput.closest('.position-relative').contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Update labels and calculations when type changes
    typeSelect.addEventListener('change', updateAdjustmentCalculations);

    // Update when quantity changes
    quantityInput.addEventListener('input', updateAdjustmentCalculations);

    // Add save button handler
    document.getElementById('createAdjustmentBtn').addEventListener('click', createStockAdjustment);

    function searchProductsForAdjustment(query) {
        const filteredProducts = productsForSearch.filter(product =>
            product.name.toLowerCase().includes(query.toLowerCase()) ||
            (product.sku && product.sku.toLowerCase().includes(query.toLowerCase()))
        ).slice(0, 10); // Limit to 10 results

        if (filteredProducts.length === 0) {
            searchResults.innerHTML = '<div class="text-center text-muted py-2">No products found</div>';
            searchResults.style.display = 'block';
            return;
        }

        searchResults.innerHTML = filteredProducts.map(product => `
            <div class="product-search-item px-3 py-2 border-bottom" data-product-id="${product.id}" style="cursor: pointer;">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1">
                        <div class="fw-bold text-truncate">${product.name}</div>
                        <small class="text-muted">SKU: ${product.sku || 'N/A'} | Price: ₱${product.selling_price || 0}</small>
                    </div>
                    <div class="text-end">
                        <small class="badge ${product.quantity_on_hand > 5 ? 'bg-success' : 'bg-warning'}">
                            ${product.quantity_on_hand || 0} in stock
                        </small>
                    </div>
                </div>
            </div>
        `).join('');

        searchResults.style.display = 'block';

        // Add click handlers to search results
        searchResults.querySelectorAll('.product-search-item').forEach(item => {
            item.addEventListener('click', function() {
                const productId = this.dataset.productId;
                const product = productsForSearch.find(p => p.id == productId);

                if (product) {
                    // Update selected product
                    selectedProductId = productId;
                    selectedProductStock = product.quantity_on_hand || 0;

                    // Update hidden input
                    productHiddenInput.value = productId;

                    // Update search input to show selected product name
                    productSearchInput.value = product.name;

                    // Update current stock display
                    currentStockDisplay.value = selectedProductStock;

                    // Hide search results
                    searchResults.style.display = 'none';

                    // Update calculations
                    updateAdjustmentCalculations();
                }
            });

            // Hover effects
            item.addEventListener('mouseenter', function() {
                this.style.background = 'var(--accent-light, rgba(0, 245, 255, 0.1))';
            });
            item.addEventListener('mouseleave', function() {
                this.style.background = '';
            });
        });
    }

    function updateAdjustmentCalculations() {
        const productId = productHiddenInput.value;
        const adjustmentType = typeSelect.value;
        const quantity = parseInt(quantityInput.value) || 0;
        const currentStock = selectedProductStock;

        if (!adjustmentType || !productId) return;

        let newStock, preview, label, help;

        switch (adjustmentType) {
            case 'add':
                newStock = currentStock + quantity;
                preview = `+${quantity} (Was ${currentStock}, Will be ${newStock})`;
                label = 'Quantity to Add *';
                help = 'Enter how many units to add to current stock';
                break;
            case 'remove':
                newStock = Math.max(0, currentStock - quantity);
                preview = `-${quantity} (Was ${currentStock}, Will be ${newStock})`;
                label = 'Quantity to Remove *';
                help = 'Enter how many units to remove from current stock';
                break;
            case 'recount':
                newStock = quantity;
                preview = `Recount to ${quantity} (Was ${currentStock}, Will be ${newStock})`;
                label = 'New Total Stock *';
                help = 'Enter the correct total stock quantity after recounting';
                break;
        }

        quantityLabel.textContent = label;
        quantityHelp.textContent = help;
        newStockDisplay.value = newStock;
        adjustmentPreview.value = preview;
        adjustmentPreview.style.color = adjustmentType === 'add' ? 'green' : adjustmentType === 'remove' ? 'red' : 'blue';
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

async function createStockAdjustment() {
    const productSelect = document.getElementById('adjustment-product');
    const typeSelect = document.getElementById('adjustment-type');
    const reasonSelect = document.getElementById('adjustment-reason');
    const quantityInput = document.getElementById('adjustment-quantity');
    const notesTextarea = document.getElementById('adjustment-notes');

    // Validate form
    if (!productSelect.value || !typeSelect.value || !reasonSelect.value || !quantityInput.value) {
        showError('Please fill in all required fields');
        return;
    }

    const adjustmentData = {
        product_id: parseInt(productSelect.value),
        adjustment_type: typeSelect.value,
        quantity_adjusted: parseInt(quantityInput.value),
        reason: reasonSelect.value,
        notes: notesTextarea.value.trim() || null
    };

    try {
        const response = await fetch(`${API_BASE}/inventory/adjustments.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(adjustmentData)
        });

        const result = await response.json();
        if (result.success) {
            showSuccess('Stock adjustment created successfully');
            bootstrap.Modal.getInstance(document.getElementById('stockAdjustmentModal')).hide();
            await loadStockAdjustments();
        } else {
            showError(result.message || 'Failed to create stock adjustment');
        }
    } catch (error) {
        devLog('Error creating stock adjustment:', error);
        showError('Failed to create stock adjustment');
    }
}

async function viewAdjustmentDetails(adjustmentId) {
    // Find the adjustment from loaded data (use == to handle type coercion)
    const adjustment = loadedAdjustments.find(adj => adj.id == adjustmentId);

    if (!adjustment) {
        showError(`Adjustment #${adjustmentId} not found. Please refresh the page and try again.`);
        return;
    }

    // Create and show modal
    const modalHtml = `
        <div class="modal fade" id="adjustmentDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title">
                            <i class="fas fa-info-circle me-2"></i>Stock Adjustment Details
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Adjustment Number & Type -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="detail-card p-3" style="background: var(--bg-tertiary); border-radius: 8px;">
                                    <label class="text-muted small mb-1">Adjustment Number</label>
                                    <h6 class="mb-0 text-primary fw-bold">${adjustment.adjustment_number}</h6>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-card p-3" style="background: var(--bg-tertiary); border-radius: 8px;">
                                    <label class="text-muted small mb-1">Type</label>
                                    <div>${getAdjustmentTypeBadge(adjustment.adjustment_type)}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Information -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3" style="border-color: var(--border-color) !important;">
                                <i class="fas fa-box me-2"></i>Product Information
                            </h6>
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="text-muted small">Product Name</label>
                                    <p class="fw-bold mb-2">${adjustment.product?.name || 'Unknown Product'}</p>
                                </div>
                                <div class="col-md-4">
                                    <label class="text-muted small">SKU</label>
                                    <p class="mb-2"><code>${adjustment.product?.sku || 'N/A'}</code></p>
                                </div>
                            </div>
                        </div>

                        <!-- Quantity Changes -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3" style="border-color: var(--border-color) !important;">
                                <i class="fas fa-chart-line me-2"></i>Quantity Changes
                            </h6>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="p-3" style="background: var(--bg-tertiary); border-radius: 8px;">
                                        <label class="text-muted small d-block mb-1">Before</label>
                                        <h4 class="mb-0">${adjustment.quantity_before}</h4>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3" style="background: ${adjustment.adjustment_type === 'add' ? 'rgba(25, 135, 84, 0.1)' : adjustment.adjustment_type === 'remove' ? 'rgba(220, 53, 69, 0.1)' : 'rgba(255, 193, 7, 0.1)'}; border-radius: 8px;">
                                        <label class="text-muted small d-block mb-1">Adjusted</label>
                                        <h4 class="mb-0 ${adjustment.adjustment_type === 'add' ? 'text-success' : adjustment.adjustment_type === 'remove' ? 'text-danger' : 'text-warning'}">
                                            ${adjustment.adjustment_type === 'add' ? '+' : adjustment.adjustment_type === 'remove' ? '-' : ''}${adjustment.quantity_adjusted}
                                        </h4>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-3" style="background: var(--bg-tertiary); border-radius: 8px;">
                                        <label class="text-muted small d-block mb-1">After</label>
                                        <h4 class="mb-0 text-primary">${adjustment.quantity_after}</h4>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Reason & Details -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2 mb-3" style="border-color: var(--border-color) !important;">
                                <i class="fas fa-clipboard-list me-2"></i>Adjustment Details
                            </h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">Reason</label>
                                    <div>${getAdjustmentReasonBadge(adjustment.reason)}</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="text-muted small">Date & Time</label>
                                    <p class="mb-0">${formatDate(adjustment.adjustment_date)}</p>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="text-muted small">Performed By</label>
                                    <p class="mb-0"><i class="fas fa-user me-2"></i>${adjustment.performed_by_name || 'System'}</p>
                                </div>
                                ${adjustment.notes ? `
                                <div class="col-md-12">
                                    <label class="text-muted small">Notes</label>
                                    <div class="p-3" style="background: var(--bg-tertiary); border-radius: 8px; border-left: 3px solid var(--accent);">
                                        <p class="mb-0">${adjustment.notes}</p>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        </div>

                        <!-- Impact Summary -->
                        <div class="alert ${adjustment.adjustment_type === 'add' ? 'alert-success' : adjustment.adjustment_type === 'remove' ? 'alert-danger' : 'alert-warning'} mb-0">
                            <i class="fas ${adjustment.adjustment_type === 'add' ? 'fa-arrow-up' : adjustment.adjustment_type === 'remove' ? 'fa-arrow-down' : 'fa-sync-alt'} me-2"></i>
                            <strong>Impact:</strong> This adjustment ${adjustment.adjustment_type === 'add' ? 'increased' : adjustment.adjustment_type === 'remove' ? 'decreased' : 'recounted'}
                            the stock level ${adjustment.adjustment_type === 'add' ? 'by' : adjustment.adjustment_type === 'remove' ? 'by' : 'to'}
                            <strong>${Math.abs(adjustment.quantity_adjusted)}</strong> unit${Math.abs(adjustment.quantity_adjusted) !== 1 ? 's' : ''}
                            ${adjustment.adjustment_type === 'recount' ? ', resulting in a net change of <strong>' + (adjustment.quantity_after - adjustment.quantity_before) + '</strong> units' : ''}.
                        </div>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove any existing modal
    const existingModal = document.getElementById('adjustmentDetailsModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('adjustmentDetailsModal'));
    modal.show();

    // Clean up on close
    document.getElementById('adjustmentDetailsModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

function viewAdjustmentNotes(notes) {
    // Create a simple modal to display notes
    const modalHtml = `
        <div class="modal fade" id="notesModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                    <div class="modal-header" style="border-color: var(--border-color);">
                        <h5 class="modal-title">
                            <i class="fas fa-sticky-note me-2"></i>Adjustment Notes
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="p-3" style="background: var(--bg-tertiary); border-radius: 8px; border-left: 3px solid var(--accent);">
                            <p class="mb-0" style="white-space: pre-wrap;">${notes || 'No notes available'}</p>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-color: var(--border-color);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove any existing modal
    const existingModal = document.getElementById('notesModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('notesModal'));
    modal.show();

    // Clean up on close
    document.getElementById('notesModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}
