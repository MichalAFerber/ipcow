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
    static $whoisServersCache = null;

    $tld = strtolower(substr(strrchr($domain, '.'), 1));
    debugLog("Extracted TLD: $tld");

    if ($whoisServersCache === null) {
        $whoisConfigFile = __DIR__ . '/whois-servers.json';
        if (!file_exists($whoisConfigFile)) {
            debugLog("Error: WHOIS servers configuration file not found: $whoisConfigFile");
            $whoisServersCache = ['default' => 'whois.iana.org', 'servers' => []];
        } else {
            $whoisConfig = json_decode(file_get_contents($whoisConfigFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                debugLog("Error: Failed to parse WHOIS servers configuration: " . json_last_error_msg());
                $whoisServersCache = ['default' => 'whois.iana.org', 'servers' => []];
            } else {
                $whoisServersCache = $whoisConfig;
            }
        }
        debugLog("Loaded WHOIS servers configuration into cache");
    }

    $defaultServer = $whoisServersCache['default'] ?? 'whois.iana.org';
    $whoisServers = $whoisServersCache['servers'] ?? [];

    foreach ($whoisServers as $server => $tlds) {
        if (in_array($tld, $tlds, true)) {
            debugLog("Found WHOIS server for TLD '$tld': $server");
            return $server;
        }
    }

    debugLog("No WHOIS server found for TLD '$tld', using default: $defaultServer");
    return $defaultServer;
}

// Function to perform WHOIS lookup
function performWhoisLookup($domain, $server, &$whoisTime) {
    $port = 43;
    $fp = @fsockopen($server, $port, $errno, $errstr, 30); // 30-second timeout
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
    debugLog("WHOIS lookup failed, returning null whois data");
    $whoisData = null;
}

// Enhanced referral handling
if ($whoisData && preg_match('/Registrar WHOIS Server: (.+)/i', $whoisData, $match)) {
    $registrarWhois = trim($match[1]);
    if ($registrarWhois && $registrarWhois !== $whoisServer) {
        debugLog("Referral detected, querying: $registrarWhois");
        $referralData = performWhoisLookup($domain, $registrarWhois, $referralTime);
        if ($referralData !== false) {
            $whoisData = $referralData; // Use referral data if successful
            $whoisTime = $referralTime; // Update time to reflect referral lookup
        } else {
            debugLog("Referral lookup failed, sticking with initial data or null");
            $whoisData = $whoisData ?: null; // Fallback to null if referral fails
        }
    }
} elseif ($whoisData && stripos($whoisData, 'No match') !== false) {
    // If "No match" is found and no referral is detected, try a fallback WHOIS server
    $fallbackServers = ['whois.cloudflare.com', 'whois.iana.org'];
    foreach ($fallbackServers as $fallbackServer) {
        if ($fallbackServer !== $whoisServer) {
            debugLog("Trying fallback WHOIS server: $fallbackServer");
            $fallbackData = performWhoisLookup($domain, $fallbackServer, $fallbackTime);
            if ($fallbackData !== false && stripos($fallbackData, 'No match') === false) {
                $whoisData = $fallbackData;
                $whoisTime = $fallbackTime;
                debugLog("Successful fallback to $fallbackServer");
                break;
            }
        }
    }
    if (stripos($whoisData, 'No match') !== false) {
        debugLog("All lookups returned 'No match', setting whois to null");
        $whoisData = null;
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
    'whois' => $whoisData ? trim($whoisData) : null,
    'whois_time_ms' => $whoisTime ?? 0,
    'total_time_ms' => $totalTime
]);
?>