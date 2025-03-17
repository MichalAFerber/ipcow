<?php
// /home/appleseed/ipcow/core-dev/api/whois.php

// Include the hCaptcha utilities
require_once __DIR__ . '/hcaptcha-utils.php';

// Debug log function
function debugLog($message) {
    $logFile = '/var/www/html/whois_debug.log';
    $timestamp = microtime(true);
    $date = date('Y-m-d H:i:s', (int)$timestamp);
    $micro = sprintf("%06d", ($timestamp - floor($timestamp)) * 1000000);
    @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// Start script
$startTime = microtime(true);
debugLog("Script started");

// Check if hcaptcha-utils.php exists
$hcaptchaUtilsPath = __DIR__ . '/hcaptcha-utils.php';
debugLog("Checking hCaptcha utils path: $hcaptchaUtilsPath");
if (!file_exists($hcaptchaUtilsPath)) {
    debugLog("Error: hCaptcha utils file not found: $hcaptchaUtilsPath");
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}
debugLog("File exists: Yes");

// Process WHOIS request
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['domain']) && isset($_GET['h-captcha-response'])) {
    $domain = trim($_GET['domain']);
    $captchaResponse = $_GET['h-captcha-response'];

    debugLog("Received domain: $domain");
    debugLog("Received hCaptcha response: $captchaResponse");

    if (empty($domain) || empty($captchaResponse)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing domain or captcha response']);
        exit;
    }

    // Validate hCaptcha
    $validationResult = validateHcaptcha($captchaResponse);
    debugLog("hCaptcha validation result: " . json_encode($validationResult));

    if (!$validationResult['success']) {
        if (isset($validationResult['error']) && $validationResult['error'] === 'hCaptcha verification failed: already-seen-response') {
            debugLog("[$startTime] Warning: hCaptcha response reused, prompting new challenge.");
            http_response_code(400);
            echo json_encode(['error' => 'Please complete a new hCaptcha challenge.']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $validationResult['error']]);
        }
        exit;
    }

    // Sanitize domain to prevent command injection
    $domain = escapeshellarg($domain);
    $whoisStartTime = microtime(true);
    $whoisCommand = "whois $domain 2>/dev/null";
    $whoisOutput = shell_exec($whoisCommand);
    $whoisTime = (microtime(true) - $whoisStartTime) * 1000; // Convert to milliseconds

    if ($whoisOutput === null) {
        http_response_code(500);
        echo json_encode(['error' => 'WHOIS lookup failed']);
        exit;
    }

    // Parse WHOIS output into an array
    $whoisData = [];
    $lines = explode("\n", trim($whoisOutput));
    foreach ($lines as $line) {
        if (preg_match('/^([^:]+):[ \t]*(.+)$/', $line, $matches)) {
            $key = trim($matches[1]);
            $value = trim($matches[2]);
            $whoisData[$key] = $value;
        }
    }

    // Calculate total time
    $totalTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
    debugLog("WHOIS time: $whoisTime ms");
    debugLog("Total time: $totalTime ms");

    // Return WHOIS data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'domain' => $domain,
        'whois' => $whoisData,
        'execution_time' => [
            'whois' => $whoisTime,
            'total' => $totalTime
        ]
    ]);
    exit;
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request method or parameters']);
    exit;
}