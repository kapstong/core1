<?php
/**
 * Facial Login API Endpoint
 * POST /backend/api/auth/facial-login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../utils/Response.php';
require_once __DIR__ . '/../../utils/AuditLogger.php';
require_once __DIR__ . '/../../middleware/Auth.php';
require_once __DIR__ . '/../../middleware/CORS.php';

CORS::handle();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        Response::error('Invalid JSON input', 400);
    }

    $username = trim((string)($input['username'] ?? ''));
    $descriptor = $input['descriptor'] ?? null;
    $blinkCount = (int)($input['blink_count'] ?? 0);
    $livenessVerifiedAt = (string)($input['liveness_verified_at'] ?? '');

    $descriptor = normalizeDescriptor($descriptor);
    if ($descriptor === null) {
        Response::error('Invalid facial descriptor payload. Expected 128 numeric values.', 400);
    }

    if ($blinkCount < 1) {
        Response::error('Liveness verification failed (blink required)', 401);
    }

    $livenessTs = strtotime($livenessVerifiedAt);
    if ($livenessTs === false || (time() - $livenessTs) > 60) {
        Response::error('Liveness challenge expired. Please blink again.', 401);
    }

    $db = Database::getInstance()->getConnection();
    $allowedRoles = ['admin', 'inventory_manager', 'staff'];
    $verificationThreshold = 0.42;
    $autoThreshold = 0.40;
    $ambiguityDelta = 0.05;
    $crossUserBetterDelta = 0.02;
    $mode = ($username === '') ? 'auto' : 'username';
    $distance = 999.0;
    $user = null;

    if ($mode === 'username') {
        $stmt = $db->prepare("
            SELECT id, username, email, role, full_name, is_active, password_hash, face_descriptor, face_biometric_enabled
            FROM users
            WHERE username = :username
            LIMIT 1
        ");
        $stmt->bindValue(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            AuditLogger::log('face_login_failed', 'user', null, "Face login failed: unknown username {$username}");
            Response::error('Invalid face login credentials', 401);
        }

        if ((int)$user['is_active'] !== 1) {
            Response::error('Account is inactive', 403);
        }

        if (!in_array($user['role'], $allowedRoles, true)) {
            Response::error('Face login is only available for admin and inventory staff accounts', 403);
        }

        if ((int)($user['face_biometric_enabled'] ?? 0) !== 1 || empty($user['face_descriptor'])) {
            Response::error('Facial login is not enrolled for this account', 403);
        }

        $storedDescriptor = normalizeDescriptor(json_decode((string)$user['face_descriptor'], true));
        if ($storedDescriptor === null) {
            AuditLogger::log('face_login_failed', 'user', (int)$user['id'], 'Face descriptor data is invalid for account');
            Response::error('Facial profile is invalid. Please re-enroll face data.', 500);
        }

        $distance = euclideanDistance($descriptor, $storedDescriptor);
        if (!is_finite($distance) || $distance > $verificationThreshold) {
            logFaceEvent($db, (int)$user['id'], false, $distance, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
            AuditLogger::log('face_login_failed', 'user', (int)$user['id'], 'Face login failed: descriptor mismatch');
            Response::error('Face does not match enrolled profile', 401);
        }

        // Guardrail: if another enrolled account is clearly closer than the claimed username, reject login.
        $rolePlaceholders = implode(',', array_fill(0, count($allowedRoles), '?'));
        $otherStmt = $db->prepare("
            SELECT id, face_descriptor
            FROM users
            WHERE is_active = 1
              AND face_biometric_enabled = 1
              AND face_descriptor IS NOT NULL
              AND role IN ($rolePlaceholders)
              AND id <> ?
        ");
        foreach ($allowedRoles as $idx => $role) {
            $otherStmt->bindValue($idx + 1, $role);
        }
        $otherStmt->bindValue(count($allowedRoles) + 1, (int)$user['id'], PDO::PARAM_INT);
        $otherStmt->execute();
        $otherCandidates = $otherStmt->fetchAll(PDO::FETCH_ASSOC);

        $closestOtherDistance = INF;
        foreach ($otherCandidates as $candidate) {
            $candidateDescriptor = normalizeDescriptor(json_decode((string)$candidate['face_descriptor'], true));
            if ($candidateDescriptor === null) {
                continue;
            }

            $candidateDistance = euclideanDistance($descriptor, $candidateDescriptor);
            if ($candidateDistance < $closestOtherDistance) {
                $closestOtherDistance = $candidateDistance;
            }
        }

        if (is_finite($closestOtherDistance)) {
            if (($closestOtherDistance + $crossUserBetterDelta) < $distance) {
                logFaceEvent($db, (int)$user['id'], false, $distance, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
                AuditLogger::log(
                    'face_login_failed',
                    'user',
                    (int)$user['id'],
                    'Face login failed: scanned face appears to match another enrolled account better'
                );
                Response::error('Scanned face appears to match another account. Use the correct username or retry.', 401);
            }

            if (($closestOtherDistance - $distance) < $ambiguityDelta) {
                logFaceEvent($db, (int)$user['id'], false, $distance, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
                AuditLogger::log('face_login_failed', 'user', (int)$user['id'], 'Face login failed: ambiguous proximity to another enrolled account');
                Response::error('Face match is too close to another enrolled account. Retry in better lighting.', 401);
            }
        }
    } else {
        $rolePlaceholders = implode(',', array_fill(0, count($allowedRoles), '?'));
        $candidateStmt = $db->prepare("
            SELECT id, username, email, role, full_name, is_active, password_hash, face_descriptor, face_biometric_enabled
            FROM users
            WHERE is_active = 1
              AND face_biometric_enabled = 1
              AND face_descriptor IS NOT NULL
              AND role IN ($rolePlaceholders)
        ");
        foreach ($allowedRoles as $idx => $role) {
            $candidateStmt->bindValue($idx + 1, $role);
        }
        $candidateStmt->execute();
        $candidates = $candidateStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$candidates) {
            Response::error('No enrolled face profiles are available for auto face login.', 403);
        }

        $bestUser = null;
        $bestDistance = INF;
        $secondBestDistance = INF;

        foreach ($candidates as $candidate) {
            $candidateDescriptor = normalizeDescriptor(json_decode((string)$candidate['face_descriptor'], true));
            if ($candidateDescriptor === null) {
                continue;
            }

            $candidateDistance = euclideanDistance($descriptor, $candidateDescriptor);

            if ($candidateDistance < $bestDistance) {
                $secondBestDistance = $bestDistance;
                $bestDistance = $candidateDistance;
                $bestUser = $candidate;
            } elseif ($candidateDistance < $secondBestDistance) {
                $secondBestDistance = $candidateDistance;
            }
        }

        if (!$bestUser || !is_finite($bestDistance)) {
            Response::error('No valid face profiles are available for auto face login.', 403);
        }

        if ($bestDistance > $autoThreshold) {
            AuditLogger::log('face_login_failed', 'user', null, 'Auto face login failed: no enrolled profile matched threshold');
            Response::error('Face not recognized. Please retry with better lighting and framing.', 401);
        }

        if (is_finite($secondBestDistance) && ($secondBestDistance - $bestDistance) < $ambiguityDelta) {
            AuditLogger::log('face_login_failed', 'user', (int)$bestUser['id'], 'Auto face login failed: ambiguous face match');
            Response::error('Face match is ambiguous. Keep centered and retry once.', 401);
        }

        $user = $bestUser;
        $distance = $bestDistance;
    }

    Auth::loginWithRemember($user, false);
    logFaceEvent($db, (int)$user['id'], true, $distance, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);
    AuditLogger::log('face_login', 'user', (int)$user['id'], $mode === 'auto' ? 'Face login successful (auto identified)' : 'Face login successful');

    Response::success([
        'user' => array_intersect_key($user, array_flip(['id', 'username', 'email', 'role', 'full_name'])),
        'session_id' => session_id(),
        'matched_by' => $mode,
        'match_distance' => $distance
    ], 'Face login successful');
} catch (Exception $e) {
    error_log('facial-login error: ' . $e->getMessage());
    Response::serverError('An error occurred during face login');
}

function normalizeDescriptor($descriptor) {
    if (!is_array($descriptor) || count($descriptor) !== 128) {
        return null;
    }

    $normalized = [];
    $sumSquares = 0.0;
    foreach ($descriptor as $value) {
        $floatValue = (float)$value;
        if (!is_finite($floatValue)) {
            return null;
        }
        $normalized[] = $floatValue;
        $sumSquares += $floatValue * $floatValue;
    }

    if ($sumSquares <= 0.0) {
        return null;
    }

    $magnitude = sqrt($sumSquares);
    foreach ($normalized as $idx => $value) {
        $normalized[$idx] = $value / $magnitude;
    }

    return $normalized;
}

function euclideanDistance($a, $b) {
    if (!is_array($a) || !is_array($b) || count($a) !== count($b) || !count($a)) {
        return INF;
    }

    $sum = 0.0;
    $length = count($a);
    for ($i = 0; $i < $length; $i++) {
        $delta = (float)$a[$i] - (float)$b[$i];
        $sum += $delta * $delta;
    }
    return sqrt($sum);
}

function logFaceEvent($db, $userId, $success, $distance, $ipAddress, $userAgent) {
    try {
        $stmt = $db->prepare("
            INSERT INTO face_auth_events (user_id, success, face_distance, ip_address, user_agent, created_at)
            VALUES (:user_id, :success, :face_distance, :ip_address, :user_agent, NOW())
        ");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':success', $success ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':face_distance', $distance);
        $stmt->bindValue(':ip_address', $ipAddress);
        $stmt->bindValue(':user_agent', $userAgent);
        $stmt->execute();
    } catch (Exception $e) {
        error_log('Failed to write face_auth_events: ' . $e->getMessage());
    }
}
