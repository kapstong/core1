<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InvenCore - Employee Login</title>
    <link rel="icon" type="image/png" href="../ppc.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/main.css">

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
                        <div style="margin-top: 2rem; padding: 1.5rem; background: rgba(0, 245, 255, 0.05); border-radius: 12px; border-left: 3px solid var(--accent);">
                            <p style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.6; margin: 0;">
                                Secure employee access portal for PC Parts Central inventory operations, analytics, and management.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Login Form -->
                <div class="login-right">
                    <div class="login-header">
                        <h2>Welcome Back</h2>
                        <p>Sign in to access your dashboard</p>
                    </div>

                    <div id="alert-container"></div>

                    <form id="login-form">
                        <div class="form-group">
                            <label class="form-label">Username</label>
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
                                >
                                <i class="fas fa-user input-icon"></i>
                                <button type="button" class="clear-input" id="clear-username" title="Clear saved username">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <div class="input-wrapper" id="password-wrapper">
                                <input
                                    type="password"
                                    class="form-input"
                                    id="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password"
                                >
                                <i class="fas fa-lock input-icon"></i>
                                <button type="button" class="clear-input" id="clear-password" title="Clear saved password">
                                    <i class="fas fa-times"></i>
                                </button>
                                <button type="button" class="password-toggle" id="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="remember-forgot">
                            <div class="remember-me">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Remember me</label>
                            </div>
                            <a href="#" class="forgot-link" id="forgot-password-link">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn-login" id="login-btn">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Sign In
                        </button>

                        <div class="divider">
                            <span>Quick Access</span>
                        </div>

                        <div class="quick-links">
                            <a href="../index.php" class="quick-link">
                                <i class="fas fa-home me-1"></i> Home
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
        // Auto-detect base path: use /core1 for production, empty for local
        const BASE_PATH = IS_DEVELOPMENT ? '' : '/core1';
        const API_BASE = BASE_PATH + '/backend/api';

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
            const savedUsername = localStorage.getItem('invencore_username');
            const savedPassword = localStorage.getItem('invencore_password');

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
                localStorage.setItem('invencore_username', username);
                localStorage.setItem('invencore_password', password);
            } else {
                clearSavedCredentials();
            }
        }

        // Clear saved credentials
        function clearSavedCredentials() {
            localStorage.removeItem('invencore_username');
            localStorage.removeItem('invencore_password');
            usernameInput.value = '';
            passwordInput.value = '';
            rememberCheckbox.checked = false;
            clearUsernameBtn.classList.remove('show');
            clearPasswordBtn.classList.remove('show');
        }

        // Clear username
        clearUsernameBtn.addEventListener('click', function() {
            localStorage.removeItem('invencore_username');
            usernameInput.value = '';
            clearUsernameBtn.classList.remove('show');
            usernameInput.focus();
        });

        // Clear password
        clearPasswordBtn.addEventListener('click', function() {
            localStorage.removeItem('invencore_password');
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

            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Authenticating...';

            try {
                const response = await fetch(`${API_BASE}/auth/login.php`, {
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

                const data = await response.json();

                if (data.success) {
                    // Save credentials if Remember Me is checked
                    saveCredentials(username, password);

                    // Check if 2FA is required
                    if (data.data && data.data.requires_two_factor) {
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
                    showAlert(data.message || 'Invalid credentials. Please try again.');
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = originalContent;
                }
            } catch (error) {
                devLog('Login error:', error);
                if (error.message && error.message.includes('JSON')) {
                    showAlert('Server configuration error. Please contact administrator.');
                } else {
                    showAlert('Connection error. Please try again.');
                }
                loginBtn.disabled = false;
                loginBtn.innerHTML = originalContent;
            }
        });

        // Help link
        document.getElementById('help-link').addEventListener('click', function(e) {
            e.preventDefault();
            showAlert('<strong>Need Help?</strong> Contact your system administrator or IT support.', 'success');
        });

        // Forgot Password Modal
        document.getElementById('forgot-password-link').addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Forgot password link clicked'); // Debug log
            const modalElement = document.getElementById('forgotPasswordModal');
            console.log('Modal element:', modalElement); // Debug log
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement, {
                    backdrop: 'static',
                    keyboard: true
                });
                modal.show();
            } else {
                console.error('Forgot password modal not found!');
            }
        });

        // Wait for DOM to be fully loaded before initializing modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Enhanced Modal Functionality
            const forgotPasswordModal = document.getElementById('forgotPasswordModal');
            const forgotEmailInput = document.getElementById('forgotEmail');
            const forgotPasswordForm = document.getElementById('forgotPasswordForm');
            const forgotAlertContainer = document.getElementById('forgotAlertContainer');

            if (forgotPasswordModal && forgotEmailInput && forgotPasswordForm) {
                // Auto-focus email input when modal opens
                forgotPasswordModal.addEventListener('shown.bs.modal', function() {
                    forgotEmailInput.focus();
                });

                // Clear form and alerts when modal is closed
                forgotPasswordModal.addEventListener('hidden.bs.modal', function() {
                    forgotPasswordForm.reset();
                    forgotAlertContainer.innerHTML = '';
                });

                // Forgot Password Form Submission
                document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const email = document.getElementById('forgotEmail').value;
            const forgotBtn = document.getElementById('forgotBtn');
            const forgotBtnText = document.getElementById('forgotBtnText');
            const forgotSpinner = document.getElementById('forgotSpinner');
            const forgotAlertContainer = document.getElementById('forgotAlertContainer');

            // Clear previous alerts
            forgotAlertContainer.innerHTML = '';

            // Validate email format
            if (!email || !email.includes('@')) {
                forgotAlertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Please enter a valid email address
                    </div>
                `;
                document.getElementById('forgotEmail').focus();
                return;
            }

            // Disable button
            forgotBtn.disabled = true;
            forgotBtnText.style.display = 'none';
            forgotSpinner.style.display = 'inline-block';

            try {
                const response = await fetch(`${API_BASE}/auth/forgot-password.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Show success message with reset URL
                    let alertMessage = `<div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> ${data.message}
                    </div>`;

                    // Always show reset URL for testing
                    if (data.reset_url) {
                        alertMessage += `
                            <div class="alert alert-info" style="margin-top: 10px;">
                                <i class="fas fa-link"></i> <strong>Reset URL (Copy & Use):</strong><br>
                                <small style="word-break: break-all; font-family: monospace; background: rgba(0, 102, 204, 0.1); padding: 5px; border-radius: 3px; display: block; margin: 5px 0;">${data.reset_url}</small>
                            </div>
                        `;
                    }

                    // Show debug information if available
                    if (data.debug_info) {
                        alertMessage += `
                            <div class="alert alert-warning" style="margin-top: 10px; font-size: 0.85rem;">
                                <i class="fas fa-bug"></i> <strong>Email Debug Info:</strong><br>
                                <small>User: ${data.debug_info.user_type} (${data.debug_info.user_email})</small><br>
                                <small>SMTP: ${data.debug_info.email_settings.smtp_host} (Port: ${data.debug_info.email_settings.smtp_port})</small><br>
                                <small>Send Result: ${data.debug_info.email_send_result}</small><br>
                                ${data.debug_info.error ? `<small style="color: #dc3545;">Error: ${data.debug_info.error}</small><br>` : ''}
                            </div>
                        `;
                    }

                    forgotAlertContainer.innerHTML = alertMessage;

                    // Don't auto-close modal for debugging - let user see the info
                    return;

                } else {
                    // Show error message
                    forgotAlertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> ${data.message || 'Failed to send reset email. Please try again.'}
                        </div>
                    `;
                }
            } catch (error) {
                forgotAlertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> An error occurred. Please try again later.
                    </div>
                `;
                console.error('Forgot password error:', error);
            } finally {
                // Re-enable button
                forgotBtn.disabled = false;
                forgotBtnText.style.display = 'inline-block';
                forgotSpinner.style.display = 'none';
            }
        });
            } // End if check for modal elements
        }); // End DOMContentLoaded
    </script>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content"
                 style="background: linear-gradient(145deg, rgba(15, 23, 42, 0.98), rgba(26, 31, 58, 0.95));
                        backdrop-filter: blur(30px);
                        -webkit-backdrop-filter: blur(30px);
                        border: 1px solid rgba(0, 245, 255, 0.3);
                        border-radius: 20px;
                        color: var(--text-primary);
                        box-shadow: 0 25px 80px rgba(0, 0, 0, 0.6), 0 0 100px rgba(0, 245, 255, 0.15);">
                <div class="modal-header border-0 pb-0" style="position: relative;">
                    <div class="text-center w-100">
                        <div class="modal-icon mx-auto mb-3"
                             style="width: 70px; height: 70px;
                                    background: linear-gradient(135deg, var(--primary), var(--accent));
                                    border-radius: 18px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    box-shadow: 0 15px 40px rgba(0, 245, 255, 0.3);
                                    animation: pulse 3s ease-in-out infinite;">
                            <i class="fas fa-key text-white" style="font-size: 1.75rem;"></i>
                        </div>
                        <h5 class="modal-title" id="forgotPasswordModalLabel"
                            style="color: var(--text-primary);
                                   font-weight: 700;
                                   font-size: 1.5rem;
                                   margin-bottom: 0.5rem;">Reset Your Password</h5>
                        <p class="mb-0" style="color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5;">
                            Enter your email address and we'll send you a secure reset link
                        </p>
                    </div>
                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal"
                            aria-label="Close"
                            style="position: absolute;
                                   right: 1rem;
                                   top: 1rem;
                                   opacity: 0.7;
                                   transition: all 0.3s ease;"
                            onmouseover="this.style.opacity='1'; this.style.transform='rotate(90deg)'"
                            onmouseout="this.style.opacity='0.7'; this.style.transform='rotate(0deg)'"></button>
                </div>

                <div class="modal-body px-4 pt-4 pb-3">
                    <div id="forgotAlertContainer"></div>

                    <form id="forgotPasswordForm">
                        <div class="mb-4">
                            <label for="forgotEmail" class="form-label"
                                   style="color: var(--text-primary);
                                          font-weight: 600;
                                          font-size: 0.9rem;
                                          text-transform: uppercase;
                                          letter-spacing: 0.5px;
                                          margin-bottom: 0.75rem;">Email Address</label>
                            <div class="input-wrapper" style="position: relative;">
                                <input type="email"
                                       class="form-control forgot-email-input"
                                       id="forgotEmail"
                                       placeholder="Enter your registered email"
                                       required
                                       autocomplete="email"
                                       style="height: 56px;
                                              background: rgba(15, 23, 42, 0.9);
                                              border: 2px solid rgba(0, 245, 255, 0.15);
                                              border-radius: 12px;
                                              color: var(--text-primary);
                                              padding: 0 1.5rem 0 3.5rem;
                                              font-size: 1rem;
                                              transition: all 0.3s ease;
                                              width: 100%;
                                              outline: none;">
                                <i class="fas fa-envelope"
                                   style="position: absolute;
                                          left: 1.25rem;
                                          top: 50%;
                                          transform: translateY(-50%);
                                          color: var(--text-muted);
                                          font-size: 1.1rem;
                                          transition: all 0.3s ease;"></i>
                            </div>
                        </div>

                        <button type="submit"
                                class="btn w-100 forgot-submit-btn"
                                id="forgotBtn"
                                style="height: 56px;
                                       background: linear-gradient(135deg, var(--primary), var(--accent));
                                       border: none;
                                       border-radius: 12px;
                                       color: white;
                                       font-weight: 700;
                                       text-transform: uppercase;
                                       letter-spacing: 1.5px;
                                       box-shadow: 0 10px 30px rgba(0, 245, 255, 0.3);
                                       transition: all 0.3s ease;
                                       position: relative;
                                       overflow: hidden;
                                       width: 100%;
                                       display: flex;
                                       align-items: center;
                                       justify-content: center;
                                       gap: 0.5rem;">
                            <span id="forgotBtnText">
                                <i class="fas fa-paper-plane"></i>Send Reset Link
                            </span>
                            <i class="fas fa-spinner fa-spin" id="forgotSpinner" style="display: none;"></i>
                        </button>
                    </form>
                </div>

                <div class="modal-footer border-0 justify-content-center pt-2 pb-4">
                    <p class="mb-0" style="color: var(--text-secondary); font-size: 0.9rem;">
                        <i class="fas fa-lock me-1" style="font-size: 0.8rem;"></i>
                        Remember your password?
                        <a href="#"
                           class="form-link"
                           data-bs-dismiss="modal"
                           style="font-weight: 600;">Back to Login</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Overlay Adjustments -->
    <style>
        .modal-backdrop {
            z-index: 1040 !important;
            background-color: rgba(0, 0, 0, 0.75) !important;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }

        .modal {
            z-index: 1050 !important;
        }

        /* Enhanced Modal Styles */
        .forgot-email-input:focus {
            border-color: var(--accent) !important;
            background: rgba(15, 23, 42, 1) !important;
            box-shadow: 0 0 0 4px rgba(0, 245, 255, 0.1) !important;
            outline: none !important;
        }

        .forgot-email-input:focus + i {
            color: var(--accent) !important;
        }

        .forgot-submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .forgot-submit-btn:hover::before {
            left: 100%;
        }

        .forgot-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(0, 245, 255, 0.5) !important;
        }

        .forgot-submit-btn:active {
            transform: scale(0.98);
        }

        .forgot-submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }
    </style>
</body>
</html>
