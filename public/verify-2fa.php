<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - PC Parts Central</title>
    <link rel="icon" type="image/png" href="../ppc.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            overflow-x: hidden;
            background: var(--bg-primary);
        }

        .login-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 30%, rgba(0, 102, 255, 0.2) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(0, 245, 255, 0.2) 0%, transparent 50%);
            animation: bgPulse 8s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes bgPulse {
            0%, 100% { opacity: 0.5; }
            50% { opacity: 1; }
        }

        .grid-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image:
                linear-gradient(rgba(0, 245, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 245, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: 1;
        }

        .verify-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            z-index: 2;
        }

        .verify-card {
            background: var(--bg-glass);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid var(--border-color);
            border-radius: 1.5rem;
            padding: 3rem;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 80px rgba(0, 245, 255, 0.1);
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .verify-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .verify-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 10px 30px rgba(0, 245, 255, 0.3);
        }

        .verify-header h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .verify-header p {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .code-inputs {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 2rem 0;
        }

        .code-input {
            width: 60px;
            height: 70px;
            font-size: 2rem;
            text-align: center;
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            color: var(--text-primary);
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .code-input:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(15, 23, 42, 1);
            box-shadow: 0 0 0 4px rgba(0, 245, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-verify {
            width: 100%;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            border-radius: 0.75rem;
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
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

        .resend-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
        }

        .resend-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .resend-link a:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            border: 1px solid;
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
            border-color: rgba(16, 185, 129, 0.3);
            color: #10b981;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .timer {
            text-align: center;
            margin-top: 1rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .timer.expired {
            color: var(--danger);
        }
    </style>
</head>
<body>
    <div class="login-bg"></div>
    <div class="grid-pattern"></div>

    <div class="verify-container">
        <div class="verify-card">
            <div class="verify-header">
                <div class="verify-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2>Two-Factor Authentication</h2>
                <p>Enter the 6-digit code sent to your email</p>
            </div>

            <!-- Trust Device Checkbox -->
            <div class="form-check mb-4" style="text-align: center;">
                <input class="form-check-input" type="checkbox" id="trust-device" checked>
                <label class="form-check-label" for="trust-device" style="color: var(--text-secondary); font-size: 0.9rem; cursor: pointer;">
                    <i class="fas fa-info-circle me-1"></i>
                    Save this device for 30 days - don't ask again on this device
                </label>
            </div>

            <div id="alert-container"></div>

            <form id="verify-form">
                <div class="code-inputs">
                    <input type="text" class="code-input" maxlength="1" id="code-1" autofocus>
                    <input type="text" class="code-input" maxlength="1" id="code-2">
                    <input type="text" class="code-input" maxlength="1" id="code-3">
                    <input type="text" class="code-input" maxlength="1" id="code-4">
                    <input type="text" class="code-input" maxlength="1" id="code-5">
                    <input type="text" class="code-input" maxlength="1" id="code-6">
                </div>

                <div class="timer" id="timer">
                    Code expires in <span id="time-remaining">10:00</span>
                </div>

                <button type="submit" class="btn-verify" id="verify-btn">
                    <i class="fas fa-check-circle me-2"></i>
                    Verify Code
                </button>
            </form>

            <div class="resend-link">
                Didn't receive the code? <a href="#" id="resend-link">Resend Code</a>
            </div>

            <div class="text-center mt-3">
                <a href="simple-login.php" style="color: var(--text-muted); text-decoration: none; font-size: 0.9rem;">
                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const BASE_PATH = window.location.pathname.includes('/core1/') ? '/core1' : '';
        const API_BASE = BASE_PATH + '/backend/api';

        // Auto-focus and move to next input
        const inputs = document.querySelectorAll('.code-input');
        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value === '' && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            // Only allow numbers
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });
        });

        // Paste support
        inputs[0].addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
            pasteData.split('').forEach((char, index) => {
                if (inputs[index]) {
                    inputs[index].value = char;
                }
            });
            if (pasteData.length === 6) {
                inputs[5].focus();
            }
        });

        // Timer countdown
        let timeRemaining = 600; // 10 minutes in seconds
        const timerElement = document.getElementById('time-remaining');
        const timerContainer = document.getElementById('timer');

        let countdown = setInterval(() => {
            timeRemaining--;

            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (timeRemaining <= 0) {
                clearInterval(countdown);
                timerContainer.classList.add('expired');
                timerElement.textContent = 'Code expired';
                showAlert('Your verification code has expired. Please request a new one.', 'danger');
                document.getElementById('verify-btn').disabled = true;
            } else if (timeRemaining <= 60) {
                timerContainer.style.color = 'var(--warning)';
            }
        }, 1000);

        function showAlert(message, type = 'danger') {
            const container = document.getElementById('alert-container');
            container.innerHTML = `
                <div class="alert alert-${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                    <span>${message}</span>
                </div>
            `;

            if (type === 'success') {
                setTimeout(() => {
                    container.innerHTML = '';
                }, 5000);
            }
        }

        // Form submission
        document.getElementById('verify-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const code = Array.from(inputs).map(input => input.value).join('');

            if (code.length !== 6) {
                showAlert('Please enter the complete 6-digit code');
                return;
            }

            const verifyBtn = document.getElementById('verify-btn');
            const originalContent = verifyBtn.innerHTML;
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';

            try {
                const trustDevice = document.getElementById('trust-device').checked;
                const response = await fetch(`${API_BASE}/auth/verify-2fa-simple.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ code, trust_device: trustDevice })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('Verification successful! Redirecting...', 'success');
                    verifyBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>Success!';
                    clearInterval(countdown);

                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    showAlert(data.message || 'Invalid verification code. Please try again.');
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = originalContent;

                    // Clear inputs
                    inputs.forEach(input => input.value = '');
                    inputs[0].focus();
                }
            } catch (error) {
                console.error('Verification error:', error);
                showAlert('Connection error. Please try again.');
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = originalContent;
            }
        });

        // Resend code
        document.getElementById('resend-link').addEventListener('click', async (e) => {
            e.preventDefault();

            try {
                const response = await fetch(`${API_BASE}/auth/resend-2fa-simple.php`, {
                    method: 'POST',
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('A new verification code has been sent to your email.', 'success');

                    // Reset timer
                    clearInterval(countdown);
                    timeRemaining = 600;
                    timerContainer.classList.remove('expired');
                    timerContainer.style.color = 'var(--text-muted)';
                    document.getElementById('verify-btn').disabled = false;

                    // Restart countdown
                    countdown = setInterval(() => {
                        // Same countdown logic
                    }, 1000);
                } else {
                    showAlert(data.message || 'Failed to resend code. Please try again.');
                }
            } catch (error) {
                console.error('Resend error:', error);
                showAlert('Connection error. Please try again.');
            }
        });
    </script>
</body>
</html>
