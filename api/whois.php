<?php
// /home/appleseed/ipcow/core-dev/api/whois.php
require_once __DIR__ . '/hcaptcha-utils.php';

function debugLog($message) {
    $logFile = '/var/www/html/whois_debug.log';
    $timestamp = microtime(true);
    $date = date('Y-m-d H:i:s', (int)$timestamp);
    $micro = sprintf("%06d", ($timestamp - floor($timestamp)) * 1000000);
    $result = @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    if ($result === false) {
        error_log("Failed to write to $logFile: $message");
    }
}

$startTime = microtime(true);
debugLog("Script started");

$hcaptchaUtilsPath = __DIR__ . '/hcaptcha-utils.php';
debugLog("Checking hCaptcha utils path: $hcaptchaUtilsPath");
if (!file_exists($hcaptchaUtilsPath)) {
    debugLog("Error: hCaptcha utils file not found: $hcaptchaUtilsPath");
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
    exit;
}
debugLog("File exists: Yes");

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['domain']) && isset($_GET['h-captcha-response'])) {
    $domain = trim($_GET['domain']);
    $captchaResponse = $_GET['h-captcha-response'];

    debugLog("Received domain: $domain");
    debugLog("Received hCaptcha response: " . substr($captchaResponse, 0, 50) . "..."); // Truncate for brevity

    if (empty($domain) || empty($captchaResponse)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing domain or captcha response']);
        exit;
    }

    $validationResult = validateHcaptcha($captchaResponse);
    debugLog("hCaptcha validation result: " . json_encode($validationResult));

    if (!$validationResult['success']) {
        http_response_code(400);
        if ($validationResult['error'] === 'hCaptcha verification failed: already-seen-response') {
            debugLog("Warning: hCaptcha response reused, prompting new challenge.");
            echo json_encode(['success' => false, 'error' => 'Please complete a new hCaptcha challenge.']);
        } else {
            echo json_encode(['success' => false, 'error' => $validationResult['error']]);
        }
        exit;
    }

    $domain = escapeshellarg($domain);
    $whoisStartTime = microtime(true);
    $whoisCommand = "whois $domain 2>/dev/null";
    debugLog("Executing WHOIS command: $whoisCommand");
    $whoisOutput = shell_exec($whoisCommand);
    $whoisTime = (microtime(true) - $whoisStartTime) * 1000;

    if ($whoisOutput === null) {
        debugLog("WHOIS lookup failed for $domain: No output returned");
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "WHOIS lookup failed for $domain. Please try again later."]);
        exit;
    }

    $whoisData = [];
    $lines = explode("\n", trim($whoisOutput));
    foreach ($lines as $line) {
        if (preg_match('/^([^:]+):[ \t]*(.+)$/', $line, $matches)) {
            $key = trim($matches[1]);
            $value = trim($matches[2]);
            $whoisData[$key] = $value;
        }
    }

    if (empty($whoisData)) {
        debugLog("WHOIS parsing failed for $domain: No valid data found");
        echo json_encode(['success' => true, 'domain' => trim($domain, "'"), 'whois' => [], 'execution_time' => ['whois' => $whoisTime, 'total' => (microtime(true) - $startTime) * 1000]]);
        exit;
    }

    $totalTime = (microtime(true) - $startTime) * 1000;
    debugLog("WHOIS time: $whoisTime ms");
    debugLog("Total time: $totalTime ms");

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'domain' => trim($domain, "'"),
        'whois' => $whoisData,
        'execution_time' => [
            'whois' => $whoisTime,
            'total' => $totalTime
        ]
    ]);
    exit;
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request method or parameters']);
    exit;
}