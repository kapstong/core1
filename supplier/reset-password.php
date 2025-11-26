<?php
// Reset Password Page for Suppliers
session_start();

// Include maintenance mode check
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/middleware/MaintenanceMode.php';

// Check if maintenance mode is enabled and user is not admin
if (MaintenanceMode::handle()) {
    MaintenanceMode::renderMaintenancePage();
}

// Redirect already logged-in suppliers to dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'supplier') {
    header('Location: dashboard.php');
    exit;
}

// Get token from URL
$token = $_GET['token'] ?? '';
if (empty($token)) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Supplier Portal</title>
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
            --success: #10b981;
            --error: #ef4444;
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 50%, #0f1629 100%);
            color: var(--text-primary);
            line-height: 1.6;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            opacity: 0.8;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-logo {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 32px rgba(37, 99, 235, 0.3);
        }

        .auth-logo i {
            font-size: 1.75rem;
            color: white;
        }

        .auth-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .auth-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            letter-spacing: 0.025em;
        }

        .form-input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            height: 48px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 0 3rem 0 1rem;
            font-size: 1rem;
            color: var(--text-primary);
            transition: all 0.2s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(0, 102, 255, 0.1);
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        .input-icon {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1rem;
            pointer-events: none;
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1rem;
            cursor: pointer;
            padding: 0.375rem;
            transition: color 0.2s ease;
            z-index: 1;
        }

        .password-toggle:hover {
            color: var(--text-secondary);
        }

        .strength-meter {
            margin-top: 0.5rem;
            height: 4px;
            background: var(--border-color);
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-text {
            font-size: 0.75rem;
            margin-top: 0.25rem;
            font-weight: 500;
        }

        .strength-weak { background: var(--error); }
        .strength-medium { background: #f59e0b; }
        .strength-strong { background: var(--success); }

        .btn-primary {
            width: 100%;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 16px rgba(0, 102, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 24px rgba(0, 102, 255, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-loading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .alert-icon {
            flex-shrink: 0;
            margin-top: 0.125rem;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--error);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: #86efac;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
        }

        .back-link:hover {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-2px);
        }

        .back-link i {
            font-size: 0.875rem;
        }

        .security-note {
            text-align: center;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .security-note i {
            color: var(--accent);
            margin-bottom: 0.5rem;
        }

        .security-note h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .security-note p {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin: 0;
        }

        @media (max-width: 640px) {
            .auth-container {
                gap: 1rem;
            }

            .auth-card {
                padding: 2rem;
            }

            .auth-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 0.5rem;
            }

            .auth-card {
                padding: 1.5rem;
            }

            .back-link {
                font-size: 0.8125rem;
                padding: 0.625rem 1.25rem;
            }
        }

        /* Accessibility */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .form-input:focus,
        .btn-primary:focus,
        .back-link:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Login</span>
        </a>

        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="auth-title">Reset Supplier Password</h1>
                <p class="auth-subtitle">Create a strong password for your supplier account</p>
            </div>

            <div id="alert-container"></div>

            <form id="resetForm" novalidate>
                <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="form-group">
                    <label class="form-label" for="password">New Password</label>
                    <div class="form-input-wrapper">
                        <input
                            type="password"
                            class="form-input"
                            id="password"
                            name="password"
                            placeholder="Enter new password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            aria-describedby="password-help"
                        >
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div id="password-help" class="sr-only">Password must be at least 8 characters long with mixed case, numbers, and symbols</div>
                    <div class="strength-meter" id="strengthMeter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <div class="strength-text" id="strengthText">Password strength: Weak</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirmPassword">Confirm Password</label>
                    <div class="form-input-wrapper">
                        <input
                            type="password"
                            class="form-input"
                            id="confirmPassword"
                            name="confirmPassword"
                            placeholder="Confirm new password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                        >
                        <i class="fas fa-check input-icon" id="matchIcon" style="display: none;"></i>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="resetBtn">
                    <span id="resetBtnText">Reset Password</span>
                </button>
            </form>
        </div>

        <div class="security-note">
            <i class="fas fa-shield-alt" style="font-size: 1.5rem; margin-bottom: 0.5rem; display: block;"></i>
            <h4>Secure Password Reset</h4>
            <p>Your new password will be securely encrypted and stored. Choose a strong, unique password that you haven't used elsewhere.</p>
        </div>
    </div>

    <script>
        // Password strength evaluation
        function evaluatePasswordStrength(password) {
            let score = 0;
            const checks = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };

            score += checks.length ? 1 : 0;
            score += checks.uppercase ? 1 : 0;
            score += checks.lowercase ? 1 : 0;
            score += checks.number ? 1 : 0;
            score += checks.special ? 1 : 0;

            if (score <= 2) return { level: 'weak', percentage: 33, color: 'strength-weak' };
            if (score <= 3) return { level: 'medium', percentage: 66, color: 'strength-medium' };
            return { level: 'strong', percentage: 100, color: 'strength-strong' };
        }

        // Update password strength meter
        function updatePasswordStrength(password) {
            const strengthMeter = document.getElementById('strengthMeter');
            const strengthFill = document.getElementById('strengthFill');
            const strengthText = document.getElementById('strengthText');

            if (password.length === 0) {
                strengthFill.style.width = '0%';
                strengthFill.className = 'strength-fill';
                strengthText.textContent = 'Password strength: Enter a password';
                strengthMeter.setAttribute('aria-valuenow', '0');
                return;
            }

            const strength = evaluatePasswordStrength(password);
            strengthFill.style.width = strength.percentage + '%';
            strengthFill.className = `strength-fill ${strength.color}`;
            strengthText.textContent = `Password strength: ${strength.level.charAt(0).toUpperCase() + strength.level.slice(1)}`;
            strengthMeter.setAttribute('aria-valuenow', strength.percentage);
        }

        // Check if passwords match
        function checkPasswordsMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchIcon = document.getElementById('matchIcon');

            if (confirmPassword.length === 0) {
                matchIcon.style.display = 'none';
                matchIcon.className = 'fas fa-check input-icon';
                return false;
            }

            if (password === confirmPassword) {
                matchIcon.style.display = 'block';
                matchIcon.className = 'fas fa-check input-icon';
                matchIcon.style.color = 'var(--success)';
                return true;
            } else {
                matchIcon.style.display = 'block';
                matchIcon.className = 'fas fa-times input-icon';
                matchIcon.style.color = 'var(--error)';
                return false;
            }
        }

        // Validate form
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const errors = [];

            if (password.length < 8) {
                errors.push('Password must be at least 8 characters long');
            }

            if (!/[A-Z]/.test(password)) {
                errors.push('Password must contain at least one uppercase letter');
            }

            if (!/[a-z]/.test(password)) {
                errors.push('Password must contain at least one lowercase letter');
            }

            if (!/[0-9]/.test(password)) {
                errors.push('Password must contain at least one number');
            }

            if (password !== confirmPassword) {
                errors.push('Passwords do not match');
            }

            return errors;
        }

        // Show alert
        function showAlert(message, type = 'danger') {
            const alertContainer = document.getElementById('alert-container');
            const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';

            alertContainer.innerHTML = `
                <div class="alert alert-${type}">
                    <i class="fas ${iconClass} alert-icon"></i>
                    <div>${message}</div>
                </div>
            `;

            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 5000);
            }
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const resetForm = document.getElementById('resetForm');
            const resetBtn = document.getElementById('resetBtn');

            // Password toggle
            togglePasswordBtn.addEventListener('click', function() {
                const icon = this.querySelector('i');
                const isVisible = passwordInput.type === 'text';

                passwordInput.type = isVisible ? 'password' : 'text';
                icon.className = isVisible ? 'fas fa-eye' : 'fas fa-eye-slash';
                this.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
            });

            // Password strength monitoring
            passwordInput.addEventListener('input', function() {
                updatePasswordStrength(this.value);
                checkPasswordsMatch();
            });

            // Confirm password monitoring
            confirmPasswordInput.addEventListener('input', function() {
                checkPasswordsMatch();
            });

            // Real-time validation
            passwordInput.addEventListener('blur', function() {
                const errors = validateForm();
                if (this.value && errors.some(error => error.includes('Password must'))) {
                    this.style.borderColor = 'var(--error)';
                } else {
                    this.style.borderColor = 'var(--border-color)';
                }
            });

            confirmPasswordInput.addEventListener('blur', function() {
                const errors = validateForm();
                if (this.value && errors.includes('Passwords do not match')) {
                    this.style.borderColor = 'var(--error)';
                } else {
                    this.style.borderColor = 'var(--border-color)';
                }
            });

            // Form submission
            resetForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const token = document.getElementById('token').value;
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                // Clear previous alerts
                document.getElementById('alert-container').innerHTML = '';

                // Validate form
                const errors = validateForm();
                if (errors.length > 0) {
                    showAlert(errors[0], 'danger');
                    return;
                }

                // Show loading state
                resetBtn.disabled = true;
                resetBtn.classList.add('btn-loading');
                resetBtn.innerHTML = '<span>Loading...</span>';

                try {
                    const response = await fetch('../backend/api/auth/reset-password.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            token: token,
                            password: password,
                            type: 'supplier'
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert(data.message, 'success');

                        // Disable form and redirect
                        resetForm.style.display = 'none';
                        setTimeout(() => {
                            window.location.href = 'index.php';
                        }, 3000);

                    } else {
                        showAlert(data.message || 'Failed to reset password. Please try again.', 'danger');
                    }

                } catch (error) {
                    console.error('Reset error:', error);
                    showAlert('An error occurred. Please try again later.', 'danger');
                } finally {
                    // Reset button
                    resetBtn.disabled = false;
                    resetBtn.classList.remove('btn-loading');
                    resetBtn.innerHTML = '<span id="resetBtnText">Reset Password</span>';
                }
            });

            // Initialize password strength meter
            updatePasswordStrength('');

            // Focus first input
            passwordInput.focus();
        });

        // Keyboard accessibility
        document.addEventListener('keydown', function(e) {
            // ESC key to clear alerts
            if (e.key === 'Escape') {
                document.getElementById('alert-container').innerHTML = '';
            }
        });
    </script>
</body>
</html>
