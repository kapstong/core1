<?php
// Email Verification Page for Customers
session_start();

// Include maintenance mode check
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/middleware/MaintenanceMode.php';

// Check if maintenance mode is enabled and user is not admin
if (MaintenanceMode::handle()) {
    MaintenanceMode::renderMaintenancePage();
}

// Get token from URL
$token = $_GET['token'] ?? '';
$verificationStatus = null;
$verificationMessage = '';
$customerEmail = '';
$alreadyVerified = false;

// If token exists, verify it
if (!empty($token)) {
    // Call the verification API
    $apiUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
              "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . "/../backend/api/shop/verify-email.php?token=" . urlencode($token);

    // Use cURL to call the API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response) {
        $result = json_decode($response, true);

        if ($result && isset($result['success'])) {
            if ($result['success']) {
                $verificationStatus = 'success';
                $verificationMessage = $result['data']['message'] ?? 'Email verified successfully!';
                $customerEmail = $result['data']['email'] ?? '';
                $alreadyVerified = $result['data']['already_verified'] ?? false;
            } else {
                $verificationStatus = 'error';
                $verificationMessage = $result['error'] ?? 'Verification failed. The link may be invalid or expired.';
            }
        } else {
            $verificationStatus = 'error';
            $verificationMessage = 'Unable to verify email. Please try again later.';
        }
    } else {
        $verificationStatus = 'error';
        $verificationMessage = 'Unable to connect to verification service.';
    }
} else {
    $verificationStatus = 'error';
    $verificationMessage = 'No verification token provided.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - PC Parts Central</title>
    <link rel="icon" type="image/png" href="ppc.png">
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

        .verification-container {
            width: 100%;
            max-width: 500px;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .verification-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 3rem;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .verification-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .logo-section {
            margin-bottom: 2rem;
        }

        .logo-text {
            font-size: 1.75rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .logo-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .icon-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            position: relative;
        }

        .icon-container.success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.1));
            color: var(--success);
            animation: successPulse 2s ease-in-out infinite;
        }

        .icon-container.error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.1));
            color: var(--error);
        }

        @keyframes successPulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 20px rgba(16, 185, 129, 0);
            }
        }

        .verification-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .verification-title.success {
            color: var(--success);
        }

        .verification-title.error {
            color: var(--error);
        }

        .verification-message {
            color: var(--text-secondary);
            margin-bottom: 2rem;
            font-size: 1rem;
            line-height: 1.6;
        }

        .customer-email {
            color: var(--accent);
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border: none;
            color: white;
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 102, 255, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            padding: 0.875rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-primary);
        }

        .info-box {
            background: rgba(0, 102, 255, 0.1);
            border: 1px solid rgba(0, 102, 255, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            text-align: left;
        }

        .info-box h4 {
            color: var(--accent);
            font-size: 0.875rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .info-box ul {
            margin: 0;
            padding-left: 1.25rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .countdown {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-card">
            <!-- Logo -->
            <div class="logo-section">
                <div class="logo-text">PC Parts Central</div>
                <div class="logo-subtitle">Premium Gaming & Workstation Components</div>
            </div>

            <?php if ($verificationStatus === 'success'): ?>
                <!-- Success State -->
                <div class="icon-container success">
                    <i class="fas fa-check-circle"></i>
                </div>

                <h1 class="verification-title success">
                    <?php echo $alreadyVerified ? 'Already Verified!' : 'Email Verified!'; ?>
                </h1>

                <p class="verification-message">
                    <?php echo htmlspecialchars($verificationMessage); ?>
                    <?php if (!empty($customerEmail)): ?>
                        <br><br>
                        <span class="customer-email"><?php echo htmlspecialchars($customerEmail); ?></span>
                    <?php endif; ?>
                </p>

                <?php if (!$alreadyVerified): ?>
                    <div class="info-box">
                        <h4><i class="fas fa-gifts"></i> What's Next?</h4>
                        <ul>
                            <li>Browse our extensive catalog of premium PC components</li>
                            <li>Add items to your cart and checkout securely</li>
                            <li>Track your orders in real-time</li>
                            <li>Access exclusive member benefits and discounts</li>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <?php if (isset($_SESSION['customer_id'])): ?>
                        <a href="dashboard.php" class="btn-primary">
                            <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Login to Your Account
                        </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn-secondary">
                        <i class="fas fa-shopping-bag"></i> Start Shopping
                    </a>
                </div>

                <?php if (!isset($_SESSION['customer_id'])): ?>
                    <div class="countdown">
                        Redirecting to login page in <span id="countdown">10</span> seconds...
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Error State -->
                <div class="icon-container error">
                    <i class="fas fa-times-circle"></i>
                </div>

                <h1 class="verification-title error">Verification Failed</h1>

                <p class="verification-message">
                    <?php echo htmlspecialchars($verificationMessage); ?>
                </p>

                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> What can you do?</h4>
                    <ul>
                        <li>Check if you used the complete verification link from your email</li>
                        <li>The verification link may have expired (valid for 24 hours)</li>
                        <li>Try logging in - you may already be verified</li>
                        <li>Contact support if the problem persists</li>
                    </ul>
                </div>

                <div class="action-buttons">
                    <a href="login.php" class="btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Try Login
                    </a>
                    <a href="register.php" class="btn-secondary">
                        <i class="fas fa-user-plus"></i> Register Again
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        <?php if ($verificationStatus === 'success' && !isset($_SESSION['customer_id'])): ?>
        // Auto redirect after 10 seconds
        let seconds = 10;
        const countdownElement = document.getElementById('countdown');

        const timer = setInterval(() => {
            seconds--;
            if (countdownElement) {
                countdownElement.textContent = seconds;
            }

            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = 'login.php';
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>
