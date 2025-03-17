<?php
require_once __DIR__ . '/utils.php'; // Include utils.php for debugLog()

// Start script
$scriptStartTime = microtime(true);
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
require_once __DIR__ . '/hcaptcha-utils.php';

debugLog("Calling validateHcaptcha");
if (!validateHcaptcha($hCaptchaResponse)) {
    debugLog("Error: hCaptcha validation failed");
    http_response_code(403);
    echo json_encode(['error' => 'hCaptcha validation failed']);
    exit;
}
debugLog("hCaptcha validation result: success");

// Function to get WHOIS server based on TLD
function getWhoisServer($domain) {
    $tld = strtolower(substr(strrchr($domain, '.'), 1));
    $whoisServers = [
        'com' => 'whois.verisign-grs.com',
        'net' => 'whois.verisign-grs.com',
        'edu' => 'whois.verisign-grs.com',
        'org' => 'whois.pir.org',
        'me' => 'whois.nic.me',
        'co' => 'whois.nic.co',
        'io' => 'whois.nic.io',
        // Add more TLDs as needed
        'default' => 'whois.iana.org' // Fallback for unknown TLDs
    ];
    return $whoisServers[$tld] ?? $whoisServers['default'];
}

// Function to perform WHOIS lookup
function performWhoisLookup($domain, $server, &$whoisTime) {
    $port = 43;
    $fp = @fsockopen($server, $port, $errno, $errstr, 10);
    if (!$fp) {
        debugLog("Error: WHOIS connection failed to $server - $errstr ($errno)");
        return false;
    }

    fputs($fp, "$domain\r\n");
    $whoisData = '';
    $startTime = microtime(true);
    while (!feof($fp)) {
        $whoisData .= fgets($fp, 128);
    }
    fclose($fp);
    $whoisTime = (microtime(true) - $startTime) * 1000;

    debugLog("WHOIS time for $server: " . number_format($whoisTime, 2) . " ms");
    debugLog("WHOIS data received from $server: " . substr($whoisData, 0, 100) . "...");
    return $whoisData;
}

// Perform initial WHOIS lookup
$whoisServer = getWhoisServer($domain);
debugLog("Initial WHOIS server: $whoisServer");
$whoisData = performWhoisLookup($domain, $whoisServer, $whoisTime);

if ($whoisData === false) {
    http_response_code(500);
    echo json_encode(['error' => "WHOIS lookup failed for server: $whoisServer"]);
    exit;
}

// Check for referral to registrar's WHOIS server
if (preg_match('/Registrar WHOIS Server: (.+)/i', $whoisData, $match)) {
    $registrarWhois = trim($match[1]);
    if ($registrarWhois && $registrarWhois !== $whoisServer) {
        debugLog("Referral detected, querying: $registrarWhois");
        $referralData = performWhoisLookup($domain, $registrarWhois, $referralTime);
        if ($referralData !== false) {
            $whoisData = $referralData; // Use referral data if successful
            $whoisTime = $referralTime; // Update time to reflect referral lookup
        } else {
            debugLog("Referral lookup failed, sticking with initial data");
        }
    }
}

// Calculate total time
$totalTime = (microtime(true) - $scriptStartTime) * 1000;
debugLog("Total time: " . number_format($totalTime, 2) . " ms");

// Return response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'domain' => $domain,
    'whois' => trim($whoisData),
    'whois_time_ms' => $whoisTime,
    'total_time_ms' => $totalTime
]);
?>