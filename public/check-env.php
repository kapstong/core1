<?php
/**
 * Environment Diagnostic Script
 * Check this file on production to see environment detection
 */

header('Content-Type: application/json');

$httpHost = $_SERVER['HTTP_HOST'] ?? 'NOT_SET';
$isLocal = in_array($httpHost, ['localhost', '127.0.0.1', 'localhost:80', 'localhost:8080']);
$basePath = $isLocal ? '/core1' : '';

$diagnostics = [
    'http_host' => $httpHost,
    'is_local_detected' => $isLocal,
    'base_path' => $basePath,
    'expected_base_path' => $isLocal ? '/core1' : '(empty string)',
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'NOT_SET',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'NOT_SET',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'NOT_SET',
];

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>
