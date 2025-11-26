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
    <title>Create Account - PC Parts Central</title>
    <link rel="icon" type="image/png" href="../ppc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .register-wrapper {
            min-height: 100vh;
            display: flex;
            position: relative;
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 50%, #0f1629 100%);
            overflow: hidden;
            padding: 3rem 0;
        }

        .register-bg {
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
        .register-container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            z-index: 2;
        }

        .register-card {
            width: 100%;
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.85), rgba(26, 31, 58, 0.7));
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

        .register-card::before {
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
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            color: var(--text-primary);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 0.6rem;
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
            font-size: 1rem;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .form-input {
            width: 100%;
            height: 52px;
            background: rgba(15, 23, 42, 0.9);
            border: 2px solid rgba(0, 245, 255, 0.1);
            border-radius: 12px;
            padding: 0 1.5rem 0 3.25rem;
            font-size: 0.95rem;
            color: var(--text-primary);
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input.no-icon {
            padding: 0 1.5rem;
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

        /* Button */
        .btn-register {
            width: 100%;
            height: 56px;
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
            margin-top: 1rem;
        }

        .btn-register::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .btn-register:hover::before {
            left: 100%;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(0, 245, 255, 0.5);
        }

        .btn-register:active {
            transform: scale(0.98);
        }

        .btn-register:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Login Link */
        .login-link {
            text-align: center;
            padding-top: 1.5rem;
            margin-top: 1.5rem;
            border-top: 1px solid rgba(0, 245, 255, 0.1);
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .login-link a {
            color: var(--accent);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-left: 0.25rem;
        }

        .login-link a:hover {
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

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .register-container {
                padding: 1rem;
            }

            .register-card {
                padding: 2rem 1.5rem;
            }

            .form-input {
                height: 50px;
                font-size: 0.95rem;
            }

            .btn-register {
                height: 50px;
                font-size: 0.95rem;
            }

            .back-link {
                top: 1rem;
                left: 1rem;
                font-size: 0.85rem;
                padding: 0.6rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-bg"></div>
    <div class="particles" id="particles"></div>

    <a href="../index.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        <span>Back to Home</span>
    </a>

    <div class="register-wrapper">
        <div class="register-container">
            <div class="register-card">
                <div class="brand-logo">
                    <div class="logo-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h1 class="brand-title">Join Us Today</h1>
                    <p class="brand-subtitle">Create your PC Parts Central account</p>
                </div>

                <div id="alert-container"></div>

                <form id="registerForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <div class="input-wrapper">
                                <input
                                    type="text"
                                    class="form-input no-icon"
                                    id="first_name"
                                    name="first_name"
                                    placeholder="First name"
                                    required
                                    autofocus
                                >
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <div class="input-wrapper">
                                <input
                                    type="text"
                                    class="form-input no-icon"
                                    id="last_name"
                                    name="last_name"
                                    placeholder="Last name"
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <div class="input-wrapper">
                            <input
                                type="email"
                                class="form-input"
                                id="email"
                                name="email"
                                placeholder="Enter your email address"
                                required
                                autocomplete="email"
                            >
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number (Optional)</label>
                        <div class="input-wrapper">
                            <input
                                type="tel"
                                class="form-input"
                                id="phone"
                                name="phone"
                                placeholder="Enter your phone number"
                                autocomplete="tel"
                            >
                            <i class="fas fa-phone input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-wrapper">
                            <input
                                type="password"
                                class="form-input"
                                id="password"
                                name="password"
                                placeholder="Create a strong password (min 8 characters)"
                                required
                                autocomplete="new-password"
                            >
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-wrapper">
                            <input
                                type="password"
                                class="form-input"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Confirm your password"
                                required
                                autocomplete="new-password"
                            >
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-register" id="registerBtn">
                        <i class="fas fa-user-plus me-2"></i>
                        <span id="registerBtnText">Create Account</span>
                        <i class="fas fa-spinner fa-spin" id="registerSpinner" style="display: none;"></i>
                    </button>
                </form>

                <div class="login-link">
                    Already have an account?
                    <a href="login.php">Sign In</a>
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

        // Password Toggle Functions
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

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const confirmPasswordInput = document.getElementById('confirm_password');
            const icon = this.querySelector('i');

            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Form Submission
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const registerBtn = document.getElementById('registerBtn');
            const registerBtnText = document.getElementById('registerBtnText');
            const registerSpinner = document.getElementById('registerSpinner');
            const alertContainer = document.getElementById('alert-container');

            // Clear previous alerts
            alertContainer.innerHTML = '';

            // Validate passwords match
            if (password !== confirmPassword) {
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Passwords do not match
                    </div>
                `;
                return;
            }

            // Validate password strength
            if (password.length < 8) {
                alertContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Password must be at least 8 characters long
                    </div>
                `;
                return;
            }

            // Disable button
            registerBtn.disabled = true;
            registerBtnText.style.display = 'none';
            registerSpinner.style.display = 'inline-block';

            try {
                const response = await fetch('../backend/api/shop/auth.php?action=register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        first_name: firstName,
                        last_name: lastName,
                        email: email,
                        phone: phone || null,
                        password: password
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // Show success message
                    alertContainer.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> ${data.message}
                        </div>
                    `;

                    // Redirect to shop
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 1500);
                } else {
                    // Show error message
                    alertContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> ${data.message || 'Registration failed. Please try again.'}
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
                registerBtn.disabled = false;
                registerBtnText.style.display = 'inline-block';
                registerSpinner.style.display = 'none';
            }
        });
    </script>
</body>
</html>
