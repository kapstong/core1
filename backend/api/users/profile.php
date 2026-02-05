<?php
/**
 * Users API - Update own profile
 * PUT /backend/api/users/profile.php
 */


// Suppress error display for clean JSON responses
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/Validator.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/User.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
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
$userId = $currentUser['id'];

$data = json_decode(file_get_contents('php://input'), true);

try {
    $db = Database::getInstance()->getConnection();
    $userModel = new User();

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

    // Validate phone if provided (optional field)
    if (isset($data['phone'])) {
        $phone = trim($data['phone']);
        if (!empty($phone) && !Validator::maxLength($phone, 50)) {
            $errors[] = 'Phone number cannot exceed 50 characters';
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

    // Phone validation (optional field - only add to updates if table supports it)
    if (isset($data['phone'])) {
        $updates['phone'] = trim($data['phone']);
    }

    // Role-based field restrictions
    $userRole = $currentUser['role'];
    $restrictedFields = [];

    // Staff users cannot modify these fields
    if ($userRole === 'staff') {
        $forbiddenFields = ['role', 'is_active'];
        foreach ($forbiddenFields as $field) {
            if (isset($data[$field])) {
                $restrictedFields[] = $field;
            }
        }
    }

    // Only admin can modify roles and active status
    if ($userRole !== 'admin') {
        if (isset($data['role']) || isset($data['is_active'])) {
            $restrictedFields[] = isset($data['role']) ? 'role' : 'is_active';
        }
    }

    if (!empty($restrictedFields)) {
        Response::error('You do not have permission to update the following fields: ' . implode(', ', $restrictedFields), 403);
    }

    if (empty($updates)) {
        Response::error('No valid fields to update', 400);
    }

    // Update user using model
    $result = $userModel->update($userId, $updates);

    if ($result) {
        // Update session data with new user information
        Auth::updateUserSession($updates);

        // Log the profile update action
        $logger = new Logger($currentUser['id']);
        $logger->log('Profile updated', 'user', $userId, [
            'updated_fields' => array_keys($updates),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);

        // Get updated user data
        $updatedUser = $userModel->findById($userId);

        Response::success([
            'message' => 'Profile updated successfully',
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
        Response::error('Failed to update profile', 500);
    }

} catch (Exception $e) {
    Logger::logError('Profile update error', [
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'data' => $data
    ], $currentUser['id']);
    Response::error('An error occurred while updating your profile', 500);
}

