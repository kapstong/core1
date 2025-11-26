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
    <title>My Profile - PC Parts Central</title>
    <link rel="icon" type="image/png" href="../ppc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <style>
        body {
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .profile-header {
            background: linear-gradient(135deg, var(--accent), #a855f7);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 600;
            margin: 0 auto 1rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }

        .profile-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .profile-card:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 20px rgba(0, 245, 255, 0.2);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.75rem;
            border-radius: 0.5rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(0, 245, 255, 0.25);
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .btn-accent {
            background: linear-gradient(135deg, var(--accent), #a855f7);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-accent:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 245, 255, 0.4);
            color: white;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--accent);
        }

        .alert {
            border-radius: 0.5rem;
            border: none;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .password-section {
            background: rgba(0, 245, 255, 0.05);
            border: 1px solid rgba(0, 245, 255, 0.2);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .stats-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .nav-tabs {
            border-bottom: 1px solid var(--border-color);
        }

        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            border-bottom: 2px solid transparent;
            padding: 1rem 1.5rem;
            font-weight: 500;
        }

        .nav-tabs .nav-link:hover {
            color: var(--accent);
            border-bottom-color: rgba(0, 245, 255, 0.3);
        }

        .nav-tabs .nav-link.active {
            color: var(--accent);
            background: none;
            border-bottom-color: var(--accent);
        }

        .tab-content {
            padding-top: 2rem;
        }

        .verification-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .verification-badge.verified {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .verification-badge.unverified {
            background: rgba(251, 191, 36, 0.1);
            color: #fbbf24;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        .btn-resend {
            background: rgba(0, 245, 255, 0.1);
            border: 1px solid rgba(0, 245, 255, 0.3);
            color: var(--accent);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-resend:hover {
            background: rgba(0, 245, 255, 0.2);
            border-color: var(--accent);
        }

        .btn-resend:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .profile-header {
                padding: 2rem 0;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }

            .profile-card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="../ppc.png" alt="PC Parts Central Logo" style="height: 32px; width: auto; margin-right: 8px;">
                <span style="font-weight: 700;">PC Parts Central</span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-store me-1"></i>Shop
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart me-1"></i>Cart
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><span id="customer-name">Loading...</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php"><i class="fas fa-receipt me-2"></i>My Orders</a></li>
                            <li><hr class="dropdown-divider" style="border-color: var(--border-color);"></li>
                            <li><a class="dropdown-item text-danger" href="#" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="text-center">
                <div class="profile-avatar" id="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h1 id="profile-name">Loading...</h1>
                <p id="profile-email" class="mb-0">Loading...</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-value" id="total-orders">0</div>
                    <div class="stats-label">Total Orders</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-value" id="total-spent">₱0</div>
                    <div class="stats-label">Total Spent</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-value" id="member-since">2024</div>
                    <div class="stats-label">Member Since</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-value" id="loyalty-points">0</div>
                    <div class="stats-label">Loyalty Points</div>
                </div>
            </div>
        </div>

        <!-- Profile Tabs -->
        <div class="profile-card">
            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                        <i class="fas fa-user me-2"></i>Personal Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                        <i class="fas fa-shield-alt me-2"></i>Security
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="addresses-tab" data-bs-toggle="tab" data-bs-target="#addresses" type="button" role="tab">
                        <i class="fas fa-map-marker-alt me-2"></i>Addresses
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                        <i class="fas fa-cog me-2"></i>Preferences
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="profileTabsContent">
                <!-- Personal Information Tab -->
                <div class="tab-pane fade show active" id="personal" role="tabpanel">
                    <div class="section-title">
                        <i class="fas fa-user"></i>
                        Personal Information
                    </div>

                    <form id="personalForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first-name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last-name" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="email" required>
                                <div class="d-flex align-items-center justify-content-between mt-2">
                                    <small class="text-muted">Changing your email will require re-verification</small>
                                </div>
                                <!-- Email Verification Status -->
                                <div id="email-verification-status" class="mt-2"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" placeholder="+63 9XX XXX XXXX">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date-of-birth">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" id="gender">
                                    <option value="">Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                    <option value="prefer-not-to-say">Prefer not to say</option>
                                </select>
                            </div>
                        </div>

                        <div id="personal-message"></div>

                        <button type="submit" class="btn btn-accent">
                            <i class="fas fa-save me-2"></i>Update Personal Information
                        </button>
                    </form>
                </div>

                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="section-title">
                        <i class="fas fa-shield-alt"></i>
                        Security Settings
                    </div>

                    <div class="password-section">
                        <h6 class="mb-3">Change Password</h6>
                        <form id="passwordForm">
                            <div class="mb-3">
                                <label class="form-label">Current Password *</label>
                                <input type="password" class="form-control" id="current-password" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" id="new-password" required minlength="8">
                                    <small class="text-muted">At least 8 characters long</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" id="confirm-password" required minlength="8">
                                </div>
                            </div>

                            <div id="password-message"></div>

                            <button type="submit" class="btn btn-accent">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>

                    <hr style="border-color: var(--border-color); margin: 2rem 0;">

                    <div class="section-title">
                        <i class="fas fa-history"></i>
                        Account Activity
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Your account activity and login history will be displayed here.
                    </div>
                </div>

                <!-- Addresses Tab -->
                <div class="tab-pane fade" id="addresses" role="tabpanel">
                    <div class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Saved Addresses
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Your saved shipping and billing addresses will appear here. Addresses are automatically saved during checkout for faster future orders.
                    </div>

                    <div id="addresses-loading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading your addresses...</p>
                    </div>

                    <div id="addresses-container" style="display: none;">
                        <div class="row" id="addresses-list">
                            <!-- Addresses will be loaded here -->
                        </div>

                        <div id="no-addresses" class="text-center py-4" style="display: none;">
                            <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                            <h5>No saved addresses</h5>
                            <p class="text-muted">Your saved addresses will appear here after your first order.</p>
                        </div>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div class="tab-pane fade" id="preferences" role="tabpanel">
                    <div class="section-title">
                        <i class="fas fa-cog"></i>
                        Preferences
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Preference settings will be available here in future updates.
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6>Email Notifications</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="email-order-updates" checked disabled>
                                <label class="form-check-label" for="email-order-updates">
                                    Order updates and shipping notifications
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="email-promotions" disabled>
                                <label class="form-check-label" for="email-promotions">
                                    Promotional offers and newsletters
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Privacy Settings</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="profile-visibility" checked disabled>
                                <label class="form-check-label" for="profile-visibility">
                                    Make profile visible to sellers
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_BASE = '/core1/backend/api';

        // Check authentication on page load
        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            await loadProfile();
            await loadAddresses();
        });

        // Check if customer is authenticated
        async function checkAuth() {
            try {
                const response = await fetch(`${API_BASE}/shop/auth.php?action=me`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();

                if (!data.success) {
                    // Use redirect URL from API response, fallback to login.php
                    const redirectUrl = data.data && data.data.redirect ? data.data.redirect : 'login.php';
                    window.location.href = redirectUrl;
                    return;
                }

                // Update navigation
                document.getElementById('customer-name').textContent = data.data.customer.first_name || 'Customer';
            } catch (error) {
                console.error('Auth check failed:', error);
                window.location.href = 'login.php';
            }
        }

        // Load customer profile
        async function loadProfile() {
            try {
                const response = await fetch(`${API_BASE}/shop/profile.php`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();

                if (data.success) {
                    const customer = data.data.customer;

                    // Update header
                    const initials = (customer.first_name + ' ' + customer.last_name)
                        .split(' ')
                        .map(n => n[0])
                        .join('')
                        .toUpperCase()
                        .substring(0, 2);
                    document.getElementById('profile-avatar').textContent = initials;
                    document.getElementById('profile-name').textContent = `${customer.first_name} ${customer.last_name}`;
                    document.getElementById('profile-email').textContent = customer.email;

                    // Update navigation
                    document.getElementById('customer-name').textContent = customer.first_name;

                    // Fill form
                    document.getElementById('first-name').value = customer.first_name || '';
                    document.getElementById('last-name').value = customer.last_name || '';
                    document.getElementById('email').value = customer.email || '';
                    document.getElementById('phone').value = customer.phone || '';
                    // Handle invalid date values (like "0000-00-00")
                    const dateOfBirth = customer.date_of_birth && customer.date_of_birth !== '0000-00-00' ? customer.date_of_birth : '';
                    document.getElementById('date-of-birth').value = dateOfBirth;
                    document.getElementById('gender').value = customer.gender || '';

                    // Display email verification status
                    displayEmailVerificationStatus(customer.email_verified);

                    // Update stats (placeholder data)
                    document.getElementById('total-orders').textContent = customer.total_orders || '0';
                    document.getElementById('total-spent').textContent = '₱' + (customer.total_spent || '0');
                    document.getElementById('member-since').textContent = new Date(customer.created_at).getFullYear();
                    document.getElementById('loyalty-points').textContent = '0'; // Placeholder
                } else {
                    showMessage('personal-message', data.message || 'Failed to load profile', 'danger');
                }
            } catch (error) {
                console.error('Failed to load profile:', error);
                showMessage('personal-message', 'Failed to load profile. Please try again.', 'danger');
            }
        }

        // Handle personal information form submission
        document.getElementById('personalForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = {
                first_name: document.getElementById('first-name').value.trim(),
                last_name: document.getElementById('last-name').value.trim(),
                email: document.getElementById('email').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                date_of_birth: document.getElementById('date-of-birth').value,
                gender: document.getElementById('gender').value
            };

            // Basic validation
            if (!formData.first_name || !formData.last_name || !formData.email) {
                showMessage('personal-message', 'Please fill in all required fields.', 'danger');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/shop/profile.php`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('personal-message', 'Profile updated successfully!', 'success');
                    // Reload profile to reflect changes
                    setTimeout(() => loadProfile(), 1500);
                } else {
                    showMessage('personal-message', data.message || 'Failed to update profile', 'danger');
                }
            } catch (error) {
                console.error('Failed to update profile:', error);
                showMessage('personal-message', 'Failed to update profile. Please try again.', 'danger');
            }
        });

        // Handle password change form submission
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            // Validation
            if (!currentPassword || !newPassword || !confirmPassword) {
                showMessage('password-message', 'Please fill in all password fields.', 'danger');
                return;
            }

            if (newPassword !== confirmPassword) {
                showMessage('password-message', 'New passwords do not match.', 'danger');
                return;
            }

            if (newPassword.length < 8) {
                showMessage('password-message', 'New password must be at least 8 characters long.', 'danger');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/shop/profile.php`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('password-message', 'Password changed successfully!', 'success');
                    // Clear password fields
                    document.getElementById('current-password').value = '';
                    document.getElementById('new-password').value = '';
                    document.getElementById('confirm-password').value = '';
                } else {
                    showMessage('password-message', data.message || 'Failed to change password', 'danger');
                }
            } catch (error) {
                console.error('Failed to change password:', error);
                showMessage('password-message', 'Failed to change password. Please try again.', 'danger');
            }
        });

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

        // Load customer addresses
        async function loadAddresses() {
            const loading = document.getElementById('addresses-loading');
            const container = document.getElementById('addresses-container');
            const noAddresses = document.getElementById('no-addresses');
            const addressesList = document.getElementById('addresses-list');

            loading.style.display = 'block';
            container.style.display = 'none';
            noAddresses.style.display = 'none';

            try {
                const response = await fetch(`${API_BASE}/shop/addresses.php`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();

                loading.style.display = 'none';

                if (data.success && data.data.addresses && data.data.addresses.length > 0) {
                    addressesList.innerHTML = '';

                    // Group addresses by type
                    const shippingAddresses = data.data.addresses.filter(addr => addr.address_type === 'shipping');
                    const billingAddresses = data.data.addresses.filter(addr => addr.address_type === 'billing');

                    // Display shipping addresses
                    if (shippingAddresses.length > 0) {
                        addressesList.innerHTML += '<div class="col-12 mb-4"><h5 class="text-primary"><i class="fas fa-shipping-fast me-2"></i>Shipping Addresses</h5></div>';
                        shippingAddresses.forEach(address => {
                            addressesList.innerHTML += createAddressCard(address);
                        });
                    }

                    // Display billing addresses
                    if (billingAddresses.length > 0) {
                        addressesList.innerHTML += '<div class="col-12 mb-4"><h5 class="text-primary"><i class="fas fa-file-invoice-dollar me-2"></i>Billing Addresses</h5></div>';
                        billingAddresses.forEach(address => {
                            addressesList.innerHTML += createAddressCard(address);
                        });
                    }

                    container.style.display = 'block';
                } else {
                    noAddresses.style.display = 'block';
                }
            } catch (error) {
                console.error('Failed to load addresses:', error);
                loading.style.display = 'none';
                noAddresses.style.display = 'block';
            }
        }

        // Create address card HTML
        function createAddressCard(address) {
            const isDefault = address.is_default ? '<span class="badge bg-success ms-2">Default</span>' : '';
            const typeIcon = address.address_type === 'shipping' ? 'fas fa-shipping-fast' : 'fas fa-file-invoice-dollar';
            const typeColor = address.address_type === 'shipping' ? 'text-primary' : 'text-info';

            return `
                <div class="col-md-6 mb-3">
                    <div class="card h-100" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title mb-0 ${typeColor}">
                                    <i class="${typeIcon} me-2"></i>
                                    ${address.address_type === 'shipping' ? 'Shipping' : 'Billing'} Address
                                    ${isDefault}
                                </h6>
                            </div>
                            <div class="card-text">
                                <div class="mb-1"><strong>${address.first_name} ${address.last_name}</strong></div>
                                ${address.company ? `<div class="mb-1">${address.company}</div>` : ''}
                                <div class="mb-1">${address.address_line_1}</div>
                                ${address.address_line_2 ? `<div class="mb-1">${address.address_line_2}</div>` : ''}
                                <div class="mb-1">${address.city}, ${address.state || ''} ${address.postal_code}</div>
                                <div class="mb-1">${address.country}</div>
                                ${address.phone ? `<div class="mb-1"><i class="fas fa-phone me-1"></i>${address.phone}</div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Utility function to show messages
        function showMessage(elementId, message, type = 'info') {
            const element = document.getElementById(elementId);
            element.innerHTML = `<div class="alert alert-${type}"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${message}</div>`;

            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    element.innerHTML = '';
                }, 5000);
            }
        }

        // Display email verification status
        function displayEmailVerificationStatus(isVerified) {
            const statusContainer = document.getElementById('email-verification-status');

            if (isVerified) {
                statusContainer.innerHTML = `
                    <div class="verification-badge verified">
                        <i class="fas fa-check-circle"></i>
                        Email Verified
                    </div>
                `;
            } else {
                statusContainer.innerHTML = `
                    <div class="d-flex flex-column gap-2">
                        <div class="verification-badge unverified">
                            <i class="fas fa-exclamation-triangle"></i>
                            Email Not Verified
                        </div>
                        <button type="button" class="btn-resend" onclick="resendVerificationEmail()">
                            <i class="fas fa-envelope me-1"></i>Resend Verification Email
                        </button>
                        <small class="text-muted">Please check your inbox and spam folder for the verification email.</small>
                    </div>
                `;
            }
        }

        // Resend verification email
        async function resendVerificationEmail() {
            const button = event.target.closest('.btn-resend');
            const originalText = button.innerHTML;

            // Disable button and show loading
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';

            try {
                const response = await fetch(`${API_BASE}/shop/resend-verification.php`, {
                    method: 'POST',
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    // Show success message
                    showMessage('personal-message', data.data.message || 'Verification email sent successfully!', 'success');

                    // Update button to show success
                    button.innerHTML = '<i class="fas fa-check me-1"></i>Email Sent!';
                    button.classList.add('btn-success');

                    // Re-enable after 5 seconds with different text
                    setTimeout(() => {
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-envelope me-1"></i>Send Again';
                        button.classList.remove('btn-success');
                    }, 5000);
                } else {
                    // If already verified, refresh the profile
                    if (data.data && data.data.already_verified) {
                        showMessage('personal-message', data.data.message, 'info');
                        setTimeout(() => loadProfile(), 1500);
                    } else {
                        showMessage('personal-message', data.message || 'Failed to send verification email', 'danger');
                    }

                    // Re-enable button
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            } catch (error) {
                console.error('Failed to resend verification email:', error);
                showMessage('personal-message', 'Failed to send verification email. Please try again.', 'danger');

                // Re-enable button
                button.disabled = false;
                button.innerHTML = originalText;
            }
        }
    </script>
</body>
</html>
