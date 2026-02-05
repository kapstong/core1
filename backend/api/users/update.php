<?php
/**
 * Users API - Update user
 * PUT /backend/api/users/update.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/User.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Require authentication
$user = Auth::requireAuth();

// Get current user data
$currentUser = Auth::user();

// Only admins can update users
if ($currentUser['role'] !== 'admin') {
    Response::error('Access denied. Admin privileges required.', 403);
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET request - fetch user for editing
if ($method === 'GET') {
    if (!isset($_GET['id'])) {
        Response::error('User ID is required', 400);
    }

    $userId = (int)$_GET['id'];
    $userModel = new User();
    $existingUser = $userModel->findById($userId);

    if (!$existingUser) {
        Response::error('User not found', 404);
    }

    // Don't send password hash to client
    unset($existingUser['password_hash']);

    Response::success($existingUser, 'User retrieved successfully');
    exit; // Stop execution after sending response
}

// Handle PUT/POST request - update user
if ($method !== 'PUT' && $method !== 'POST') {
    Response::error('Method not allowed. Use GET to retrieve or PUT/POST to update', 405);
}

// Accept data from either JSON body or POST parameters
$data = json_decode(file_get_contents('php://input'), true);

// If JSON decode failed or empty, try $_POST
if (!$data) {
    $data = $_POST;
}

// Also check for ID in URL parameters if not in body
if (!isset($data['id']) && isset($_GET['id'])) {
    $data['id'] = $_GET['id'];
}

// Validate required user ID
if (empty($data['id'])) {
    Response::error('User ID is required', 400);
}

$userId = (int)$data['id'];

try {
    $db = Database::getInstance()->getConnection();
    $userModel = new User();

    // Check if user exists
    $existingUser = $userModel->findById($userId);
    if (!$existingUser) {
        Response::error('User not found', 404);
    }

    // Prevent admin from deactivating themselves
    if ($userId === $currentUser['id'] && isset($data['is_active']) && !$data['is_active']) {
        Response::error('You cannot deactivate your own account', 400);
    }

    // Manual validation using existing Validator methods
    $errors = [];

    // Validate username if provided
    if (isset($data['username'])) {
        $username = trim($data['username']);
        if (empty($username)) {
            $errors[] = 'Username cannot be empty';
        } elseif (!Validator::minLength($username, 3)) {
            $errors[] = 'Username must be at least 3 characters';
        } elseif (!Validator::maxLength($username, 50)) {
            $errors[] = 'Username cannot exceed 50 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores';
        }
    }

    // Validate full_name if provided
    if (isset($data['full_name'])) {
        $fullName = trim($data['full_name']);
        if (empty($fullName)) {
            $errors[] = 'Full name cannot be empty';
        } elseif (!Validator::minLength($fullName, 2)) {
            $errors[] = 'Full name must be at least 2 characters';
        } elseif (!Validator::maxLength($fullName, 100)) {
            $errors[] = 'Full name cannot exceed 100 characters';
        }
    }

    // Validate email if provided
    if (isset($data['email'])) {
        $email = trim($data['email']);
        if (empty($email)) {
            $errors[] = 'Email cannot be empty';
        } elseif (!Validator::email($email)) {
            $errors[] = 'Invalid email format';
        } elseif (!Validator::maxLength($email, 100)) {
            $errors[] = 'Email cannot exceed 100 characters';
        }
    }

    // Validate role if provided - exclude supplier since they are managed separately
    if (isset($data['role'])) {
        $allowedRoles = ['admin', 'inventory_manager', 'purchasing_officer', 'staff'];
        if (!in_array($data['role'], $allowedRoles)) {
            $errors[] = 'Invalid role specified';
        }

        // Prevent changing to/from supplier role
        if ($existingUser['role'] === 'supplier' || $data['role'] === 'supplier') {
            $errors[] = 'Supplier accounts cannot be modified through user management. Use the supplier management section instead.';
        }
    }

    // Validate is_active if provided
    if (isset($data['is_active'])) {
        if (!is_bool($data['is_active']) && !in_array($data['is_active'], [0, 1, '0', '1', true, false])) {
            $errors[] = 'Active status must be a boolean value';
        }
    }

    if (!empty($errors)) {
        Response::error('Validation failed: ' . implode(', ', $errors), 400);
    }

    // Build updates array from validated data
    $updates = [];

    // Username validation
    if (isset($data['username'])) {
        $data['username'] = trim($data['username']);
        if ($userModel->usernameExists($data['username'], $userId)) {
            Response::error('Username already exists', 400);
        }
        $updates['username'] = $data['username'];
    }

    // Email validation
    if (isset($data['email'])) {
        $data['email'] = trim(strtolower($data['email']));
        if ($userModel->emailExists($data['email'], $userId)) {
            Response::error('Email already exists', 400);
        }
        $updates['email'] = $data['email'];
    }

    // Full name validation
    if (isset($data['full_name'])) {
        $updates['full_name'] = trim($data['full_name']);
    }

    // Role validation - exclude supplier since they are managed separately
    if (isset($data['role'])) {
        $allowed_roles = ['admin', 'inventory_manager', 'purchasing_officer', 'staff'];
        if (!in_array($data['role'], $allowed_roles)) {
            Response::error('Invalid role specified', 400);
        }
        $updates['role'] = $data['role'];
    }

    // Active status validation
    if (isset($data['is_active'])) {
        $updates['is_active'] = (bool)$data['is_active'];
    }

    if (empty($updates)) {
        Response::error('No valid fields to update', 400);
    }

    // Update user using model
    $result = $userModel->update($userId, $updates);

    if ($result) {
        // Log the user update action
        $logger = new Logger($currentUser['id']);
        $logger->log('User updated', 'user', $userId, [
            'updated_fields' => array_keys($updates),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        // Get updated user data
        $updatedUser = $userModel->findById($userId);

            // If this is a supplier account activation change, reflect it on suppliers table
            if (isset($updates['is_active']) && $updatedUser['role'] === 'supplier') {
                try {
                    $stmt = $db->prepare("UPDATE suppliers SET is_active = :is_active WHERE user_id = :uid");
                    $stmt->execute([':is_active' => $updates['is_active'] ? 1 : 0, ':uid' => $userId]);
                } catch (Exception $e) {
                    // Non-fatal: log and continue
                    $logger->log('Failed to update supplier active flag', 'supplier', $userId, ['error' => $e->getMessage()]);
                }
            }
        Response::success([
            'message' => 'User updated successfully',
            'user' => [
                'id' => $updatedUser['id'],
                'username' => $updatedUser['username'],
                'full_name' => $updatedUser['full_name'],
                'email' => $updatedUser['email'],
                'role' => $updatedUser['role'],
                'is_active' => (bool)$updatedUser['is_active'],
                'last_login' => $updatedUser['last_login'],
                'created_at' => $updatedUser['created_at']
            ]
        ]);
    } else {
        Response::error('Failed to update user', 500);
    }

} catch (Exception $e) {
    Logger::logError('User update error', [
        'user_id' => $userId ?? null,
        'error' => $e->getMessage(),
        'data' => $data
    ], $currentUser['id'] ?? null);
    Response::error('An error occurred while updating the user', 500);
}

