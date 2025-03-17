<?php
// Define log file location
$logFile = '/var/www/html/whois_debug.log';

// Debug logging function
function debugLog($message) {
    global $logFile;
    $timestamp = microtime(true);
    $formattedMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
}

// Start script
debugLog("Script started");

// Check for GET parameters
if (!isset($_GET['domain']) || !isset($_GET['h-captcha-response'])) {
    debugLog("Error: Missing domain or hCaptcha response");
    http_response_code(400);
    echo json_encode(['error' => 'Missing domain or hCaptcha response']);
    exit;
}

$domain = htmlspecialchars($_GET['domain']);
$hCaptchaResponse = $_GET['h-captcha-response'];
debugLog("GET parameters received: domain=$domain, hCaptcha response length=" . strlen($hCaptchaResponse));

// Include hCaptcha utility functions
require_once '/api/hcaptcha-utils.php';

debugLog("Calling validateHcaptcha");
if (!validateHcaptcha($hCaptchaResponse)) {
    debugLog("Error: hCaptcha validation failed");
    http_response_code(403);
    echo json_encode(['error' => 'hCaptcha validation failed']);
    exit;
}
debugLog("hCaptcha validation result: success");

// Perform WHOIS lookup (simplified example using a WHOIS server)
$whoisServer = "whois.verisign-grs.com"; // Example for .com domains
$port = 43;
$fp = fsockopen($whoisServer, $port, $errno, $errstr, 10);
if (!$fp) {
    debugLog("Error: WHOIS connection failed - $errstr ($errno)");
    http_response_code(500);
    echo json_encode(['error' => 'WHOIS lookup failed']);
    exit;
}

fputs($fp, "$domain\r\n");
$whoisData = '';
$startTime = microtime(true);
while (!feof($fp)) {
    $whoisData .= fgets($fp, 128);
}
fclose($fp);
$whoisTime = (microtime(true) - $startTime) * 1000; // Time in milliseconds

debugLog("WHOIS time: " . number_format($whoisTime, 2) . " ms");
debugLog("WHOIS data received: " . substr($whoisData, 0, 100) . "..."); // Log first 100 chars

// Calculate total time
$totalTime = (microtime(true) - $startTime) * 1000;
debugLog("Total time: " . number_format($totalTime, 2) . " ms");

// Return response
header('Content-Type: application/json');
echo json_encode([
    'domain' => $domain,
    'whois' => $whoisData,
    'whois_time_ms' => $whoisTime,
    'total_time_ms' => $totalTime
]);
?>