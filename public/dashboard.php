<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PC Parts Central</title>
    <link rel="icon" type="image/png" href="ppc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Icon preload for faster FontAwesome rendering -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="prefetch" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/icon-optimizations.css">
    <!-- CUSTOM CHECKBOX SYSTEM - Modern redesign for entire application -->
    <link rel="stylesheet" href="assets/css/checkboxes.css?v=1.0">
    <!-- User Manual System -->
    <link rel="stylesheet" href="assets/css/user-manual.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body {
            overflow-x: hidden;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
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
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-brand i {
            color: var(--accent);
            font-size: 1.5rem;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-section {
            margin-bottom: 1.5rem;
        }

        .menu-section-title {
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
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
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
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

        .trend-up {
            color: var(--success);
        }

        .trend-down {
            color: var(--danger);
        }

        /* Charts */
        .chart-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-header {
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        /* Content Pages */
        #page-content {
            min-height: 60vh;
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

            /* Add overlay when sidebar is open on mobile */
            .sidebar.show::before {
                content: '';
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.5);
                z-index: -1;
            }
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Modal backdrop fix */
        .modal-backdrop {
            background-color: rgba(10, 14, 39, 0.8);
        }

        /* Help Button */
        .help-button {
            position: fixed;
            top: 20px;
            right: 15%;
            width: 50px;
            height: 50px;
            background: var(--accent);
            border: none;
            border-radius: 50%;
            color: white;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1001;
            transition: all 0.3s ease;
        }

        .help-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
        }

        /* Custom checkbox styling for dark theme */
        .form-check-input {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
        }

        .form-check-input:checked {
            background-color: var(--accent);
            border-color: var(--accent);
        }

        .form-check-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.2rem rgba(0, 245, 255, 0.25);
        }

        /* Form switch styling for dark theme */
        .form-check-input[type="checkbox"] {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
        }

        .form-switch .form-check-input {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%28255,255,255,0.25%29'/%3e%3c/svg%3e");
        }

        .form-switch .form-check-input:checked {
            background-color: var(--accent);
            border-color: var(--accent);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23ffffff'/%3e%3c/svg%3e");
        }
    </style>
</head>
<body>
    <!-- Toast Notification Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="sidebar-brand d-flex align-items-center">
                <img src="ppc.png" alt="Inventory Management System Logo" style="height: 48px; width: auto; margin-right: 8px;">
                <span>Inventory Management System</span>
            </a>
        </div>

        <div class="sidebar-menu" id="sidebar-menu">
            <!-- Menu will be dynamically loaded based on user role -->
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
                <h5 class="mb-0" style="color: var(--text-primary);" id="page-title-top">Dashboard</h5>
            </div>

            <div class="topbar-right">
                <!-- View Shop button will be inserted here by JavaScript -->
                <span id="view-shop-button"></span>

                <!-- Help/Manual Button -->
                <button class="help-button" onclick="openUserManual()" title="User Manual">
                    <i class="fas fa-question"></i>
                </button>

                <div class="dropdown">
                    <button class="user-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="user-avatar" id="user-avatar">A</div>
                        <div class="d-none d-md-block">
                            <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-primary);" id="user-name">Loading...</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);" id="current-user-role">Role</div>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: var(--text-muted);"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                        <li><button class="dropdown-item" onclick="showPage('profile')"><i class="fas fa-user me-2"></i>My Profile</button></li>
                        <li id="settings-dropdown-item"><button class="dropdown-item" onclick="showPage('settings')"><i class="fas fa-cog me-2"></i>Settings</button></li>
                        <li><hr class="dropdown-divider" style="border-color: var(--border-color);"></li>
                        <li><button class="dropdown-item text-danger" onclick="logout()"><i class="fas fa-sign-out-alt me-2"></i>Logout</button></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content-wrapper">
            <div id="page-content">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Load Bootstrap and helper functions BEFORE dashboard-pages.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const IS_DEVELOPMENT = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

        // Auto-detect base path: /core1 for local dev, empty for production
        const BASE_PATH = IS_DEVELOPMENT ? '/core1' : '';
        const API_BASE = BASE_PATH + '/backend/api';
        const ASSETS_BASE = BASE_PATH + '/public/assets';

        // Expose BASE_PATH globally for dashboard-pages.js
        window.BASE_PATH = BASE_PATH;
        window.API_BASE = API_BASE;
        window.ASSETS_BASE = ASSETS_BASE;

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

        function showToast(message, type = 'info', duration = 4000) {
            const container = document.getElementById('toast-container');
            if (!container) return;

            const icons = {
                success: 'fas fa-check-circle',
                error: 'fas fa-times-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };

            const titles = {
                success: 'Success',
                error: 'Error',
                warning: 'Warning',
                info: 'Info'
            };

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="${icons[type] || icons.info}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${titles[type] || titles.info}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            `;

            container.appendChild(toast);

            // Auto remove after duration
            if (duration > 0) {
                setTimeout(() => {
                    toast.classList.add('removing');
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }

            return toast;
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
    </script>

    <!-- Inactivity Monitor -->
    <script src="assets/js/inactivity-monitor.js?v=3.3"></script>

    <!-- Include all page loaders -->
    <script src="assets/js/dashboard-pages.js?v=2.5"></script>

    <!-- NEW: Complete GRN Management System -->
    <script src="assets/js/grn-new.js?v=3.5"></script>

    <!-- Audit Logs Page -->
    <script src="assets/js/audit-logs-page.js?v=1.0"></script>

    <!-- User Manual System -->
    <script src="assets/js/user-manual.js"></script>

    <script>
        let currentUser = null;
        let currentPage = 'home';

        // Get URL parameter
        function getUrlParameter(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }

        // Update URL without reloading page
        function updateUrlParameter(page) {
            const url = new URL(window.location);
            if (page === 'home') {
                url.searchParams.delete('page');
            } else {
                url.searchParams.set('page', page);
            }
            window.history.pushState({page: page}, '', url);
        }

        // Handle browser back/forward buttons
        window.addEventListener('popstate', (event) => {
            if (event.state && event.state.page) {
                showPage(event.state.page, false); // Don't update URL again
            } else {
                showPage('home', false);
            }
        });

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', async () => {
            await checkAuth();
            initMobileMenu();
            await initInactivityMonitor();

            // Check URL parameter for initial page
            const initialPage = getUrlParameter('page') || 'home';
            showPage(initialPage);
        });

        // Initialize inactivity monitor with user's saved preference
        async function initInactivityMonitor() {
            try {
                console.log('⚙️ Loading inactivity timeout from settings...');
                const response = await fetch(`${API_BASE}/settings/index.php`);
                const data = await response.json();

                // Settings are nested in data.data.settings (not data.settings)
                const settingsArray = data.data?.settings || data.settings || [];

                if (data.success && settingsArray && Array.isArray(settingsArray)) {
                    console.log('📋 Settings array found with', settingsArray.length, 'items');
                    // Find inactivity_timeout setting
                    const setting = settingsArray.find(s => s.setting_key === 'inactivity_timeout');
                    console.log('🔍 Found inactivity_timeout setting:', setting);
                    const timeoutMinutes = parseInt(setting?.setting_value) || 30;
                    console.log('✅ LOADED FROM DATABASE:', timeoutMinutes, 'minutes');

                    // Initialize monitor with saved setting
                    if (typeof initializeInactivityMonitor === 'function') {
                        initializeInactivityMonitor(timeoutMinutes);
                    }
                } else {
                    console.warn('⚠️ No settings array found, using default 30 minutes');
                    if (typeof initializeInactivityMonitor === 'function') {
                        initializeInactivityMonitor(30);
                    }
                }
            } catch (error) {
                console.error('❌ Failed to load setting:', error);
                // Fallback to default 30 minutes
                if (typeof initializeInactivityMonitor === 'function') {
                    initializeInactivityMonitor(30);
                }
            }
        }

        // Check authentication
        async function checkAuth() {
            try {
                const response = await fetch(`${API_BASE}/auth/me.php`, {
                    credentials: 'same-origin'
                });
                const data = await response.json();

                if (!data.success || !data.data || (data.data && data.data.authenticated === false)) {
                    // Use redirect URL from API response, fallback to simple-login.php
                    const redirectUrl = data.data && data.data.redirect ? data.data.redirect : 'simple-login.php';
                    window.location.href = redirectUrl;
                    return;
                }

                currentUser = data.data;
                updateUserUI();
                loadSidebarMenu();
            } catch (error) {
                window.location.href = 'simple-login.php';
            }
        }

        // Update user UI
        function updateUserUI() {
            document.getElementById('user-name').textContent = currentUser.full_name || currentUser.username;
            document.getElementById('current-user-role').textContent = formatRole(currentUser.role);

            const initials = (currentUser.full_name || currentUser.username)
                .split(' ')
                .map(n => n[0])
                .join('')
                .toUpperCase()
                .substring(0, 2);
            document.getElementById('user-avatar').textContent = initials;

            // View Shop button removed for all users

            // Show Settings dropdown only for admin users
            const settingsDropdownItem = document.getElementById('settings-dropdown-item');
            if (settingsDropdownItem) {
                if (currentUser.role === 'admin') {
                    settingsDropdownItem.style.display = '';
                } else {
                    settingsDropdownItem.style.display = 'none';
                }
            }
        }

        // Make updateUserUI available globally
        window.updateUserUI = updateUserUI;

        // Format role name
        function formatRole(role) {
            const roles = {
                'admin': 'Administrator',
                'inventory_manager': 'Inventory Manager',
                'purchasing_officer': 'Purchasing Officer',
                'staff': 'Staff Member',
                'supplier': 'Supplier'
            };
            return roles[role] || role;
        }

        // Load sidebar menu based on role
        function loadSidebarMenu() {
            const menus = {
                supplier: [
                    {
                        section: 'Main',
                        items: [
                            { icon: 'fa-home', label: 'Dashboard', page: 'home' }
                        ]
                    },
                    {
                        section: 'Purchase Orders',
                        items: [
                            { icon: 'fa-file-invoice', label: 'Pending Approval', page: 'purchase-orders' },
                            { icon: 'fa-history', label: 'Order History', page: 'po-history' }
                        ]
                    },
                    {
                        section: 'Profile',
                        items: [
                            { icon: 'fa-user', label: 'My Profile', page: 'profile' },
                            { icon: 'fa-cog', label: 'Settings', page: 'settings' }
                        ]
                    }
                ],
                admin: [
                    {
                        section: 'Daily Operations',
                        items: [
                            { icon: 'fa-home', label: 'Dashboard', page: 'home' }
                        ]
                    },
                    {
                        section: 'Product Management',
                        items: [
                            { icon: 'fa-box', label: 'Products', page: 'products' },
                            { icon: 'fa-exchange-alt', label: 'Stock Adjustments', page: 'adjustments' },
                            { icon: 'fa-layer-group', label: 'Categories', page: 'categories' }
                        ]
                    },
                    {
                        section: 'Purchasing',
                        items: [
                            { icon: 'fa-truck', label: 'Suppliers', page: 'suppliers' },
                            { icon: 'fa-file-invoice', label: 'Purchase Orders', page: 'purchase-orders' },
                            { icon: 'fa-clipboard-check', label: 'Goods Received', page: 'grn' }
                        ]
                    },
                    {
                        section: 'Administration',
                        items: [
                            { icon: 'fa-users', label: 'User Management', page: 'users' },
                            { icon: 'fa-history', label: 'Audit Logs', page: 'logs' }
                        ]
                    }
                ],
                inventory_manager: [
                    {
                        section: 'Main',
                        items: [
                            { icon: 'fa-home', label: 'Dashboard', page: 'home' },
                        ]
                    },
                    {
                        section: 'Product Management',
                        items: [
                            { icon: 'fa-box', label: 'Products', page: 'products' },
                            { icon: 'fa-exchange-alt', label: 'Stock Adjustments', page: 'adjustments' },
                            { icon: 'fa-clipboard-check', label: 'Goods Received', page: 'grn' }
                        ]
                    }
                ],
                purchasing_officer: [
                    {
                        section: 'Main',
                        items: [
                            { icon: 'fa-home', label: 'Dashboard', page: 'home' },
                        ]
                    },
                    {
                        section: 'Purchasing',
                        items: [
                            { icon: 'fa-truck', label: 'Suppliers', page: 'suppliers' },
                            { icon: 'fa-file-invoice', label: 'Purchase Orders', page: 'purchase-orders' },
                            { icon: 'fa-clipboard-check', label: 'Goods Received', page: 'grn' }
                        ]
                    }
                ],
                staff: [
                    {
                        section: 'Main',
                        items: [
                            { icon: 'fa-home', label: 'Dashboard', page: 'home' },
                        ]
                    },
                    {
                        section: 'Sales',
                        items: [
                            { icon: 'fa-receipt', label: 'Sales History', page: 'sales' }
                        ]
                    },
                    {
                        section: 'Products',
                        items: [
                            { icon: 'fa-search', label: 'Product Lookup', page: 'products' }
                        ]
                    }
                ]
            };

            const userMenu = menus[currentUser.role] || menus.staff;
            const menuHTML = userMenu.map(section => `
                <div class="menu-section">
                    <div class="menu-section-title">${section.section}</div>
                    ${section.items.map(item => `
                        <a class="menu-item" onclick="showPage('${item.page}')" data-page="${item.page}">
                            <i class="fas ${item.icon}"></i>
                            <span>${item.label}</span>
                        </a>
                    `).join('')}
                </div>
            `).join('');

            document.getElementById('sidebar-menu').innerHTML = menuHTML;
        }

        // Show page
        async function loadSupplierPendingOrdersPage() {
            const content = document.getElementById('page-content');
            content.innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4>Pending Purchase Orders</h4>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Date</th>
                                        <th>Expected Delivery</th>
                                        <th>Total Items</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="pending-po-table">
                                    <!-- Will be populated dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            try {
                const response = await fetch(`${API_BASE}/purchase_orders/pending.php?supplier_id=${currentUser.id}`);
                const orders = await response.json();
                
                const tableBody = document.getElementById('pending-po-table');
                tableBody.innerHTML = orders.map(order => `
                    <tr>
                        <td>${order.po_number}</td>
                        <td>${formatDate(order.created_at)}</td>
                        <td>${formatDate(order.expected_delivery)}</td>
                        <td>${order.total_items}</td>
                        <td>${formatCurrency(order.total_amount)}</td>
                        <td><span class="badge bg-warning">Pending</span></td>
                        <td>
                            <button onclick="viewPODetails('${order.id}')" class="btn btn-sm btn-primary me-2">View</button>
                            <button onclick="approvePO('${order.id}')" class="btn btn-sm btn-success me-2">Approve</button>
                            <button onclick="rejectPO('${order.id}')" class="btn btn-sm btn-danger">Reject</button>
                        </td>
                    </tr>
                `).join('');
            } catch (error) {
                showError('Failed to load pending purchase orders');
            }
        }

        async function loadSupplierOrderHistoryPage() {
            const content = document.getElementById('page-content');
            content.innerHTML = `
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4>Purchase Order History</h4>
                            <div class="d-flex gap-2">
                                <select id="status-filter" class="form-select">
                                    <option value="all">All Status</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Date</th>
                                        <th>Total Items</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="po-history-table">
                                    <!-- Will be populated dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;

            try {
                const response = await fetch(`${API_BASE}/purchase_orders/history.php?supplier_id=${currentUser.id}`);
                const orders = await response.json();
                
                const tableBody = document.getElementById('po-history-table');
                tableBody.innerHTML = orders.map(order => {
                    let statusBadge = '';
                    switch(order.status) {
                        case 'approved':
                            statusBadge = '<span class="badge bg-success">Approved</span>';
                            break;
                        case 'rejected':
                            statusBadge = '<span class="badge bg-danger">Rejected</span>';
                            break;
                        case 'completed':
                            statusBadge = '<span class="badge bg-primary">Completed</span>';
                            break;
                    }
                    
                    return `
                        <tr>
                            <td>${order.po_number}</td>
                            <td>${formatDate(order.created_at)}</td>
                            <td>${order.total_items}</td>
                            <td>${formatCurrency(order.total_amount)}</td>
                            <td>${statusBadge}</td>
                            <td>
                                <button onclick="viewPODetails('${order.id}')" class="btn btn-sm btn-primary">View</button>
                            </td>
                        </tr>
                    `;
                }).join('');

                // Add event listener for status filter
                document.getElementById('status-filter').addEventListener('change', async (e) => {
                    const status = e.target.value;
                    const url = status === 'all' 
                        ? `${API_BASE}/purchase_orders/history.php?supplier_id=${currentUser.id}`
                        : `${API_BASE}/purchase_orders/history.php?supplier_id=${currentUser.id}&status=${status}`;
                    
                    const response = await fetch(url);
                    const filteredOrders = await response.json();
                    tableBody.innerHTML = filteredOrders.map(order => {
                        let statusBadge = '';
                        switch(order.status) {
                            case 'approved':
                                statusBadge = '<span class="badge bg-success">Approved</span>';
                                break;
                            case 'rejected':
                                statusBadge = '<span class="badge bg-danger">Rejected</span>';
                                break;
                            case 'completed':
                                statusBadge = '<span class="badge bg-primary">Completed</span>';
                                break;
                        }
                        
                        return `
                            <tr>
                                <td>${order.po_number}</td>
                                <td>${formatDate(order.created_at)}</td>
                                <td>${order.total_items}</td>
                                <td>${formatCurrency(order.total_amount)}</td>
                                <td>${statusBadge}</td>
                                <td>
                                    <button onclick="viewPODetails('${order.id}')" class="btn btn-sm btn-primary">View</button>
                                </td>
                            </tr>
                        `;
                    }).join('');
                });
            } catch (error) {
                showError('Failed to load purchase order history');
            }
        }

        async function viewPODetails(id) {
            try {
                const response = await fetch('/backend/api/purchase_orders/' + id);
                const po = await response.json();

                const content = document.getElementById('page-content');
                content.innerHTML = `
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h4>Purchase Order Details</h4>
                                <button onclick="showPage('${currentUser.role === 'supplier' ? 'purchase-orders' : 'po-history'}')" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back
                                </button>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5>Order Information</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>PO Number:</strong></td>
                                            <td>${po.po_number}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Date Created:</strong></td>
                                            <td>${formatDate(po.created_at)}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Expected Delivery:</strong></td>
                                            <td>${formatDate(po.expected_delivery)}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>${formatPOStatus(po.status)}</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h5>Supplier Information</h5>
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Name:</strong></td>
                                            <td>${po.supplier.name}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td>${po.supplier.email}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Phone:</strong></td>
                                            <td>${po.supplier.phone}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <h5>Order Items</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
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
                                        ${po.items.map(item => `
                                            <tr>
                                                <td>${item.product_name}</td>
                                                <td>${item.sku}</td>
                                                <td>${item.quantity}</td>
                                                <td>${formatCurrency(item.unit_price)}</td>
                                                <td>${formatCurrency(item.quantity * item.unit_price)}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Total Amount:</strong></td>
                                            <td><strong>${formatCurrency(po.total_amount)}</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            ${po.status === 'pending' && currentUser.role === 'supplier' ? `
                                <div class="mt-4">
                                    <button onclick="approvePO('${po.id}')" class="btn btn-success me-2">Approve Order</button>
                                    <button onclick="rejectPO('${po.id}')" class="btn btn-danger">Reject Order</button>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            } catch (error) {
                showError('Failed to load purchase order details');
            }
        }

        async function approvePO(id) {
            try {
                const response = await fetch('/backend/api/purchase_orders/approve/' + id, {
                    method: 'POST'
                });
                
                if (response.ok) {
                    showSuccess('Purchase order approved successfully');
                    showPage('purchase-orders');
                } else {
                    throw new Error('Failed to approve purchase order');
                }
            } catch (error) {
                showError(error.message);
            }
        }

        async function rejectPO(id) {
            const reason = await showPrompt('Please provide a reason for rejection:');
            if (!reason) return;

            try {
                const response = await fetch('/backend/api/purchase_orders/reject/' + id, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ reason })
                });
                
                if (response.ok) {
                    showSuccess('Purchase order rejected successfully');
                    showPage('purchase-orders');
                } else {
                    throw new Error('Failed to reject purchase order');
                }
            } catch (error) {
                showError(error.message);
            }
        }

        function formatPOStatus(status) {
            const statusMap = {
                'pending': '<span class="badge bg-warning">Pending</span>',
                'approved': '<span class="badge bg-success">Approved</span>',
                'rejected': '<span class="badge bg-danger">Rejected</span>',
                'completed': '<span class="badge bg-primary">Completed</span>'
            };
            return statusMap[status] || status;
        }

        async function showPage(pageName, updateUrl = true, filter = null) {
            currentPage = pageName;

            // Store filter parameter globally for pages to access
            window.currentFilter = filter;

            // Update URL if requested
            if (updateUrl) {
                updateUrlParameter(pageName);
            }

            // Update active menu item
            document.querySelectorAll('.menu-item').forEach(item => {
                if (item.dataset.page === pageName) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Update page title
            const pageTitle = document.querySelector(`[data-page="${pageName}"] span`)?.textContent || 'Dashboard';
            document.getElementById('page-title-top').textContent = pageTitle;

            // Load page content
            const content = document.getElementById('page-content');
            content.innerHTML = '<div class="text-center py-5"><div class="spinner"></div></div>';

            try {
                switch(pageName) {
                    case 'home':
                        await loadHomePage();
                        break;
                    case 'products':
                        await loadProductsPage();
                        break;
                    case 'categories':
                        await loadCategoriesPage();
                        break;
                    case 'adjustments':
                        await loadStockAdjustmentsPage();
                        break;
                    case 'suppliers':
                        await loadSuppliersPage();
                        break;
                    case 'purchase-orders':
                        if (currentUser.role === 'supplier') {
                            await loadSupplierPendingOrdersPage();
                        } else {
                            await loadPurchaseOrdersPage();
                        }
                        break;
                    case 'po-history':
                        await loadSupplierOrderHistoryPage();
                        break;
                    case 'grn':
                        await loadGRNPage();
                        break;
                    case 'pos':
                        await loadPOSPage();
                        break;
                    case 'sales':
                        await loadSalesPage();
                        break;
                    case 'users':
                        await loadUsersPage();
                        break;
                    case 'logs':
                        await loadActivityLogsPage();
                        break;
                    case 'settings':
                        await loadSettingsPage();
                        break;
                    case 'profile':
                        await loadProfilePage();
                        break;


                    default:
                        content.innerHTML = `
                            <div class="text-center py-5">
                                <i class="fas fa-tools fa-4x text-muted mb-3" style="opacity: 0.3;"></i>
                                <h4 class="text-muted">Page Not Found</h4>
                                <p class="text-muted">The requested page does not exist.</p>
                            </div>
                        `;
                }
            } catch (error) {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading page. Please try again.
                    </div>
                `;
            }

            // Close mobile menu
            document.getElementById('sidebar').classList.remove('show');
        }

        // Logout
        async function logout() {
            if (await showConfirm('Are you sure you want to logout?', {
                title: 'Confirm Logout',
                confirmText: 'Logout',
                type: 'warning',
                icon: 'fas fa-sign-out-alt'
            })) {
                try {
                    await fetch(`${API_BASE}/auth/logout.php`, {
                        method: 'POST',
                        credentials: 'same-origin'
                    });
                } catch (error) {
                }
                window.location.href = 'simple-login.php';
            }
        }

        // Mobile menu
        function initMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const mobileToggle = document.getElementById('mobile-menu-toggle');
            const mainContent = document.querySelector('.main-content');

            // Toggle sidebar when menu button is clicked
            mobileToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('show');
            });

            // Close sidebar when clicking outside
            mainContent.addEventListener('click', () => {
                if (sidebar.classList.contains('show')) {
                    sidebar.classList.remove('show');
                }
            });

            // Close sidebar when clicking on sidebar background (but not on menu items)
            sidebar.addEventListener('click', (e) => {
                // Only close if clicking on the sidebar itself, not on menu items
                if (e.target === sidebar) {
                    sidebar.classList.remove('show');
                }
            });

            // Prevent sidebar from closing when clicking on menu items (let the navigation handle it)
            const menuItems = sidebar.querySelectorAll('.menu-item');
            menuItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    // Close sidebar after a brief delay to allow navigation to process
                    setTimeout(() => {
                        sidebar.classList.remove('show');
                    }, 100);
                });
            });
        }

    </script>

    <!-- Supplier Modal -->
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
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier Name *</label>
                                <input type="text" class="form-control" id="supplier-name" required placeholder="Enter supplier/company name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="supplier-contact" placeholder="Main contact person">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="supplier-email" placeholder="contact@supplier.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="supplier-phone" placeholder="+1 (555) 000-0000">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" id="supplier-address" rows="2" placeholder="Street address, city, state, ZIP"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
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
                                Active Supplier
                            </label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="supplier-notes" rows="2"></textarea>
                        </div>
                        <div id="supplier-message"></div>
                    </form>
                </div>
                <div class="modal-footer" style="border-color: var(--border-color);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveSupplierBtn">Save Supplier</button>
                </div>
            </div>
        </div>
    </div>

    <!-- HOME PAGE LOADER -->
    <script>
        async function loadHomePage() {
            if (currentUser.role === 'supplier') {
                await loadSupplierHomePage();
                return;
            }
            
            const content = document.getElementById('page-content');

            try {
                // Fetch comprehensive dashboard data from the main dashboard API
                const dashboardResponse = await fetch(`${API_BASE}/dashboard/index.php`);
                const dashboardData = await dashboardResponse.json();

                if (!dashboardResponse.ok) {
                    throw new Error(`Dashboard API error: ${dashboardResponse.status} ${dashboardResponse.statusText}`);
                }

                const dashboard = dashboardData.data || {};

                // Also fetch basic stats for backward compatibility
                const statsResponse = await fetch(`${API_BASE}/dashboard/stats.php`);
                const statsData = await statsResponse.json();
                const stats = statsData.data || {};

            const isStaff = currentUser.role === 'staff';

            content.innerHTML = `
                <div class="page-header">
                    <h1 class="page-title"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
                    <p class="page-subtitle">Welcome back, ${currentUser.full_name || currentUser.username}!</p>
                </div>

                <!-- Customer Overview Section (Hidden for Staff) -->
                ${!isStaff && dashboard.summary?.customers ? `
                <div class="mb-4">
                    <h4 class="mb-3"><i class="fas fa-users me-2"></i>Customer Overview</h4>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value">${dashboard.summary?.customers?.total_customers || 0}</div>
                                    <div class="stat-label">Total Customers</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(0, 245, 255, 0.1); color: var(--accent);">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                            </div>
                            <div class="stat-trend trend-up">
                                <i class="fas fa-user-plus"></i>
                                <span>Registered users</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value">${dashboard.summary?.customers?.total_orders || 0}</div>
                                    <div class="stat-label">Total Orders</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                            </div>
                            <div class="stat-trend trend-up">
                                <i class="fas fa-chart-line"></i>
                                <span>Order volume</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value">₱${(dashboard.summary?.customers?.avg_order_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                                    <div class="stat-label">Avg Order Value</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">
                                    <i class="fas fa-calculator"></i>
                                </div>
                            </div>
                            <div class="stat-trend trend-neutral">
                                <i class="fas fa-balance-scale"></i>
                                <span>Per transaction</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value">${dashboard.top_performers?.customers?.length || 0}</div>
                                    <div class="stat-label">Top Customers</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(255, 170, 0, 0.1); color: var(--warning);">
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                            <div class="stat-trend trend-up">
                                <i class="fas fa-trophy"></i>
                                <span>High value customers</span>
                            </div>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Inventory Overview -->
                <div class="mb-4">
                    <h4 class="mb-3"><i class="fas fa-warehouse me-2"></i>Inventory Overview</h4>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value">${dashboard.summary?.inventory?.total_products || 0}</div>
                                    <div class="stat-label">Total Products</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(0, 245, 255, 0.1); color: var(--accent);">
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
                        </div>

                        ${!isStaff && dashboard.summary?.inventory?.total_inventory_value !== undefined ? `
                        <div class="stat-card">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value">₱${(dashboard.summary?.inventory?.total_inventory_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}</div>
                                    <div class="stat-label">Inventory Value</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: var(--success);">
                                    <i class="fas fa-peso-sign"></i>
                                </div>
                            </div>
                        </div>
                        ` : ''}

                        <div class="stat-card" onclick="showPage('products', true, 'low_stock')" style="cursor: pointer;">
                            <div class="stat-header">
                                <div>
                                    <div class="stat-value">${dashboard.summary?.inventory?.low_stock_items || 0}</div>
                                    <div class="stat-label">Low Stock Items</div>
                                </div>
                                <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: var(--warning);">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div class="stat-trend ${dashboard.summary?.inventory?.low_stock_items > 0 ? 'trend-down' : 'trend-up'}">
                                <i class="fas fa-${dashboard.summary?.inventory?.low_stock_items > 0 ? 'exclamation' : 'check'}-circle"></i>
                                <span>Click to view</span>
                            </div>
                        </div>


                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h5 class="chart-title">Sales Trend</h5>
                            </div>
                            <canvas id="sales-trend-chart" height="200"></canvas>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h5 class="chart-title">Top Products</h5>
                            </div>
                            <div class="list-group list-group-flush" style="background: transparent;">
                                ${(dashboard.top_performers?.products || []).slice(0, 5).map(product => `
                                    <div class="list-group-item" style="background: transparent; border-color: var(--border-color); color: var(--text-primary);">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>${product.name}</strong>
                                                <br><small class="text-muted">${product.total_sold} units sold</small>
                                            </div>
                                            <span class="badge badge-primary">₱${parseFloat(product.total_revenue).toLocaleString()}</span>
                                        </div>
                                    </div>
                                `).join('') || '<div class="text-center text-muted py-3">No sales data available</div>'}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    ${!isStaff && dashboard.recent_activity ? `
                    <div class="col-lg-6">
                        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush" style="background: transparent;">
                                    ${(dashboard.recent_activity || []).slice(0, 5).map(activity => `
                                        <div class="list-group-item" style="background: transparent; border-color: var(--border-color); color: var(--text-primary);">
                                            <div class="d-flex align-items-start">
                                                <div class="activity-icon me-3 mt-1">
                                                    <i class="${getActivityIcon(activity.action)} fa-lg" style="color: var(--accent);"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="activity-text" style="font-weight: 500;">
                                                        ${activity.description}
                                                    </div>
                                                    <div class="activity-meta mt-1">
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i>${new Date(activity.timestamp).toLocaleString()}
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('') || '<div class="text-center text-muted py-3">No recent activity</div>'}
                                </div>
                            </div>
                        </div>
                    </div>
                    ` : ''}

                    <div class="${isStaff ? 'col-lg-12' : 'col-lg-6'}">
                        <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color);">
                            <div class="card-header" style="background: var(--bg-tertiary); border-color: var(--border-color);">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>System Alerts</h5>
                            </div>
                            <div class="card-body">
                                ${(dashboard.alerts || []).length > 0 ? dashboard.alerts.map(alert => `
                                    <div class="alert alert-${alert.type === 'error' ? 'danger' : alert.type === 'warning' ? 'warning' : 'info'}">
                                        <i class="fas fa-${alert.type === 'error' ? 'times-circle' : alert.type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                                        <strong>${alert.title}:</strong> ${alert.message}
                                    </div>
                                `).join('') : `
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                        <p>No system alerts at this time.</p>
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Initialize sales trend chart
            setTimeout(() => initSalesTrendChart(dashboard.charts?.sales_over_time || []), 100);

            } catch (error) {
                console.error('Error loading dashboard:', error);
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading dashboard data. Please try refreshing the page.
                        <br><small class="text-muted mt-2">${error.message}</small>
                    </div>
                `;
            }
        }

        // Activity icon helper function
        function getActivityIcon(action) {
            const iconMap = {
                'login': 'fas fa-sign-in-alt',
                'logout': 'fas fa-sign-out-alt',
                'create_product': 'fas fa-plus-circle',
                'update_product': 'fas fa-edit',
                'delete_product': 'fas fa-trash-alt',
                'create_sale': 'fas fa-shopping-cart',
                'update_sale': 'fas fa-edit',
                'delete_sale': 'fas fa-trash-alt',
                'create_user': 'fas fa-user-plus',
                'update_user': 'fas fa-user-edit',
                'delete_user': 'fas fa-user-times',
                'create_supplier': 'fas fa-plus-circle',
                'update_supplier': 'fas fa-edit',
                'delete_supplier': 'fas fa-trash-alt',
                'create_po': 'fas fa-file-plus',
                'update_po': 'fas fa-file-edit',
                'approve_po': 'fas fa-check-circle',
                'reject_po': 'fas fa-times-circle',
                'create_grn': 'fas fa-receipt',
                'update_grn': 'fas fa-edit',
                'delete_grn': 'fas fa-trash-alt',
                'system_backup': 'fas fa-shield-alt',
                'system_restore': 'fas fa-undo',
                'settings_update': 'fas fa-cogs',
                'password_change': 'fas fa-key'
            };
            return iconMap[action] || 'fas fa-info-circle';
        }

        function initSalesTrendChart(salesData) {
            const ctx = document.getElementById('sales-trend-chart');
            if (!ctx || !salesData.length) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: salesData.map(item => new Date(item.date).toLocaleDateString()),
                    datasets: [{
                        label: 'Daily Sales (₱)',
                        data: salesData.map(item => parseFloat(item.revenue || 0)),
                        borderColor: 'rgb(0, 245, 255)',
                        backgroundColor: 'rgba(0, 245, 255, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            labels: { color: '#e2e8f0' }
                        }
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
    </script>

    <!-- Help System JavaScript -->
    <script>
        // Help System - Press '?' to open help for admin users
        document.addEventListener('keydown', (e) => {
            if (e.key === '?' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                // Only if not typing in an input field and user is admin
                if (!['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName) && currentUser && currentUser.role === 'admin') {
                    e.preventDefault();
                    showHelpModal();
                }
            }
        });

        function showHelpModal() {
            // Create help modal dynamically
            const helpModal = document.createElement('div');
            helpModal.className = 'modal fade';
            helpModal.innerHTML = `
                <div class="modal-dialog modal-xl">
                    <div class="modal-content" style="background: var(--bg-card); color: var(--text-primary);">
                        <div class="modal-header" style="border-color: var(--border-color);">
                            <h5 class="modal-title"><i class="fas fa-book me-2"></i>Administrator Manual</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Quick Help:</strong> Press <kbd>?</kbd> anytime to open this manual.
                            </div>
                            <div class="accordion" id="helpAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#dashboard">
                                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard Overview
                                        </button>
                                    </h2>
                                    <div id="dashboard" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <p>The dashboard provides real-time analytics including:</p>
                                            <ul>
                                                <li>Total sales and revenue</li>
                                                <li>Customer statistics</li>
                                                <li>Inventory levels and alerts</li>
                                                <li>Recent activity logs</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#products">
                                            <i class="fas fa-box me-2"></i>Product Management
                                        </button>
                                    </h2>
                                    <div id="products" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <p>Manage your product catalog:</p>
                                            <ul>
                                                <li>Add new products with images and specifications</li>
                                                <li>Update pricing and inventory levels</li>
                                                <li>Organize products by categories</li>
                                                <li>Track stock levels and reorder points</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#users">
                                            <i class="fas fa-users me-2"></i>User Management
                                        </button>
                                    </h2>
                                    <div id="users" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <p>System roles and permissions:</p>
                                            <ul>
                                                <li><strong>Admin:</strong> Full system access</li>
                                                <li><strong>Inventory Manager:</strong> Stock and inventory control</li>
                                                <li><strong>Purchasing Officer:</strong> Purchase order management</li>
                                                <li><strong>Staff:</strong> Sales and basic operations</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#shortcuts">
                                            <i class="fas fa-keyboard me-2"></i>Keyboard Shortcuts
                                        </button>
                                    </h2>
                                    <div id="shortcuts" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <ul>
                                                <li><kbd>Ctrl + K</kbd> - Quick search products</li>
                                                <li><kbd>Ctrl + N</kbd> - New sale</li>
                                                <li><kbd>Ctrl + P</kbd> - New purchase order</li>
                                                <li><kbd>Esc</kbd> - Close modals</li>
                                                <li><kbd>?</kbd> - Open this help (Admin only)</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(helpModal);
            const modal = new bootstrap.Modal(helpModal);
            modal.show();
            helpModal.addEventListener('hidden.bs.modal', () => helpModal.remove());
        }
    </script>
    <!-- Icon performance optimization -->
    <script src="assets/js/icon-lazy-loader.js"></script>
</body>
</html>
