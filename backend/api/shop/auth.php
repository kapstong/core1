<?php
/**
 * Shop Customer Authentication API Endpoint
 * POST /backend/api/shop/auth.php - Customer login/register
 *
 * Actions:
 * - register: Register new customer
 * - login: Customer login
 * - logout: Customer logout
 * - me: Get current customer info
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../middleware/CORS.php';
require_once __DIR__ . '/../../models/Customer.php';

CORS::handle();

// Start session for customer authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$customerModel = new Customer();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            handlePostRequest($action, $customerModel);
            break;

        case 'GET':
            handleGetRequest($action, $customerModel);
            break;

        case 'DELETE':
            handleDeleteRequest($action, $customerModel);
            break;

        default:
            Response::error('Method not allowed', 405);
    }

} catch (Exception $e) {
    Response::serverError('Authentication error: ' . $e->getMessage());
}

function handlePostRequest($action, $customerModel) {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    switch ($action) {
        case 'register':
            registerCustomer($input, $customerModel);
            break;

        case 'login':
            loginCustomer($input, $customerModel);
            break;

        case 'forgot_password':
            forgotPassword($input, $customerModel);
            break;

        case 'reset_password':
            resetPassword($input, $customerModel);
            break;

        default:
            Response::error('Invalid action. Supported actions: register, login, forgot_password, reset_password', 400);
    }
}

function handleGetRequest($action, $customerModel) {
    switch ($action) {
        case 'me':
            getCurrentCustomer($customerModel);
            break;

        default:
            Response::error('Invalid action. Supported actions: me', 400);
    }
}

function handleDeleteRequest($action, $customerModel) {
    switch ($action) {
        case 'logout':
            logoutCustomer();
            break;

        default:
            Response::error('Invalid action. Supported actions: logout', 400);
    }
}

function registerCustomer($data, $customerModel) {
    // Validate required fields
    $required = ['email', 'password', 'first_name', 'last_name'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            Response::error("{$field} is required", 400);
        }
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        Response::error('Invalid email format', 400);
    }

    $email = strtolower(trim($data['email']));

    // Check if this email belongs to a staff user - prevent staff from registering as customers
    require_once __DIR__ . '/../../models/User.php';
    $userModel = new User();
    $staffUser = $userModel->findByEmail($email);
    if ($staffUser) {
        Response::error('Staff users cannot register through the shop. Please contact an administrator.', 403);
    }

    // Check if email already exists
    $existing = $customerModel->findByEmail($email);
    if ($existing) {
        Response::error('Email already registered', 409);
    }

    // Validate password strength
    if (strlen($data['password']) < 8) {
        Response::error('Password must be at least 8 characters long', 400);
    }

    // Generate email verification token
    $verificationToken = bin2hex(random_bytes(32));

    // Prepare customer data
    $customerData = [
        'email' => strtolower(trim($data['email'])),
        'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        'first_name' => trim($data['first_name']),
        'last_name' => trim($data['last_name']),
        'phone' => isset($data['phone']) ? trim($data['phone']) : null,
        'date_of_birth' => isset($data['date_of_birth']) ? $data['date_of_birth'] : null,
        'gender' => isset($data['gender']) ? $data['gender'] : null,
        'email_verification_token' => $verificationToken,
        'email_verified' => 0
    ];

    // Create customer
    $customer = $customerModel->create($customerData);

    if (!$customer) {
        Response::error('Failed to create customer account', 500);
    }

    // Send verification email
    try {
        require_once __DIR__ . '/../../utils/Email.php';
        require_once __DIR__ . '/../config/env.php';
        $email = new Email();

        // Build verification URL pointing to public page
        $appUrl = Env::get('APP_URL', 'https://core1.merchandising-c23.com');
        $verificationUrl = $appUrl . "/verify-email.php?token={$verificationToken}";
        $email->sendEmailVerification($customer, $verificationUrl);
    } catch (Exception $e) {
        // Log error but don't fail registration
        error_log('Failed to send verification email: ' . $e->getMessage());
    }

    // Set session
    $_SESSION['customer_id'] = $customer['id'];
    $_SESSION['customer_email'] = $customer['email'];

    // Transfer any guest cart to customer
    if (isset($_SESSION['guest_session_id'])) {
        $customerModel->transferCartToCustomer($_SESSION['guest_session_id'], $customer['id']);
        unset($_SESSION['guest_session_id']);
    }

    // Return customer data (exclude password hash)
    unset($customer['password_hash']);
    unset($customer['email_verification_token']);
    unset($customer['password_reset_token']);

    Response::success([
        'customer' => $customer,
        'email_verification_sent' => true
    ], 'Account created successfully', 201);
}

function loginCustomer($data, $customerModel) {
    // Validate required fields
    if (empty($data['email']) || empty($data['password'])) {
        Response::error('Email and password are required', 400);
    }

    $email = strtolower(trim($data['email']));

    // Check if this email belongs to a staff user - prevent staff from logging in through shop
    require_once __DIR__ . '/../../models/User.php';
    $userModel = new User();
    $staffUser = $userModel->findByEmail($email);
    if ($staffUser) {
        Response::error('Staff users cannot login through the shop. Please use the employee login.', 403);
    }

    // Find customer by email
    $customer = $customerModel->findByEmail($email);

    if (!$customer) {
        Response::error('Invalid email or password', 401);
    }

    // Note: Customer accounts don't have is_active field like staff accounts
    // All registered customers are considered active

    // Verify password
    if (!password_verify($data['password'], $customer['password_hash'])) {
        Response::error('Invalid email or password', 401);
    }

    // Check for remember me parameter
    $remember = isset($data['remember']) && $data['remember'] === true;

    // Update last login
    $customerModel->updateLastLogin($customer['id']);

    // Set session
    $_SESSION['customer_id'] = $customer['id'];
    $_SESSION['customer_email'] = $customer['email'];

    // If remember me is requested, create persistent token for customers
    if ($remember) {
        require_once __DIR__ . '/../../middleware/Auth.php';
        Auth::createPersistentToken($customer['id']);
    }

    // Transfer any guest cart to customer
    if (isset($_SESSION['guest_session_id'])) {
        $customerModel->transferCartToCustomer($_SESSION['guest_session_id'], $customer['id']);
        unset($_SESSION['guest_session_id']);
    }

    // Return customer data (exclude sensitive fields)
    unset($customer['password_hash']);
    unset($customer['email_verification_token']);
    unset($customer['password_reset_token']);

    Response::success([
        'customer' => $customer,
        'message' => 'Logged-in successfully'
    ]);
}

function getCurrentCustomer($customerModel) {
    if (!isset($_SESSION['customer_id'])) {
        // Check if user is authenticated as staff/admin - if so, they should NOT be on the shop
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
            // User is authenticated as staff/admin, NOT as a customer
            // Staff should use the employee dashboard, not the shop
            Response::success([
                'customer' => null,
                'authenticated' => false,
                'is_staff' => true,
                'message' => 'Staff users cannot access the shop. Please use the employee dashboard.'
            ], 'Staff user cannot shop');
        } else {
            // Regular guest not authenticated - allow browsing without redirect
            Response::success([
                'customer' => null,
                'authenticated' => false
            ], 'Guest user - browsing allowed');
        }
        return;
    }

    $customer = $customerModel->findById($_SESSION['customer_id']);

    if (!$customer) {
        // Clear invalid session
        unset($_SESSION['customer_id']);
        unset($_SESSION['customer_email']);
        Response::success([
            'customer' => null,
            'authenticated' => false,
            'redirect' => 'login.php'
        ], 'Customer not authenticated');
        return;
    }

    // Return customer data (exclude sensitive fields)
    unset($customer['password_hash']);
    unset($customer['email_verification_token']);
    unset($customer['password_reset_token']);

    Response::success(['customer' => $customer]);
}

function logoutCustomer() {
    // Clear customer session
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_email']);

    // Generate new guest session ID for continued shopping
    if (!isset($_SESSION['guest_session_id'])) {
        $_SESSION['guest_session_id'] = session_id() . '_guest_' . time();
    }

    Response::success(['message' => 'Logout successful']);
}

function forgotPassword($data, $customerModel) {
    // Validate required fields
    if (empty($data['email'])) {
        Response::error('Email is required', 400);
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        Response::error('Invalid email format', 400);
    }

    $email = strtolower(trim($data['email']));

    // Find customer by email (send reset link regardless of verified status)
    $customer = $customerModel->findByEmail($email);

    // Always return success message for security (don't reveal if email exists or not)
    // But only send email if customer exists
    if ($customer) {
        try {
            // Generate reset token
            $resetToken = bin2hex(random_bytes(32));

            // Store reset token in verification_codes table
            require_once __DIR__ . '/../../config/database.php';
            $db = Database::getInstance()->getConnection();

            // Use MySQL's DATE_ADD to avoid timezone issues
            $stmt = $db->prepare("INSERT INTO verification_codes (user_id, code, code_type, expires_at) VALUES (?, ?, 'password_reset', DATE_ADD(NOW(), INTERVAL 1 HOUR))");
            $stmt->execute([$customer['id'], $resetToken]);

            // Send reset email
            require_once __DIR__ . '/../../utils/Email.php';
            require_once __DIR__ . '/../config/env.php';
            $emailService = new Email();

            // Use configured APP_URL instead of building dynamically
            $appUrl = Env::get('APP_URL', 'https://core1.merchandising-c23.com');

            // Build reset URL - use root level URL for customers
            $resetUrl = $appUrl . "/reset-password.php?token={$resetToken}&type=customer";
            $emailService->sendPasswordResetEmail($customer, $resetUrl);

        } catch (Exception $e) {
            // Log error but don't reveal to user for security
            error_log('Failed to send password reset email: ' . $e->getMessage());
        }
    }

    // Return success message (always the same for security)
    Response::success([
        'message' => 'If an account with that email exists, a password reset link has been sent.'
    ]);
}

function resetPassword($data, $customerModel) {
    // Validate required fields
    $required = ['token', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            Response::error("{$field} is required", 400);
        }
    }

    $token = trim($data['token']);
    $password = $data['password'];

    // Validate password strength
    if (strlen($password) < 8) {
        Response::error('Password must be at least 8 characters long', 400);
    }

    // Find valid reset token
    require_once __DIR__ . '/../../config/database.php';
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        SELECT vc.*, c.id as customer_id, c.email, c.first_name, c.last_name
        FROM verification_codes vc
        JOIN customers c ON vc.user_id = c.id
        WHERE vc.code = ? AND vc.code_type = 'password_reset'
        AND vc.is_used = 0 AND vc.expires_at > NOW()
        AND vc.attempts < vc.max_attempts
    ");
    $stmt->execute([$token]);
    $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetRecord) {
        Response::error('Invalid or expired reset token', 400);
    }

    $customerId = $resetRecord['customer_id'];

    try {
        // Start transaction
        $db->beginTransaction();

        // Update customer password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE customers SET password_hash = ? WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $customerId]);

        // Mark token as used
        $tokenStmt = $db->prepare("
            UPDATE verification_codes
            SET is_used = 1, used_at = NOW()
            WHERE code = ?
        ");
        $tokenStmt->execute([$token]);

        // Send confirmation email
        try {
            require_once __DIR__ . '/../../utils/Email.php';
            $emailService = new Email();
            $customer = [
                'id' => $customerId,
                'email' => $resetRecord['email'],
                'first_name' => $resetRecord['first_name'],
                'last_name' => $resetRecord['last_name']
            ];
            $emailService->sendPasswordChangedEmail($customer);
        } catch (Exception $e) {
            // Log error but continue (password was successfully changed)
            error_log('Failed to send password changed email: ' . $e->getMessage());
        }

        // Commit transaction
        $db->commit();

        Response::success(['message' => 'Password has been reset successfully']);

    } catch (Exception $e) {
        // Rollback on error
        $db->rollBack();
        Response::error('Failed to reset password', 500);
    }
}
