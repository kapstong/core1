<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Verification - Supplier Portal</title>
    <link rel="icon" type="image/png" href="../ppc.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../public/assets/css/main.css">

    <style>
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

        .verify-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 245, 255, 0.1);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4),
                        0 0 100px rgba(0, 245, 255, 0.1);
            animation: slideUp 0.8s ease-out;
            max-width: 500px;
            width: 100%;
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

        .verify-header {
            padding: 3rem 2.5rem 2rem;
            text-align: center;
            background: linear-gradient(135deg, rgba(0, 102, 255, 0.08), rgba(0, 245, 255, 0.08));
        }

        .verify-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
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

        .verify-title {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .verify-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            line-height: 1.6;
        }

        .verify-body {
            padding: 2.5rem;
        }

        .verify-form {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.75rem;
        }

        .form-label {
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

        .form-input {
            width: 100%;
            height: 58px;
            background: rgba(15, 23, 42, 0.9);
            border: 2px solid rgba(0, 245, 255, 0.1);
            border-radius: 12px;
            padding: 0 1.5rem;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 0.5rem;
            color: var(--text-primary);
            transition: all 0.3s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--accent);
            background: rgba(15, 23, 42, 1);
            box-shadow: 0 0 0 4px rgba(0, 245, 255, 0.1);
        }

        .form-input::placeholder {
            text-align: center;
            color: var(--text-muted);
            letter-spacing: normal;
        }

        .btn-verify {
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
        }

        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(0, 245, 255, 0.5);
        }

        .btn-verify:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

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

        .text-center {
            text-align: center;
            margin-top: 1.5rem;
        }

        .back-link {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .back-link:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="login-bg"></div>
    <div class="particles" id="particles"></div>

    <div class="login-wrapper">
        <div class="login-container">
            <div class="verify-card">
                <div class="verify-header">
                    <div class="verify-logo">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h1 class="verify-title">2FA Verification</h1>
                    <p class="verify-subtitle">Enter the 6-digit code sent to your email to complete authentication</p>
                </div>

                <div class="verify-body">
                    <div id="alert-container"></div>

                    <form id="verify-form" class="verify-form">
                        <div class="form-group">
                            <label class="form-label">Verification Code</label>
                            <div class="input-wrapper">
                                <input
                                    type="text"
                                    class="form-input"
                                    id="verification-code"
                                    name="code"
                                    placeholder="Enter 6-digit code"
                                    maxlength="6"
                                    pattern="[0-9]{6}"
                                    required
                                    autocomplete="off"
                                >
                            </div>
                        </div>

                        <button type="submit" class="btn-verify" id="verify-btn">
                            <i class="fas fa-check-circle me-2"></i>
                            Verify Code
                        </button>
                    </form>

                    <div class="text-center">
                        <small class="text-muted">Didn't receive the code?
                            <a href="#" class="back-link" id="resend-link">Send again</a>
                        </small>
                    </div>
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

        // Auto-focus on input
        document.getElementById('verification-code').focus();

        // Format input as user types
        document.getElementById('verification-code').addEventListener('input', function(e) {
            // Remove non-numeric characters
            this.value = this.value.replace(/\D/g, '');

            // Add spacing every 3 digits for readability
            if (this.value.length > 3) {
                this.value = this.value.replace(/^(\d{3})(\d{1,3})$/, '$1 $2');
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
            }, type === 'success' ? 5000 : 8000);
        }

        // Verification form submission
        document.getElementById('verify-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const code = document.getElementById('verification-code').value.replace(/\s/g, '');
            const verifyBtn = document.getElementById('verify-btn');
            const originalContent = verifyBtn.innerHTML;

            if (code.length !== 6 || !/^\d{6}$/.test(code)) {
                showAlert('Please enter a valid 6-digit verification code.');
                return;
            }

            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';

            try {
                const response = await fetch(`${API_BASE}/auth/verify-2fa.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ code })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('<strong>Verification successful!</strong> Redirecting to dashboard...', 'success');

                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1000);
                } else {
                    showAlert(data.message || 'Verification failed. Please check your code.');
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = originalContent;
                }
            } catch (error) {
                devLog('Verification error:', error);
                if (error.message && error.message.includes('JSON')) {
                    showAlert('Server configuration error. Please contact administrator.');
                } else {
                    showAlert('Verification failed. Please try again.');
                }
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = originalContent;
            }
        });

        // Resend verification code
        document.getElementById('resend-link').addEventListener('click', async function(e) {
            e.preventDefault();

            const resendLink = e.target;
            const originalText = resendLink.textContent;

            resendLink.textContent = 'Sending...';
            resendLink.style.pointerEvents = 'none';

            try {
                const response = await fetch(`${API_BASE}/auth/resend-2fa.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('<strong>Verification code resent!</strong> Please check your email.', 'success');
                    document.getElementById('verification-code').focus();
                } else {
                    showAlert(data.message || 'Failed to resend verification code.');
                }
            } catch (error) {
                devLog('Resend error:', error);
                showAlert('Failed to resend verification code.');
            } finally {
                resendLink.textContent = originalText;
                resendLink.style.pointerEvents = 'auto';
            }
        });

        // Allow Enter key on resend link
        document.getElementById('resend-link').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.click();
            }
        });
    </script>
</body>
</html>
