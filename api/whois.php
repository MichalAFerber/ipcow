<?php
$logFile = '/var/www/html/whois_debug.log';

function debugLog($message) {
    global $logFile;
    $timestamp = microtime(true);
    $formattedMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
}

debugLog("Script started");

header('Content-Type: application/json');

$hCaptchaResponse = $_GET['h-captcha-response'] ?? '';
$domain = $_GET['domain'] ?? '';
debugLog("GET parameters received");

if (empty($domain)) {
    debugLog("Error: No domain provided");
    http_response_code(400);
    echo json_encode(['error' => 'No domain provided']);
    exit;
}

debugLog("Received domain: $domain");
debugLog("Received hCaptcha response: " . substr($hCaptchaResponse, 0, 50) . "...");

$startTime = microtime(true);
debugLog("Calling validateHcaptcha");
$hCaptchaResult = validateHcaptcha($hCaptchaResponse);
debugLog("hCaptcha validation result: " . json_encode($hCaptchaResult));

if (!$hCaptchaResult['success']) {
    debugLog("Error: hCaptcha verification failed: " . json_encode($hCaptchaResult['error-codes'] ?? 'Unknown error'));
    http_response_code(400);
    echo json_encode(['error' => 'hCaptcha verification failed']);
    exit;
}

$whoisStart = microtime(true);
debugLog("About to run WHOIS for '$domain'");
$whoisOutput = shell_exec("whois '$domain' 2>/dev/null");
$whoisTime = (microtime(true) - $whoisStart) * 1000;

if ($whoisOutput === null) {
    debugLog("WHOIS lookup failed for '$domain': No output returned");
    http_response_code(500);
    echo json_encode(['error' => "WHOIS lookup failed for $domain. Please try again later."]);
    exit;
}

debugLog("WHOIS time: $whoisTime ms");
$totalTime = (microtime(true) - $startTime) * 1000;
debugLog("Total time: $totalTime ms");

echo json_encode(['whois' => $whoisOutput]);
?>