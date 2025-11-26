<?php
// Start session to check for staff authentication
session_start();

// Include maintenance mode check
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/middleware/MaintenanceMode.php';

// Check if maintenance mode is enabled and user is not admin
if (MaintenanceMode::handle()) {
    MaintenanceMode::renderMaintenancePage();
}

// Redirect already logged-in users
// If customer is already logged in, redirect to shop
if (isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit;
}

// If staff member is already logged in, redirect to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Login - PC Parts Central</title>
    <link rel="icon" type="image/png" href="ppc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Animated Background */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            position: relative;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 50%, #0f1629 100%);
            overflow: hidden;
        }

        .login-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(circle at 20% 30%, rgba(0, 102, 255, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(0, 245, 255, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 60% 10%, rgba(0, 102, 255, 0.08) 0%, transparent 30%),
                radial-gradient(circle at 10% 90%, rgba(0, 245, 255, 0.08) 0%, transparent 30%);
            animation: bgPulse 10s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes bgPulse {
            0%, 100% {
                opacity: 0.7;
                transform: scale(1);
            }
            33% {
                opacity: 1;
                transform: scale(1.05);
            }
            66% {
                opacity: 0.8;
                transform: scale(0.98);
            }
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        /* Modal Overlay Adjustments */
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

        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: rgba(0, 245, 255, 0.6);
            border-radius: 50%;
            animation: float 20s infinite linear;
            box-shadow: 0 0 6px rgba(0, 245, 255, 0.4);
        }

        .particle:nth-child(odd) {
            animation-duration: 25s;
            background: rgba(0, 102, 255, 0.5);
        }

        .particle:nth-child(3n) {
            width: 1px;
            height: 1px;
            animation-duration: 30s;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translate(120vw, -120vh) rotate(360deg);
                opacity: 0;
            }
        }

        /* Main Container */
        .login-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 2;
            gap: 4rem;
        }

        /* Split Layout */
        .login-content {
            flex: 1;
            max-width: 600px;
            text-align: center;
        }

        .login-form-section {
            flex: 1;
            max-width: 800px;
        }

        .login-card {
            width: 100%;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.8), rgba(26, 31, 58, 0.6));
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid rgba(0, 245, 255, 0.15);
            border-radius: 28px;
            padding: 4.5rem 5rem;
            box-shadow:
                0 32px 100px rgba(0, 0, 0, 0.5),
                0 0 150px rgba(0, 245, 255, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            animation: slideUpFade 0.9s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0, 245, 255, 0.3), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0%, 100% { opacity: 0; }
            50% { opacity: 1; }
        }

        @keyframes slideUpFade {
            from {
                opacity: 0;
                transform: translateY(60px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Logo */
        .brand-logo {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
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

        .logo-icon i {
            font-size: 2.75rem;
            color: white;
        }

        .brand-title {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .brand-subtitle {
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: 300;
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
            z-index: 1;
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

        .form-input:focus ~ .input-icon {
            color: var(--accent);
        }

        .form-input::placeholder {
            color: var(--text-muted);
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

        .form-link {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-link:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        /* Button */
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

        /* Divider */
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
            background: rgba(15, 23, 42, 0.9);
            padding: 0 1rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Register Link */
        .register-link {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0, 245, 255, 0.1);
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .register-link a {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .register-link a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        /* Alert */
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

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: #ef4444;
            color: #ef4444;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: #10b981;
            color: #10b981;
        }

        .alert i {
            margin-right: 0.5rem;
        }

        /* Back Link */
        .back-link {
            position: absolute;
            top: 2rem;
            left: 2rem;
            color: var(--text-secondary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            z-index: 3;
            background: rgba(15, 23, 42, 0.5);
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 245, 255, 0.1);
        }

        .back-link:hover {
            color: var(--accent);
            transform: translateX(-5px);
            background: rgba(15, 23, 42, 0.8);
            border-color: var(--accent);
        }

        /* Welcome Content Section */
        .welcome-section {
            animation: slideUpFade 0.9s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .welcome-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            box-shadow: 0 15px 40px rgba(0, 245, 255, 0.3);
            animation: pulse 3s ease-in-out infinite;
        }

        .welcome-icon i {
            font-size: 2.25rem;
            color: white;
        }

        .welcome-title {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--text-primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-description {
            font-size: 1.1rem;
            line-height: 1.6;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .features-list {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.5);
            border: 1px solid rgba(0, 245, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            border-color: var(--accent);
            background: rgba(15, 23, 42, 0.8);
            transform: translateY(-2px);
        }

        .feature-item i {
            font-size: 1.25rem;
            color: var(--accent);
        }

        .feature-item span {
            font-size: 0.9rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .stats-section {
            display: flex;
            justify-content: space-around;
            gap: 1rem;
        }

        .stat {
            text-align: center;
            flex: 1;
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--accent);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                gap: 2rem;
                padding: 1.5rem;
            }

            .login-content {
                max-width: 100%;
            }

            .welcome-title {
                font-size: 2rem;
            }

            .welcome-description {
                font-size: 1rem;
            }

            .features-list {
                grid-template-columns: 1fr;
            }

            .stats-section {
                flex-direction: column;
                gap: 1.5rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .login-container {
                padding: 1rem;
            }

            .login-card {
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

            .back-link {
                top: 1rem;
                left: 1rem;
                font-size: 0.85rem;
                padding: 0.6rem 1rem;
            }

            .welcome-title {
                font-size: 1.75rem;
            }

            .stat-number {
                font-size: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-bg"></div>
    <div class="particles" id="particles"></div>

    <a href="../index.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        <span>Back to Home</span>
    </a>

    <div class="login-wrapper">
        <div class="login-container">
            <!-- Welcome Content Section -->
            <div class="login-content">
                <div class="welcome-section">
                    <div class="welcome-icon">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <h2 class="welcome-title">Build Your Dream PC</h2>
                    <p class="welcome-description">
                        Access our extensive collection of premium PC components, from high-performance GPUs to reliable power supplies.
                        Build, upgrade, or repair your gaming rig with confidence.
                    </p>

                    <div class="features-list">
                        <div class="feature-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Secure Shopping</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-truck"></i>
                            <span>Fast Delivery</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-headset"></i>
                            <span>Expert Support</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-star"></i>
                            <span>Quality Guarantee</span>
                        </div>
                    </div>

                    <div class="stats-section">
                        <div class="stat">
                            <div class="stat-number">10,000+</div>
                            <div class="stat-label">Products</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">50,000+</div>
                            <div class="stat-label">Happy Customers</div>
                        </div>
                        <div class="stat">
                            <div class="stat-number">99%</div>
                            <div class="stat-label">Satisfaction</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Login Form Section -->
            <div class="login-form-section">
                <div class="login-card">
                    <div class="brand-logo">
                        <div class="logo-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h1 class="brand-title">Welcome Back</h1>
                        <p class="brand-subtitle">Sign in to your PC Parts Central account</p>
                    </div>

                    <div id="alert-container"></div>

                    <form id="loginForm">
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <div class="input-wrapper" id="username-wrapper">
                                <input
                                    type="email"
                                    class="form-input"
                                    id="username"
                                    name="username"
                                    placeholder="Enter your email address"
                                    required
                                    autofocus
                                    autocomplete="email"
                                >
                                <i class="fas fa-envelope input-icon"></i>
                                <button type="button" class="clear-input" id="clear-username" title="Clear saved email">
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
                                <button type="button" class="password-toggle" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="remember-forgot">
                            <div class="remember-me">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Remember me</label>
                            </div>
                            <a href="#" class="form-link" id="forgotPassword">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn-login" id="loginBtn">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            <span id="loginBtnText">Sign In</span>
                            <i class="fas fa-spinner fa-spin" id="loginSpinner" style="display: none;"></i>
                        </button>
                    </form>

                    <div class="divider">
                        <span>New to PC Parts Central?</span>
                    </div>

                    <div class="register-link">
                        Don't have an account? <a href="register.php">Create Account</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Forgot Password Modal - Moved outside login-container to fix z-index issue -->
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
                                              transition: all 0.3s ease;">
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
                                       overflow: hidden;">
                            <span id="forgotBtnText">
                                <i class="fas fa-paper-plane me-2"></i>Send Reset Link
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

    <script>
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
            const savedUsername = localStorage.getItem('ppc_customer_username');
            const savedPassword = localStorage.getItem('ppc_customer_password');

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
                localStorage.setItem('ppc_customer_username', username);
                localStorage.setItem('ppc_customer_password', password);
            } else {
                clearSavedCredentials();
            }
        }

        // Clear saved credentials
        function clearSavedCredentials() {
            localStorage.removeItem('ppc_customer_username');
            localStorage.removeItem('ppc_customer_password');
            usernameInput.value = '';
            passwordInput.value = '';
            rememberCheckbox.checked = false;
            clearUsernameBtn.classList.remove('show');
            clearPasswordBtn.classList.remove('show');
        }

        // Clear username
        clearUsernameBtn.addEventListener('click', function() {
            localStorage.removeItem('ppc_customer_username');
            usernameInput.value = '';
            clearUsernameBtn.classList.remove('show');
            usernameInput.focus();
        });

        // Clear password
        clearPasswordBtn.addEventListener('click', function() {
            localStorage.removeItem('ppc_customer_password');
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

        // Password Toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
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

        // Form Submission
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const loginBtn = document.getElementById('loginBtn');
            const loginBtnText = document.getElementById('loginBtnText');
            const loginSpinner = document.getElementById('loginSpinner');
            const alertContainer = document.getElementById('alert-container');

            // Clear previous alerts
            alertContainer.innerHTML = '';

            // Disable button
            loginBtn.disabled = true;
            loginBtnText.style.display = 'none';
            loginSpinner.style.display = 'inline-block';

            try {
                const response = await fetch('../backend/api/shop/auth.php?action=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: username,
                        password: password
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Save credentials if Remember Me is checked
                    saveCredentials(username, password);

                    // Show success message
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> ${data.message}
                        </div>
                    `;

                    // Redirect to shop or dashboard
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1000);
                } else {
                    // Show error message
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> ${data.message || 'Login failed. Please try again.'}
                        </div>
                    `;
                }
            } catch (error) {
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> An error occurred. Please try again later.
                    </div>
                `;
            } finally {
                // Re-enable button
                loginBtn.disabled = false;
                loginBtnText.style.display = 'inline-block';
                loginSpinner.style.display = 'none';
            }
        });

        // Forgot Password Modal
        document.getElementById('forgotPassword').addEventListener('click', function(e) {
            e.preventDefault();
            const modal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
            modal.show();
        });

        // Enhanced Modal Functionality
        const forgotPasswordModal = document.getElementById('forgotPasswordModal');
        const forgotEmailInput = document.getElementById('forgotEmail');
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        const forgotAlertContainer = document.getElementById('forgotAlertContainer');

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
                const response = await fetch('../backend/api/shop/auth.php?action=forgot_password', {
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
                    // Show success message
                    forgotAlertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> ${data.message}
                        </div>
                    `;

                    // Clear form and close modal after delay
                    setTimeout(() => {
                        document.getElementById('forgotPasswordForm').reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
                        modal.hide();
                    }, 3000);

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
    </script>
</body>
</html>
