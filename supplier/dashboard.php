<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard - Purchase Orders</title>
    <link rel="icon" type="image/png" href="../ppc.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="../public/assets/css/main.css">

    <style>
        :root {
            --primary: #0066ff;
            --accent: #00f5ff;
            --bg-primary: #0a0e27;
            --bg-secondary: #1a1f3a;
            --bg-card: rgba(26, 31, 58, 0.6);
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: rgba(148, 163, 184, 0.2);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 240px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-brand {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-brand i {
            color: var(--accent);
            font-size: 1.4rem;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-section {
            margin-bottom: 1.5rem;
        }

        .menu-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .menu-item:hover {
            background: rgba(0, 245, 255, 0.05);
            color: var(--accent);
            border-left: 3px solid var(--accent);
        }

        .menu-item.active {
            background: rgba(0, 245, 255, 0.1);
            color: var(--accent);
            border-left: 3px solid var(--accent);
        }

        .menu-item i {
            width: 18px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 240px;
            min-height: 100vh;
            background: var(--bg-primary);
        }

        .topbar {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: rgba(0, 245, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-menu:hover {
            background: rgba(0, 245, 255, 0.1);
            border-color: var(--accent);
        }

        /* Notification Bell */
        .notification-bell {
            position: relative;
            background: rgba(0, 245, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-bell:hover {
            background: rgba(0, 245, 255, 0.1);
            border-color: var(--accent);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
            border: 2px solid var(--bg-secondary);
        }

        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            width: 380px;
            max-height: 500px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            z-index: 1001;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h6 {
            margin: 0;
            font-weight: 600;
            color: var(--accent);
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background: rgba(0, 245, 255, 0.05);
        }

        .notification-item.unread {
            background: rgba(0, 245, 255, 0.08);
            border-left: 3px solid var(--accent);
        }

        .notification-item .notification-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .notification-item .notification-message {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 0.5rem;
        }

        .notification-item .notification-time {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .notification-type-badge {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }

        .notification-type-success {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .notification-type-warning {
            background: rgba(245, 158, 11, 0.2);
            color: var(--warning);
        }

        .notification-type-danger {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        .notification-type-info {
            background: rgba(0, 245, 255, 0.2);
            color: var(--accent);
        }

        .notification-footer {
            padding: 0.75rem 1.25rem;
            border-top: 1px solid var(--border-color);
            text-align: center;
        }

        .notification-footer button {
            background: none;
            border: none;
            color: var(--accent);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-footer button:hover {
            color: var(--text-primary);
        }

        .empty-notifications {
            padding: 3rem 1.5rem;
            text-align: center;
        }

        .empty-notifications i {
            font-size: 3rem;
            color: var(--text-muted);
            opacity: 0.3;
            margin-bottom: 1rem;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), #a855f7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .content-wrapper {
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-muted);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 245, 255, 0.2);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            margin-top: 0.75rem;
        }

        .po-container {
            margin-bottom: 2rem;
        }

        .po-card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .po-card:hover {
            border-color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 245, 255, 0.2);
        }

        .po-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .badge-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #ff8c00 100%);
            color: white;
        }

        .badge-approved {
            background: linear-gradient(135deg, #10b981 0%, #00cc66 100%);
            color: white;
        }

        .badge-rejected {
            background: linear-gradient(135deg, #ef4444 0%, #cc0033 100%);
            color: white;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981 0%, #00cc66 100%);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-approve:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444 0%, #cc0033 100%);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-reject:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-view {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 245, 255, 0.3);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block !important;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .po-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
        }

        /* Loading States */
        .loading {
            text-align: center;
            padding: 2rem;
        }

        .spinner-border {
            color: var(--accent);
            width: 3rem;
            height: 3rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--text-muted);
            opacity: 0.3;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand">
                <img src="../ppc.png" alt="Inventory Management System" style="height: 36px; width: auto;">
                <span>IMS Supplier</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-section-title">MAIN</div>
                <div class="menu-item active" data-page="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </div>
                <div class="menu-item" data-page="po-history">
                    <i class="fas fa-history"></i>
                    <span>Order History</span>
                </div>
            </div>

            <div class="menu-section">
                <div class="menu-section-title">ACCOUNT</div>
                <div class="menu-item" data-page="profile">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </div>
                <div class="menu-item" data-page="settings">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </div>
                <div class="menu-item" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu-toggle" id="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-truck-loading me-2"></i>Supplier Dashboard</h1>
                    <p class="page-subtitle">Manage and approve purchase orders</p>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Notification Bell -->
                <div class="notification-bell" id="notification-bell" style="position: relative;">
                    <i class="fas fa-bell" style="font-size: 1.2rem; color: var(--text-secondary);"></i>
                    <span class="notification-badge" id="notification-badge" style="display: none;">0</span>

                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notification-dropdown">
                        <div class="notification-header">
                            <h6><i class="fas fa-bell me-2"></i>Notifications</h6>
                            <button onclick="markAllAsRead()" style="background: none; border: none; color: var(--accent); font-size: 0.8rem; cursor: pointer;">
                                Mark all read
                            </button>
                        </div>
                        <div class="notification-list" id="notification-list">
                            <!-- Notifications will be loaded here -->
                        </div>
                        <div class="notification-footer">
                            <button onclick="loadNotifications()">
                                <i class="fas fa-sync me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <div class="user-menu" onclick="showPage('profile')">
                    <div class="user-avatar" id="user-avatar">S</div>
                    <div class="d-none d-md-block">
                        <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary); line-height: 1.2;" id="user-name">Loading...</div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">Supplier</div>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: var(--text-muted);"></i>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content-wrapper">
            <!-- Dashboard Page -->
            <div id="page-dashboard" class="page-content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.1)); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.2);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="pending-count">0</div>
                    <div class="stat-label">Pending Orders</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-arrow-up"></i>
                        <span>Requires attention</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1)); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="approved-count">0</div>
                    <div class="stat-label">Approved Orders</div>
                    <div class="stat-trend trend-up">
                        <i class="fas fa-plus"></i>
                        <span>This month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1)); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2);">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value" id="rejected-count">0</div>
                    <div class="stat-label">Rejected Orders</div>
                    <div class="stat-trend trend-down">
                        <i class="fas fa-arrow-down"></i>
                        <span>This month</span>
                    </div>
                </div>
            </div>

            <!-- Purchase Orders Section -->
            <div class="po-container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 style="color: var(--accent); margin: 0;">
                        <i class="fas fa-file-invoice me-2"></i>Recent Purchase Orders
                    </h2>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary btn-sm" onclick="refreshOrders()">
                            <i class="fas fa-sync me-1"></i>Refresh
                        </button>
                    </div>
                </div>

                <div id="po-container">
                    <!-- POs will be loaded here -->
                </div>

                <div id="loading" class="loading">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-3 text-muted">Loading purchase orders...</p>
                </div>

                <div id="empty-state" class="empty-state" style="display: none;">
                    <i class="fas fa-inbox"></i>
                    <h5 class="mt-3 text-muted">No purchase orders found</h5>
                    <p class="text-muted">You don't have any purchase orders yet.</p>
                </div>
            </div>
            </div>
            <!-- End Dashboard Page -->

            <!-- My Profile Page -->
            <div id="page-profile" class="page-content" style="display: none;">
                <div class="page-header mb-4">
                    <h1 class="page-title"><i class="fas fa-user me-2"></i>My Profile</h1>
                    <p class="page-subtitle">Manage your supplier account information</p>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 1rem; padding: 2rem;">
                            <h5 class="mb-4" style="color: var(--accent);"><i class="fas fa-building me-2"></i>Supplier Information</h5>
                            <form id="profile-form">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Company Name *</label>
                                        <input type="text" class="form-control" id="profile-company-name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Supplier Code</label>
                                        <input type="text" class="form-control" id="profile-supplier-code" readonly>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Contact Person</label>
                                        <input type="text" class="form-control" id="profile-contact-person">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" id="profile-email">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" id="profile-address">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">City</label>
                                        <input type="text" class="form-control" id="profile-city">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">State/Province</label>
                                        <input type="text" class="form-control" id="profile-state">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" id="profile-postal-code">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Country</label>
                                        <input type="text" class="form-control" id="profile-country">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="profile-phone">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Tax ID</label>
                                        <input type="text" class="form-control" id="profile-tax-id">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Payment Terms</label>
                                        <select class="form-select" id="profile-payment-terms">
                                            <option value="Net 30">Net 30</option>
                                            <option value="Net 60">Net 60</option>
                                            <option value="Net 90">Net 90</option>
                                            <option value="COD">Cash on Delivery (COD)</option>
                                            <option value="Prepaid">Prepaid</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" id="profile-notes" rows="3"></textarea>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="showPage('dashboard')">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 1rem; padding: 2rem;">
                            <h5 class="mb-4" style="color: var(--accent);"><i class="fas fa-user-circle me-2"></i>Account Details</h5>
                            <div class="mb-3">
                                <label class="form-label text-muted">Full Name</label>
                                <p class="fw-bold" id="profile-full-name">-</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Username</label>
                                <p class="fw-bold" id="profile-username">-</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Account Status</label>
                                <p><span class="badge bg-success" id="profile-status">Active</span></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted">Member Since</label>
                                <p class="fw-bold" id="profile-created-at">-</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Profile Page -->

            <!-- Order History Page -->
            <div id="page-po-history" class="page-content" style="display: none;">
                <div class="page-header mb-4">
                    <h1 class="page-title"><i class="fas fa-history me-2"></i>Order History</h1>
                    <p class="page-subtitle">View your completed and processed orders</p>
                </div>
                <div id="order-history-content">
                    <!-- Order history will be loaded here -->
                </div>
            </div>
            <!-- End Order History Page -->

            <!-- Settings Page -->
            <div id="page-settings" class="page-content" style="display: none;">
                <div class="page-header mb-4">
                    <h1 class="page-title"><i class="fas fa-cog me-2"></i>Settings</h1>
                    <p class="page-subtitle">Manage your account settings and preferences</p>
                </div>
                <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 1rem; padding: 2rem;">
                    <h5 class="mb-4" style="color: var(--accent);"><i class="fas fa-lock me-2"></i>Change Password</h5>
                    <form id="password-form">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current-password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new-password" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm-password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key me-2"></i>Update Password
                        </button>
                    </form>
                </div>
            </div>
            <!-- End Settings Page -->
        </div>
    </div>

    <!-- View PO Modal -->
    <div class="modal fade" id="viewPOModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 16px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" style="color: var(--accent);">
                        <i class="fas fa-file-invoice me-2"></i>Purchase Order Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="po-details-content">
                    <!-- PO details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Approve PO Confirmation Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 16px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" style="color: var(--success);">
                        <i class="fas fa-check-circle me-2"></i>Confirm Approval
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-question-circle" style="font-size: 3rem; color: var(--success); opacity: 0.7;"></i>
                    </div>
                    <h6 class="mb-3">Are you sure you want to approve this purchase order?</h6>
                    <p class="text-muted mb-0" id="approve-po-number">PO-2024-001</p>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-approve" id="confirm-approve-btn">
                        <i class="fas fa-check me-2"></i>Approve
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject PO Confirmation Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary); border: 1px solid var(--border-color); border-radius: 16px;">
                <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                    <h5 class="modal-title" style="color: var(--danger);">
                        <i class="fas fa-exclamation-triangle me-2"></i>Reject Purchase Order
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-4">
                    <h6 class="mb-3 text-center">Please provide a reason for rejecting this purchase order:</h6>
                    <p class="text-muted text-center mb-3" id="reject-po-number">PO-2024-001</p>
                    <div class="mb-3">
                        <textarea class="form-control" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: var(--text-primary); min-height: 100px;" id="reject-reason" placeholder="Enter rejection reason..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-reject" id="confirm-reject-btn">
                        <i class="fas fa-times me-2"></i>Reject PO
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success/Error Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="success-toast-message">Operation successful!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>

        <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <span id="error-toast-message">Operation failed!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_PATH = window.location.pathname.includes('/core1/') ? '/core1' : '';
        const API_BASE = BASE_PATH + '/backend/api';

        // Toast Notification System
        function showError(message) {
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-bg-danger border-0 position-fixed bottom-0 end-0 m-3';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-exclamation-circle me-2"></i>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        }

        function showSuccess(message) {
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-bg-success border-0 position-fixed bottom-0 end-0 m-3';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-check-circle me-2"></i>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        }

        function showInfo(message) {
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-bg-info border-0 position-fixed bottom-0 end-0 m-3';
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-info-circle me-2"></i>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            toast.addEventListener('hidden.bs.toast', () => toast.remove());
        }

        // Confirmation Dialog System
        function showConfirm(message, options = {}) {
            return new Promise((resolve) => {
                const {
                    title = 'Confirm Action',
                    confirmText = 'Confirm',
                    cancelText = 'Cancel',
                    type = 'warning',
                    icon = 'fas fa-exclamation-triangle'
                } = options;

                const overlay = document.createElement('div');
                overlay.className = 'confirm-overlay';

                const cancelButton = cancelText ? `
                    <button class="confirm-btn confirm-btn-cancel" data-action="cancel">
                        ${cancelText}
                    </button>
                ` : '';

                overlay.innerHTML = `
                    <div class="confirm-dialog">
                        <div class="confirm-header">
                            <div class="confirm-icon ${type}">
                                <i class="${icon}"></i>
                            </div>
                            <h5 class="confirm-title">${title}</h5>
                        </div>
                        <div class="confirm-body">${message}</div>
                        <div class="confirm-footer">
                            ${cancelButton}
                            <button class="confirm-btn confirm-btn-confirm ${type}" data-action="confirm">
                                ${confirmText}
                            </button>
                        </div>
                    </div>
                `;

                document.body.appendChild(overlay);

                overlay.addEventListener('click', (e) => {
                    if (e.target.dataset.action === 'confirm') {
                        overlay.remove();
                        resolve(true);
                    } else if (e.target.dataset.action === 'cancel' || (e.target.classList.contains('confirm-overlay') && cancelText)) {
                        overlay.remove();
                        resolve(false);
                    }
                });

                overlay.querySelector('[data-action="confirm"]').focus();

                const handleEsc = (e) => {
                    if (e.key === 'Escape' && cancelText) {
                        overlay.remove();
                        resolve(false);
                        document.removeEventListener('keydown', handleEsc);
                    }
                };
                document.addEventListener('keydown', handleEsc);
            });
        }

        // Check authentication
        async function checkAuth() {
            try {
                const response = await fetch(`${API_BASE}/auth/check.php`, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                    }
                });

                if (!response.ok) {
                    console.error('Auth check failed - HTTP error:', response.status);
                    window.location.href = 'index.php';
                    return null;
                }

                const data = await response.json();

                if (!data.success || !data.data) {
                    console.error('Auth check failed - invalid response structure');
                    window.location.href = 'index.php';
                    return null;
                }

                if (!data.data.authenticated || data.data.user.role !== 'supplier') {
                    console.error('Auth check failed - not authenticated or not supplier:', {
                        authenticated: data.data.authenticated,
                        role: data.data.user ? data.data.user.role : 'no user'
                    });
                    window.location.href = 'index.php';
                    return null;
                }

                // Update UI with user info
                if (data.data.user) {
                    const userNameElement = document.getElementById('user-name');
                    if (userNameElement) {
                        userNameElement.textContent = data.data.user.full_name || 'Supplier';
                    }
                }

                return data.data.user;
            } catch (error) {
                console.error('Auth check failed - network or JSON error:', error);
                window.location.href = 'index.php';
                return null;
            }
        }

        // Load purchase orders
        async function loadPurchaseOrders() {
            try {
                const response = await fetch(`${API_BASE}/supplier/purchase-orders.php`);
                const data = await response.json();

                document.getElementById('loading').style.display = 'none';

                if (data.success && data.data && data.data.purchase_orders.length > 0) {
                    renderPurchaseOrders(data.data.purchase_orders);
                    updateStats(data.data.purchase_orders);
                } else {
                    document.getElementById('empty-state').style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading POs:', error);
                document.getElementById('loading').style.display = 'none';
                document.getElementById('empty-state').style.display = 'block';
            }
        }

        // Render purchase orders
        function renderPurchaseOrders(orders) {
            const container = document.getElementById('po-container');
            container.innerHTML = orders.map(po => `
                <div class="po-card">
                    <div class="po-header">
                        <div>
                            <h5 style="color: var(--accent); margin-bottom: 0.25rem;">${po.po_number}</h5>
                            <small class="text-muted">Order Date: ${formatDate(po.order_date)}</small>
                        </div>
                        <span class="badge ${getStatusBadgeClass(po.status)} px-3 py-2">${getStatusText(po.status)}</span>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <small class="text-muted">Created By</small>
                            <div style="font-weight: 600;">${po.created_by_name || 'N/A'}</div>
                            <small class="text-muted">${po.created_by_email || ''}</small>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Expected Delivery</small>
                            <div style="font-weight: 600;">${po.expected_delivery_date ? formatDate(po.expected_delivery_date) : 'Not specified'}</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Items</small>
                            <div style="font-weight: 600;">${po.item_count || 0} item(s)</div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Total Amount</small>
                            <div style="font-weight: 700; color: var(--accent);">₱${parseFloat(po.total_amount).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                        </div>
                    </div>
                    ${po.notes ? `
                    <div class="mb-3">
                        <small class="text-muted"><i class="fas fa-sticky-note me-1"></i>Notes</small>
                        <div style="font-size: 0.9rem; padding: 0.5rem; background: rgba(0,0,0,0.2); border-radius: 0.5rem; margin-top: 0.25rem;">${po.notes}</div>
                    </div>
                    ` : ''}

                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewPODetails(${po.id})">
                            <i class="fas fa-eye me-2"></i>View Details
                        </button>
                        ${po.status === 'pending_supplier' ? `
                            <button class="btn btn-sm btn-approve" onclick="approvePO(${po.id}, '${po.po_number}')">
                                <i class="fas fa-check me-2"></i>Approve
                            </button>
                            <button class="btn btn-sm btn-reject" onclick="rejectPO(${po.id}, '${po.po_number}')">
                                <i class="fas fa-times me-2"></i>Reject
                            </button>
                        ` : ''}
                    </div>
                </div>
            `).join('');
        }

        // Update stats
        function updateStats(orders) {
            const pending = orders.filter(po => po.status === 'pending_supplier').length;
            const approved = orders.filter(po => po.status === 'approved').length;
            const rejected = orders.filter(po => po.status === 'rejected').length;

            document.getElementById('pending-count').textContent = pending;
            document.getElementById('approved-count').textContent = approved;
            document.getElementById('rejected-count').textContent = rejected;
        }

        // View PO details
        async function viewPODetails(poId) {
            try {
                const response = await fetch(`${API_BASE}/supplier/purchase-order-details.php?id=${poId}`);
                const data = await response.json();

                if (data.success) {
                    displayPODetails(data.data.purchase_order);
                    const modal = new bootstrap.Modal(document.getElementById('viewPOModal'));
                    modal.show();
                } else {
                    showToast('Failed to load purchase order details', 'error');
                }
            } catch (error) {
                console.error('Error loading PO details:', error);
                showToast('Failed to load purchase order details', 'error');
            }
        }

        // Display PO details
        function displayPODetails(po) {
            const content = document.getElementById('po-details-content');
            content.innerHTML = `
                <div class="mb-4">
                    <h6 style="color: var(--accent);"><i class="fas fa-info-circle me-2"></i>Order Information</h6>
                    <table class="table table-dark table-borderless" style="margin-bottom: 0;">
                        <tr>
                            <td style="width: 40%;"><strong>PO Number:</strong></td>
                            <td>${po.po_number || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td><strong>Order Date:</strong></td>
                            <td>${po.order_date ? formatDate(po.order_date) : 'N/A'}</td>
                        </tr>
                        <tr>
                            <td><strong>Expected Delivery:</strong></td>
                            <td>${po.expected_delivery_date ? formatDate(po.expected_delivery_date) : '<span class="text-warning">Not specified</span>'}</td>
                        </tr>
                        <tr>
                            <td><strong>Created By:</strong></td>
                            <td>${po.created_by_name || 'N/A'}${po.created_by_email ? ' (' + po.created_by_email + ')' : ''}</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><span class="badge ${getStatusBadgeClass(po.status)} px-3 py-2">${getStatusText(po.status)}</span></td>
                        </tr>
                        <tr>
                            <td><strong>Notes:</strong></td>
                            <td>${po.notes || '<span class="text-muted">No notes</span>'}</td>
                        </tr>
                    </table>
                </div>

                <div class="mb-3">
                    <h6 style="color: var(--accent);"><i class="fas fa-shopping-cart me-2"></i>Order Items (${po.items ? po.items.length : 0} item${po.items && po.items.length !== 1 ? 's' : ''})</h6>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead style="background: rgba(0, 245, 255, 0.1);">
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th class="text-end">Ordered</th>
                                    <th class="text-end">Received</th>
                                    <th class="text-end">Accepted</th>
                                    <th class="text-end">Rejected</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${po.items && po.items.length > 0 ? po.items.map((item, index) => {
                                    const quantityOrdered = parseInt(item.quantity_ordered || 0);
                                    const quantityReceived = parseInt(item.quantity_received || 0);
                                    const totalAccepted = parseInt(item.total_accepted || 0);
                                    const totalRejected = parseInt(item.total_rejected || 0);

                                    // Show status badges
                                    let statusBadge = '';
                                    if (quantityReceived > 0) {
                                        if (totalRejected > 0) {
                                            statusBadge = '<span class="badge bg-danger ms-2" style="font-size: 0.7rem;">Rejected</span>';
                                        } else if (totalAccepted > 0) {
                                            statusBadge = '<span class="badge bg-success ms-2" style="font-size: 0.7rem;">Accepted</span>';
                                        }
                                    }

                                    return `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td>
                                            <strong>${item.product_name || 'Unknown Product'}</strong>${statusBadge}
                                            ${item.notes ? `<br><small class="text-muted"><i class="fas fa-sticky-note me-1"></i>${item.notes}</small>` : ''}
                                        </td>
                                        <td><small class="text-muted">${item.product_sku || 'N/A'}</small></td>
                                        <td class="text-end"><strong>${quantityOrdered}</strong></td>
                                        <td class="text-end">${quantityReceived > 0 ? '<strong>' + quantityReceived + '</strong>' : '<span class="text-muted">-</span>'}</td>
                                        <td class="text-end">${totalAccepted > 0 ? '<strong style="color: var(--success);">' + totalAccepted + '</strong>' : '<span class="text-muted">-</span>'}</td>
                                        <td class="text-end">${totalRejected > 0 ? '<strong style="color: var(--danger);">' + totalRejected + '</strong>' : '<span class="text-muted">-</span>'}</td>
                                        <td class="text-end">₱${parseFloat(item.unit_cost || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</td>
                                        <td class="text-end"><strong>₱${(quantityOrdered * parseFloat(item.unit_cost || 0)).toLocaleString('en-US', {minimumFractionDigits: 2})}</strong></td>
                                    </tr>
                                `}).join('') : '<tr><td colspan="9" class="text-center text-muted">No items found</td></tr>'}
                            </tbody>
                            <tfoot style="background: rgba(0, 245, 255, 0.05); border-top: 2px solid var(--accent);">
                                <tr>
                                    <th colspan="8" class="text-end" style="padding: 1rem;">Total Amount:</th>
                                    <th class="text-end" style="color: var(--accent); font-size: 1.2rem; padding: 1rem;">₱${parseFloat(po.total_amount || 0).toFixed(2)}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            `;
        }

        // Approve PO - Show modal
        async function approvePO(poId, poNumber) {
            document.getElementById('approve-po-number').textContent = poNumber;
            const modal = new bootstrap.Modal(document.getElementById('approveModal'));
            modal.show();

            // Clear any previous listeners
            const confirmBtn = document.getElementById('confirm-approve-btn');
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            // Add event listener to approval button
            newConfirmBtn.addEventListener('click', async () => {
                modal.hide();
                await performApprovePO(poId, poNumber);
            });
        }

        // Perform actual approval
        async function performApprovePO(poId, poNumber) {
            try {
                const response = await fetch(`${API_BASE}/supplier/approve-po.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ po_id: poId })
                });

                const data = await response.json();

                if (data.success) {
                    showToast('Purchase order approved successfully!', 'success');
                    loadPurchaseOrders();
                } else {
                    showToast('Failed to approve: ' + (data.message || 'Unknown error'), 'error');
                }
            } catch (error) {
                console.error('Error approving PO:', error);
                showToast('Failed to approve purchase order', 'error');
            }
        }

        // Reject PO - Show modal
        async function rejectPO(poId, poNumber) {
            document.getElementById('reject-po-number').textContent = poNumber;
            document.getElementById('reject-reason').value = '';
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();

            // Clear any previous listeners
            const confirmBtn = document.getElementById('confirm-reject-btn');
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            // Add event listener to rejection button
            newConfirmBtn.addEventListener('click', async () => {
                const reason = document.getElementById('reject-reason').value.trim();
                if (!reason) {
                    showToast('Please provide a reason for rejection', 'error');
                    return;
                }
                modal.hide();
                await performRejectPO(poId, poNumber, reason);
            });
        }

        // Perform actual rejection
        async function performRejectPO(poId, poNumber, reason) {
            try {
                const response = await fetch(`${API_BASE}/supplier/reject-po.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ po_id: poId, reason: reason })
                });

                const data = await response.json();

                if (data.success) {
                    showToast('Purchase order rejected successfully', 'success');
                    loadPurchaseOrders();
                } else {
                    showToast('Failed to reject: ' + (data.message || 'Unknown error'), 'error');
                }
            } catch (error) {
                console.error('Error rejecting PO:', error);
                showToast('Failed to reject purchase order', 'error');
            }
        }

        // Show toast notifications
        function showToast(message, type = 'success') {
            const toastId = type === 'success' ? 'successToast' : 'errorToast';
            const messageId = type === 'success' ? 'success-toast-message' : 'error-toast-message';

            document.getElementById(messageId).textContent = message;

            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement);
            toast.show();
        }

        // Logout
        async function logout() {
            try {
                await fetch(`${API_BASE}/auth/logout.php`, {method: 'POST'});
                window.location.href = 'index.php';
            } catch (error) {
                window.location.href = 'index.php';
            }
        }

        // Helper functions
        function getStatusBadgeClass(status) {
            const classes = {
                'pending_supplier': 'badge-pending',
                'approved': 'badge-approved',
                'rejected': 'badge-rejected'
            };
            return classes[status] || 'bg-secondary';
        }

        function getStatusText(status) {
            const texts = {
                'pending_supplier': 'Pending Your Approval',
                'approved': 'Approved',
                'rejected': 'Rejected',
                'ordered': 'Ordered',
                'received': 'Received'
            };
            return texts[status] || status;
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        // Create PO Card HTML
        function createPOCard(po, isHistory = false) {
            return `
                <div class="po-card">
                    <div class="po-header">
                        <div>
                            <h5 class="mb-1" style="color: var(--accent);">PO #${po.po_number || po.id}</h5>
                            <p class="text-muted mb-0">
                                <i class="fas fa-calendar me-2"></i>${formatDate(po.order_date)}
                            </p>
                        </div>
                        <div>
                            <span class="badge ${getStatusBadgeClass(po.status)}">${getStatusText(po.status)}</span>
                        </div>
                    </div>
                    <div class="po-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <p class="text-muted mb-1"><i class="fas fa-user me-2"></i>Requested By</p>
                                <p class="fw-bold">${po.created_by_name || 'N/A'}</p>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted mb-1"><i class="fas fa-user-check me-2"></i>Approved By</p>
                                <p class="fw-bold">${po.approved_by_name || 'N/A'}</p>
                            </div>
                            <div class="col-md-4">
                                <p class="text-muted mb-1"><i class="fas fa-calendar-check me-2"></i>Required Date</p>
                                <p class="fw-bold">${po.expected_delivery_date ? formatDate(po.expected_delivery_date) : 'Not specified'}</p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <p class="text-muted mb-1"><i class="fas fa-sticky-note me-2"></i>Notes</p>
                            <p>${po.notes || 'No notes'}</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted mb-0">Total Amount</p>
                                <h4 class="mb-0" style="color: var(--accent);">₱${parseFloat(po.total_amount || 0).toFixed(2)}</h4>
                            </div>
                            <div class="d-flex gap-2">
                                <button onclick="viewPODetails(${po.id})" class="btn btn-view btn-sm">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </button>
                                ${!isHistory && po.status === 'pending_supplier' ? `
                                    <button onclick="approvePO(${po.id})" class="btn btn-approve btn-sm">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                    <button onclick="rejectPO(${po.id})" class="btn btn-reject btn-sm">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Mobile menu toggle
        document.getElementById('mobile-menu-toggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        });

        // Menu item navigation - Add click handlers to all menu items with data-page
        document.querySelectorAll('.menu-item[data-page]').forEach(item => {
            item.addEventListener('click', function() {
                const page = this.getAttribute('data-page');

                // Remove active class from all menu items
                document.querySelectorAll('.menu-item').forEach(mi => mi.classList.remove('active'));

                // Add active class to clicked item
                this.classList.add('active');

                // Navigate to page
                showPage(page);
            });
        });

        // Page navigation
        function showPage(page) {
            // Hide all pages
            document.querySelectorAll('.page-content').forEach(p => p.style.display = 'none');

            // Show the requested page
            const pageElement = document.getElementById(`page-${page}`);
            if (pageElement) {
                pageElement.style.display = 'block';

                // Save current page to localStorage
                localStorage.setItem('supplier_current_page', page);

                // Update sidebar menu active state
                document.querySelectorAll('.menu-item').forEach(item => {
                    item.classList.remove('active');
                });
                const activeMenuItem = document.querySelector(`.menu-item[data-page="${page}"]`);
                if (activeMenuItem) {
                    activeMenuItem.classList.add('active');
                }

                // Load page-specific data
                switch(page) {
                    case 'profile':
                        loadProfilePage();
                        break;
                    case 'purchase-orders':
                        loadPurchaseOrdersPage();
                        break;
                    case 'po-history':
                        loadOrderHistoryPage();
                        break;
                    case 'settings':
                        // Settings page is static, no need to load data
                        break;
                    case 'dashboard':
                        loadPurchaseOrders();
                        break;
                    default:
                        // Load dashboard by default
                        loadPurchaseOrders();
                        break;
                }
            }
        }

        // Load Profile Page
        async function loadProfilePage() {
            const user = await checkAuth();
            if (!user) return;

            try {
                // Fetch supplier data
                const response = await fetch(`${API_BASE}/suppliers/index.php`);
                const data = await response.json();

                if (data.success && data.data.suppliers) {
                    const supplier = data.data.suppliers.find(s => s.id == user.id);
                    if (supplier) {
                        // Populate form fields
                        document.getElementById('profile-company-name').value = supplier.company_name || '';
                        document.getElementById('profile-supplier-code').value = supplier.supplier_code || '';
                        document.getElementById('profile-contact-person').value = supplier.contact_person || '';
                        document.getElementById('profile-email').value = supplier.supplier_email || supplier.email || '';
                        document.getElementById('profile-address').value = supplier.address || '';
                        document.getElementById('profile-city').value = supplier.city || '';
                        document.getElementById('profile-state').value = supplier.state || '';
                        document.getElementById('profile-postal-code').value = supplier.postal_code || '';
                        document.getElementById('profile-country').value = supplier.country || 'Philippines';
                        document.getElementById('profile-phone').value = supplier.phone || '';
                        document.getElementById('profile-tax-id').value = supplier.tax_id || '';
                        document.getElementById('profile-payment-terms').value = supplier.payment_terms || 'Net 30';
                        document.getElementById('profile-notes').value = supplier.notes || '';

                        // Populate account details
                        document.getElementById('profile-full-name').textContent = user.full_name || user.username;
                        document.getElementById('profile-username').textContent = user.username;
                        document.getElementById('profile-status').textContent = user.is_active == 1 ? 'ACTIVE' : 'INACTIVE';
                        document.getElementById('profile-status').className = user.is_active == 1 ? 'badge bg-success' : 'badge bg-warning';
                        document.getElementById('profile-created-at').textContent = user.created_at ? formatDate(user.created_at) : 'N/A';
                    }
                }
            } catch (error) {
                console.error('Error loading profile:', error);
                showToast('Failed to load profile data', 'error');
            }
        }

        // Save Profile
        document.getElementById('profile-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const user = await checkAuth();
            if (!user) return;

            const formData = {
                company_name: document.getElementById('profile-company-name').value,
                contact_person: document.getElementById('profile-contact-person').value,
                supplier_email: document.getElementById('profile-email').value,
                address: document.getElementById('profile-address').value,
                city: document.getElementById('profile-city').value,
                state: document.getElementById('profile-state').value,
                postal_code: document.getElementById('profile-postal-code').value,
                country: document.getElementById('profile-country').value,
                phone: document.getElementById('profile-phone').value,
                tax_id: document.getElementById('profile-tax-id').value,
                payment_terms: document.getElementById('profile-payment-terms').value,
                notes: document.getElementById('profile-notes').value
            };

            try {
                const response = await fetch(`${API_BASE}/suppliers/update.php?id=${user.id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();
                if (data.success) {
                    showToast('Profile updated successfully', 'success');
                } else {
                    showToast(data.message || 'Failed to update profile', 'error');
                }
            } catch (error) {
                console.error('Error updating profile:', error);
                showToast('Failed to update profile', 'error');
            }
        });

        // Load Purchase Orders Page
        async function loadPurchaseOrdersPage() {
            const content = document.getElementById('purchase-orders-content');
            content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3">Loading purchase orders...</p></div>';

            try {
                const user = await checkAuth();
                const response = await fetch(`${API_BASE}/purchase_orders/pending.php?supplier_id=${user.id}`);
                const data = await response.json();

                if (data.success && data.data && data.data.length > 0) {
                    content.innerHTML = data.data.map(po => createPOCard(po)).join('');
                } else {
                    content.innerHTML = '<div class="empty-state text-center py-5"><i class="fas fa-inbox fa-4x text-muted mb-3"></i><h5>No pending purchase orders</h5><p class="text-muted">You don\'t have any pending orders to review.</p></div>';
                }
            } catch (error) {
                console.error('Error loading purchase orders:', error);
                content.innerHTML = '<div class="alert alert-danger">Failed to load purchase orders</div>';
            }
        }

        // Load Order History Page
        async function loadOrderHistoryPage() {
            const content = document.getElementById('order-history-content');
            content.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3">Loading order history...</p></div>';

            try {
                const user = await checkAuth();
                // Fetch all POs for this supplier (approved, rejected, received, etc.)
                const response = await fetch(`${API_BASE}/purchase_orders/index.php?supplier_id=${user.id}`);
                const data = await response.json();

                // Filter to show approved, rejected, received, partially_received, ordered POs
                const historyStatuses = ['approved', 'rejected', 'ordered', 'partially_received', 'received', 'cancelled'];
                const orders = (data.data?.purchase_orders || data.purchase_orders || [])
                    .filter(po => historyStatuses.includes(po.status));

                if (orders.length > 0) {
                    content.innerHTML = orders.map(po => createPOCard(po, true)).join('');
                } else {
                    content.innerHTML = '<div class="empty-state text-center py-5"><i class="fas fa-history fa-4x text-muted mb-3"></i><h5>No order history</h5><p class="text-muted">You don\'t have any completed orders yet.</p></div>';
                }
            } catch (error) {
                devLog('Error loading order history:', error);
                content.innerHTML = '<div class="alert alert-danger">Failed to load order history</div>';
            }
        }

        // Change Password
        document.getElementById('password-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            if (newPassword !== confirmPassword) {
                showToast('Passwords do not match', 'error');
                return;
            }

            if (newPassword.length < 6) {
                showToast('Password must be at least 6 characters', 'error');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/auth/change-password.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword
                    })
                });

                const data = await response.json();
                if (data.success) {
                    showToast('Password updated successfully', 'success');
                    this.reset();
                } else {
                    showToast(data.message || 'Failed to update password', 'error');
                }
            } catch (error) {
                console.error('Error updating password:', error);
                showToast('Failed to update password', 'error');
            }
        });

        // Refresh orders function
        async function refreshOrders() {
            const container = document.getElementById('po-container');
            const loading = document.getElementById('loading');
            const emptyState = document.getElementById('empty-state');

            // Show loading state
            container.innerHTML = '';
            loading.style.display = 'block';
            emptyState.style.display = 'none';

            // Reload orders
            await loadPurchaseOrders();

            // Show success toast
            showToast('Purchase orders refreshed', 'success');
        }

        // Update user avatar
        function updateUserAvatar(user) {
            const avatar = document.getElementById('user-avatar');
            const name = document.getElementById('user-name');

            if (user && user.full_name) {
                const initials = user.full_name.split(' ')
                    .map(n => n[0])
                    .join('')
                    .toUpperCase()
                    .substring(0, 2);
                avatar.textContent = initials;
                name.textContent = user.full_name;
            }
        }

        // ==========================================
        // NOTIFICATION FUNCTIONS
        // ==========================================

        // Load notifications
        async function loadNotifications() {
            try {
                const response = await fetch(`${API_BASE}/notifications/index.php`);
                const data = await response.json();

                if (data.success) {
                    renderNotifications(data.data.notifications);
                    updateNotificationBadge(data.data.unread_count);
                }
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }

        // Render notifications
        function renderNotifications(notifications) {
            const container = document.getElementById('notification-list');

            if (!notifications || notifications.length === 0) {
                container.innerHTML = `
                    <div class="empty-notifications">
                        <i class="fas fa-bell-slash"></i>
                        <p class="text-muted mb-0">No notifications yet</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = notifications.map(notification => {
                const unreadClass = notification.is_read == 0 ? 'unread' : '';
                const typeClass = `notification-type-${notification.type}`;

                return `
                    <div class="notification-item ${unreadClass}" onclick="handleNotificationClick(${notification.id}, '${notification.action_url || ''}')">
                        <div class="notification-title">
                            <span class="notification-type-badge ${typeClass}">
                                ${notification.type.toUpperCase()}
                            </span>
                            ${notification.title}
                        </div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">
                            <i class="fas fa-clock me-1"></i>${formatTimeAgo(notification.created_at)}
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Update notification badge
        function updateNotificationBadge(count) {
            const badge = document.getElementById('notification-badge');
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        // Handle notification click
        async function handleNotificationClick(notificationId, actionUrl) {
            try {
                // Mark as read
                await fetch(`${API_BASE}/notifications/mark-read.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ notification_id: notificationId })
                });

                // Reload notifications
                await loadNotifications();

                // Navigate to action URL if provided
                if (actionUrl) {
                    // Close dropdown
                    document.getElementById('notification-dropdown').classList.remove('show');

                    // If it's a relative URL within the app, handle navigation
                    if (actionUrl.startsWith('/')) {
                        // For now, just show a toast - you can implement navigation logic here
                        showInfo('Click "View Details" on the related purchase order to see more');
                    }
                }
            } catch (error) {
                console.error('Error handling notification click:', error);
            }
        }

        // Mark all notifications as read
        async function markAllAsRead() {
            try {
                const response = await fetch(`${API_BASE}/notifications/mark-read.php`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ mark_all: true })
                });

                const data = await response.json();
                if (data.success) {
                    showSuccess('All notifications marked as read');
                    await loadNotifications();
                }
            } catch (error) {
                console.error('Error marking all as read:', error);
                showError('Failed to mark notifications as read');
            }
        }

        // Format time ago
        function formatTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);

            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';

            return formatDate(dateString);
        }

        // Toggle notification dropdown
        document.getElementById('notification-bell').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('notification-dropdown');
            dropdown.classList.toggle('show');

            // Load notifications when opening dropdown
            if (dropdown.classList.contains('show')) {
                loadNotifications();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notification-dropdown');
            const bell = document.getElementById('notification-bell');

            if (!bell.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Poll for new notifications every 30 seconds
        setInterval(async () => {
            try {
                const response = await fetch(`${API_BASE}/notifications/count.php`);
                const data = await response.json();
                if (data.success) {
                    updateNotificationBadge(data.data.unread_count);
                }
            } catch (error) {
                console.error('Error polling notifications:', error);
            }
        }, 30000);

        // Initialize
        (async () => {
            const user = await checkAuth();
            if (user) {
                updateUserAvatar(user);

                // Load initial notification count
                loadNotifications();

                // Restore last visited page or default to dashboard
                const savedPage = localStorage.getItem('supplier_current_page');
                if (savedPage && document.getElementById(`page-${savedPage}`)) {
                    showPage(savedPage);
                } else {
                    // Default to dashboard, which shows purchase orders
                    await loadPurchaseOrders();
                }
            }
        })();
    </script>
</body>
</html>
