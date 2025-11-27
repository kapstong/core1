<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InvenCore - Supplier Login</title>
    <link rel="icon" type="image/png" href="../ppc.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../public/assets/css/main.css">

    <style>
        /* CSS Variables for consistent colors */
        :root {
            --primary: #0066ff;
            --accent: #00f5ff;
            --bg-primary: #0a0e27;
            --bg-secondary: #1a1f3a;
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --border-color: rgba(148, 163, 184, 0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        /* Animated Background */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            position: relative;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 50%, #0f1629 100%);
        }

        .login-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(0, 102, 255, 0.15) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(0, 245, 255, 0.15) 0%, transparent 50%);
            animation: bgPulse 8s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes bgPulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }

        /* Floating particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .particle {
            position: absolute;
            width: 3px;
            height: 3px;
            background: rgba(0, 245, 255, 0.5);
            border-radius: 50%;
            animation: float 15s infinite ease-in-out;
        }

        @keyframes float {
            0%, 100% {
                transform: translate(0, 0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translate(100vw, -100vh);
                opacity: 0;
            }
        }

        /* Main Container */
        .login-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 2;
        }

        .login-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
            max-width: 1100px;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 245, 255, 0.1);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4),
                        0 0 100px rgba(0, 245, 255, 0.1);
            animation: slideUp 0.8s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Left Side - Branding */
        .login-left {
            background: linear-gradient(135deg, rgba(0, 102, 255, 0.08), rgba(0, 245, 255, 0.08));
            padding: 4rem 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(0, 245, 255, 0.05) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .brand-header {
            position: relative;
            z-index: 1;
        }

        .brand-logo {
            width: 100px;
            height: 100px;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            box-shadow: 0 15px 40px rgba(0, 245, 255, 0.3);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 15px 40px rgba(0, 245, 255, 0.3);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 20px 50px rgba(0, 245, 255, 0.5);
            }
        }

        .brand-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .brand-subtitle {
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 3rem;
            font-weight: 300;
        }

        .feature-list {
            list-style: none;
            position: relative;
            z-index: 1;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            margin-bottom: 1.75rem;
            padding: 1rem;
            background: rgba(0, 245, 255, 0.03);
            border-radius: 12px;
            border-left: 3px solid var(--accent);
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            background: rgba(0, 245, 255, 0.08);
            transform: translateX(5px);
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            min-width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(0, 102, 255, 0.2), rgba(0, 245, 255, 0.2));
            border-radius: 12px;
            color: var(--accent);
            font-size: 1.4rem;
        }

        .feature-content h4 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .feature-content p {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin: 0;
        }

        /* Right Side - Login Form */
        .login-right {
            padding: 4rem 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(10, 14, 39, 0.4);
        }

        .login-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .login-header h2 {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }

        .login-header p {
            color: var(--text-muted);
            font-size: 1rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.75rem;
        }

        .form-label {
            display: block;
            color: var(--text-primary);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .form-input {
            width: 100%;
            height: 58px;
            background: rgba(15, 23, 42, 0.9);
            border: 2px solid rgba(0, 245, 255, 0.1);
            border-radius: 12px;
            padding: 0 1.5rem 0 3.5rem;
            font-size: 1rem;
            color: var(--text-primary);
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--accent);
            background: rgba(15, 23, 42, 1);
            box-shadow: 0 0 0 4px rgba(0, 245, 255, 0.1);
        }

        .form-input:focus + .input-icon {
            color: var(--accent);
        }

        .password-toggle {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.1rem;
            cursor: pointer;
            padding: 0.5rem;
            transition: all 0.3s ease;
            z-index: 2;
        }

        .password-toggle:hover {
            color: var(--accent);
        }

        .clear-input {
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(239, 68, 68, 0.1);
            border: none;
            color: #ef4444;
            font-size: 0.9rem;
            cursor: pointer;
            padding: 0.4rem 0.6rem;
            border-radius: 6px;
            transition: all 0.3s ease;
            z-index: 2;
            display: none;
        }

        .clear-input:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }

        .clear-input.show {
            display: block;
        }

        #password-wrapper .password-toggle {
            right: 3.5rem;
        }

        /* Error message styling */
        .error-message {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-weight: 500;
            display: none;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .error-message.show {
            display: block;
            opacity: 1;
        }

        .error-message.error {
            color: #ef4444;
        }

        .error-message.success {
            color: #10b981;
        }

        /* Form validation states */
        .form-input.error {
            border-color: #ef4444 !important;
            background: rgba(239, 68, 68, 0.05) !important;
        }

        .form-input.success {
            border-color: #10b981 !important;
            background: rgba(16, 185, 129, 0.05) !important;
        }

        .form-input:focus.error {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1) !important;
        }

        .form-input:focus.success {
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1) !important;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .remember-me input {
            width: 18px;
            height: 18px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .remember-me label {
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
            font-size: 0.9rem;
        }

        .forgot-link {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            height: 58px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 245, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(0, 245, 255, 0.5);
        }

        .btn-login:active {
            transform: scale(0.98);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .divider {
            text-align: center;
            margin: 2rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: rgba(0, 245, 255, 0.1);
        }

        .divider span {
            position: relative;
            background: rgba(10, 14, 39, 0.4);
            padding: 0 1rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .quick-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .quick-link {
            flex: 1;
            padding: 0.75rem;
            background: rgba(0, 245, 255, 0.05);
            border: 1px solid rgba(0, 245, 255, 0.1);
            border-radius: 8px;
            text-align: center;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .quick-link:hover {
            background: rgba(0, 245, 255, 0.1);
            border-color: var(--accent);
            color: var(--accent);
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: #10b981;
            color: #10b981;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #ef4444;
        }

        .alert i {
            margin-right: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .login-box {
                grid-template-columns: 1fr;
            }

            .login-left {
                padding: 3rem 2rem;
                min-height: auto;
            }

            .brand-logo {
                width: 80px;
                height: 80px;
                font-size: 2.5rem;
            }

            .brand-title {
                font-size: 2rem;
            }

            .feature-list {
                display: none;
            }

            .login-right {
                padding: 3rem 2rem;
            }

            .login-header h2 {
                font-size: 1.75rem;
            }
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 1rem;
            }

            .login-right {
                padding: 2rem 1.5rem;
            }

            .form-input {
                height: 52px;
                font-size: 0.95rem;
            }

            .btn-login {
                height: 52px;
                font-size: 0.95rem;
            }

            .quick-links {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="login-bg"></div>
    <div class="particles" id="particles"></div>

    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-box">
                <!-- Left Side - Branding -->
                <div class="login-left">
                    <div class="brand-header">
                        <div class="brand-logo">
                            <i class="fas fa-microchip"></i>
                        </div>
                        <h1 class="brand-title">InvenCore</h1>
                        <p class="brand-subtitle">Advanced Inventory Management System</p>

                        <ul class="feature-list">
                            <li class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="feature-content">
                                    <h4>Order Management</h4>
                                    <p>Track and fulfill purchase orders efficiently</p>
                                </div>
                            </li>
                            <li class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div class="feature-content">
                                    <h4>Supply Chain</h4>
                                    <p>Integrated supplier network and logistics</p>
                                </div>
                            </li>
                            <li class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="feature-content">
                                    <h4>Performance</h4>
                                    <p>Analytics and performance metrics dashboard</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Right Side - Login Form -->
                <div class="login-right">
                    <div class="login-header">
                        <h2>Supplier Login</h2>
                        <p>Sign in to access your supplier dashboard</p>
                    </div>

                    <div id="alert-container"></div>

                    <form id="login-form">
                        <div class="form-group">
                            <label class="form-label" for="username">Username</label>
                            <div class="input-wrapper" id="username-wrapper">
                                <input
                                    type="text"
                                    class="form-input"
                                    id="username"
                                    name="username"
                                    placeholder="Enter your username"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    aria-describedby="username-error"
                                    minlength="3"
                                    maxlength="50"
                                >
                                <i class="fas fa-user input-icon"></i>
                                <button type="button" class="clear-input" id="clear-username" title="Clear saved username" aria-label="Clear username">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="username-error" class="error-message" role="alert" aria-live="polite"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-wrapper" id="password-wrapper">
                                <input
                                    type="password"
                                    class="form-input"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password"
                                    aria-describedby="password-error"
                                    minlength="6"
                                >
                                <i class="fas fa-lock input-icon"></i>
                                <button type="button" class="clear-input" id="clear-password" title="Clear saved password" aria-label="Clear password">
                                    <i class="fas fa-times"></i>
                                </button>
                                <button type="button" class="password-toggle" id="toggle-password" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="password-error" class="error-message" role="alert" aria-live="polite"></div>
                        </div>

                        <div class="remember-forgot">
                            <div class="remember-me">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Remember me</label>
                            </div>
                            <a href="#" class="forgot-link" id="forgot-link">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn-login" id="login-btn">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Sign In
                        </button>

                        <div class="text-center" style="margin-top: 2rem; padding: 1.5rem; background: rgba(0, 245, 255, 0.05); border-radius: 12px; border-left: 3px solid var(--accent);">
                            <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 1rem; line-height: 1.6;">
                                Want to become a Supplier? Join our partner network to supply premium PC components.
                            </p>
                            <a href="signup.php" class="btn btn-outline-custom" style="padding: 0.75rem 2rem; font-size: 0.9rem;">
                                <i class="fas fa-user-plus me-2"></i>
                                Become a Supplier
                            </a>
                        </div>

                        <div class="divider">
                            <span>Quick Access</span>
                        </div>

                        <div class="quick-links">
                            <a href="signup.php" class="quick-link">
                                <i class="fas fa-user-plus me-1"></i> Sign Up
                            </a>
                            <a href="#" class="quick-link" id="help-link">
                                <i class="fas fa-question-circle me-1"></i> Help
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const IS_DEVELOPMENT = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        // Auto-detect base path: /core1 for local dev, empty for production
        const BASE_PATH = IS_DEVELOPMENT ? '/core1' : '';
        const API_BASE = BASE_PATH + '/backend/api';

        // Silent logging for production
        function devLog(message, data = null) {
            // No console output in production
        }

        // Create floating particles
        function createParticles() {
            const container = document.getElementById('particles');
            const particleCount = 20;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 15 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
                container.appendChild(particle);
            }
        }

        createParticles();

        // Remember Me functionality
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const rememberCheckbox = document.getElementById('remember');
        const clearUsernameBtn = document.getElementById('clear-username');
        const clearPasswordBtn = document.getElementById('clear-password');

        // Load saved credentials on page load
        function loadSavedCredentials() {
            const savedUsername = localStorage.getItem('supplier_username');
            const savedPassword = localStorage.getItem('supplier_password');

            if (savedUsername) {
                usernameInput.value = savedUsername;
                clearUsernameBtn.classList.add('show');
                rememberCheckbox.checked = true;
            }

            if (savedPassword) {
                passwordInput.value = savedPassword;
                clearPasswordBtn.classList.add('show');
            }
        }

        // Save credentials to localStorage
        function saveCredentials(username, password) {
            if (rememberCheckbox.checked) {
                localStorage.setItem('supplier_username', username);
                localStorage.setItem('supplier_password', password);
            } else {
                clearSavedCredentials();
            }
        }

        // Clear saved credentials
        function clearSavedCredentials() {
            localStorage.removeItem('supplier_username');
            localStorage.removeItem('supplier_password');
            usernameInput.value = '';
            passwordInput.value = '';
            rememberCheckbox.checked = false;
            clearUsernameBtn.classList.remove('show');
            clearPasswordBtn.classList.remove('show');
        }

        // Clear username
        clearUsernameBtn.addEventListener('click', function() {
            localStorage.removeItem('supplier_username');
            usernameInput.value = '';
            clearUsernameBtn.classList.remove('show');
            usernameInput.focus();
        });

        // Clear password
        clearPasswordBtn.addEventListener('click', function() {
            localStorage.removeItem('supplier_password');
            passwordInput.value = '';
            clearPasswordBtn.classList.remove('show');
            passwordInput.focus();
        });

        // Show/hide clear buttons on input
        usernameInput.addEventListener('input', function() {
            if (this.value) {
                clearUsernameBtn.classList.add('show');
            } else {
                clearUsernameBtn.classList.remove('show');
            }
        });

        passwordInput.addEventListener('input', function() {
            if (this.value) {
                clearPasswordBtn.classList.add('show');
            } else {
                clearPasswordBtn.classList.remove('show');
            }
        });

        // Load saved credentials when page loads
        loadSavedCredentials();

        // Form validation - Only basic required field validation for login
        function showError(input, message, errorId) {
            const errorElement = document.getElementById(errorId);
            errorElement.textContent = message;
            errorElement.className = 'error-message error show';
            input.classList.add('error');
            input.classList.remove('success');
        }

        function showSuccess(errorId) {
            const errorElement = document.getElementById(errorId);
            errorElement.className = 'error-message success';
            errorElement.textContent = '';
        }

        function clearValidation(input, errorId) {
            const errorElement = document.getElementById(errorId);
            errorElement.className = 'error-message';
            errorElement.textContent = '';
            input.classList.remove('error', 'success');
        }

        function validateUsername() {
            const username = usernameInput.value.trim();

            if (username === '') {
                showError(usernameInput, 'Username is required', 'username-error');
                return false;
            }

            if (username.length < 3) {
                showError(usernameInput, 'Username must be at least 3 characters', 'username-error');
                return false;
            }

            if (username.length > 50) {
                showError(usernameInput, 'Username must be less than 50 characters', 'username-error');
                return false;
            }

            // Basic alphanumeric check with underscores and hyphens
            const usernameRegex = /^[a-zA-Z0-9_-]+$/;
            if (!usernameRegex.test(username)) {
                showError(usernameInput, 'Username can only contain letters, numbers, underscores, and hyphens', 'username-error');
                return false;
            }

            showSuccess('username-error');
            usernameInput.classList.add('success');
            usernameInput.classList.remove('error');
            return true;
        }

        function validatePassword() {
            const password = passwordInput.value;

            if (password === '') {
                showError(passwordInput, 'Password is required', 'password-error');
                return false;
            }

            showSuccess('password-error');
            passwordInput.classList.add('success');
            passwordInput.classList.remove('error');
            return true;
        }

        // Real-time validation
        usernameInput.addEventListener('blur', validateUsername);
        usernameInput.addEventListener('input', function() {
            if (usernameInput.classList.contains('error')) {
                validateUsername();
            }
        });

        passwordInput.addEventListener('blur', validatePassword);
        passwordInput.addEventListener('input', function() {
            if (passwordInput.classList.contains('error')) {
                validatePassword();
            }
        });

        // Password toggle
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        function showAlert(message, type = 'danger') {
            const container = document.getElementById('alert-container');
            container.innerHTML = `
                <div class="alert alert-${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                    <span>${message}</span>
                </div>
            `;

            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    alert.style.opacity = '0';
                    setTimeout(() => container.innerHTML = '', 300);
                }
            }, type === 'success' ? 8000 : 6000);
        }

        document.getElementById('login-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const loginBtn = document.getElementById('login-btn');
            const originalContent = loginBtn.innerHTML;

            devLog('Login attempt started', { username, apiBase: API_BASE });

            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';

            try {
                const apiUrl = `${API_BASE}/auth/login.php`;
                devLog('Calling API:', apiUrl);

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        username,
                        password
                    })
                });

                devLog('Response status:', response.status);
                devLog('Response headers:', Object.fromEntries(response.headers.entries()));

                const responseText = await response.text();
                devLog('Raw response:', responseText);

                let data;
                try {
                    data = JSON.parse(responseText);
                    devLog('Parsed response:', data);
                } catch (parseError) {
                    devLog('JSON parse error:', parseError);
                    throw new Error('Invalid JSON response from server');
                }

                if (data.success) {
                    // Save credentials if Remember Me is checked
                    saveCredentials(username, password);

                    // Check if user is a supplier
                    if (data.user && data.user.role !== 'supplier') {
                        showAlert('Access denied. This portal is only for suppliers.');
                        loginBtn.disabled = false;
                        loginBtn.innerHTML = originalContent;
                        return;
                    }

                    // Check if 2FA is required
                    if (data.requires_two_factor) {
                        showAlert('<strong>Verification Required!</strong> Please check your email for the verification code.', 'success');
                        loginBtn.innerHTML = '<i class="fas fa-shield-alt me-2"></i>Redirecting to verification...';

                        setTimeout(() => {
                            window.location.href = 'verify-2fa.php';
                        }, 2000);
                    } else {
                        // Normal login without 2FA
                        showAlert('<strong>Success!</strong> Redirecting to dashboard...', 'success');
                        loginBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Login Successful!';

                        setTimeout(() => {
                            window.location.href = 'dashboard.php';
                        }, 1000);
                    }
                } else {
                    devLog('Login failed:', data);
                    showAlert(data.message || 'Invalid credentials. Please try again.');
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = originalContent;
                }
            } catch (error) {
                devLog('Login error:', error);
                devLog('Error stack:', error.stack);
                if (error.message && error.message.includes('JSON')) {
                    showAlert('Server configuration error. Please contact administrator. Check console for details.');
                } else if (error.message && error.message.includes('fetch')) {
                    showAlert('Cannot connect to server. Check console for details.');
                } else {
                    showAlert('Connection error: ' + error.message + '. Check console for details.');
                }
                loginBtn.disabled = false;
                loginBtn.innerHTML = originalContent;
            }
        });

        // Forgot password functionality
        document.getElementById('forgot-link').addEventListener('click', function(e) {
            e.preventDefault();
            const email = prompt('Enter your email address:');
            if (email && email.trim()) {
                handleForgotPassword(email.trim());
            }
        });

        async function handleForgotPassword(email) {
            try {
                const response = await fetch(`${API_BASE}/auth/forgot-password.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, type: 'supplier' })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('<strong>Password Reset Sent!</strong> If an account with that email exists, a password reset link has been sent.', 'success');
                } else {
                    showAlert(data.message || 'Failed to send reset email.', 'danger');
                }
            } catch (error) {
                devLog('Forgot password error:', error);
                showAlert('Failed to send reset email. Please try again later.', 'danger');
            }
        }

        // Help link
        document.getElementById('help-link').addEventListener('click', function(e) {
            e.preventDefault();
            showAlert('<strong>Need Help?</strong> Contact your supplier account manager or support team.', 'success');
        });
    </script>
</body>
</html>
