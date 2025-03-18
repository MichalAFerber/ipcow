<?php
require_once __DIR__ . '/utils.php'; // Include utils.php for debugLog()
require_once __DIR__ . '/hcaptcha-utils.php'; // Include hCaptcha utility functions

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

debugLog("Calling validateHcaptcha");
if (!validateHcaptcha($hCaptchaResponse)) {
    debugLog("Error: hCaptcha validation failed");
    http_response_code(403);
    echo json_encode(['error' => 'hCaptcha validation failed']);
    exit;
}
debugLog("hCaptcha validation result: success");

// Function to fetch and cache IANA RDAP data
function getIanaRdapData() {
    static $rdapData = null;
    $cacheFile = __DIR__ . '/iana-rdap-cache.json';
    $cacheDuration = 86400; // 24 hours in seconds

    if ($rdapData === null) {
        if (file_exists($cacheFile) && (filemtime($cacheFile) > (time() - $cacheDuration))) {
            $rdapData = json_decode(file_get_contents($cacheFile), true);
            debugLog("Loaded RDAP data from cache: $cacheFile");
        } else {
            $ianaUrl = 'https://data.iana.org/rdap/dns.json';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $ianaUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MyWHOISApp/1.0');
            $response = curl_exec($ch);
            if (curl_errno($ch)) {
                debugLog("Error fetching IANA RDAP data: " . curl_error($ch) . ", HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE));
                $rdapData = [];
            } else {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode >= 400) {
                    debugLog("Error: IANA fetch returned HTTP $httpCode, Response: " . substr($response, 0, 200));
                    $rdapData = [];
                } else {
                    $rdapData = json_decode($response, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        debugLog("Error parsing IANA RDAP data: " . json_last_error_msg() . ", Raw response: " . substr($response, 0, 200));
                        $rdapData = [];
                    } else {
                        if (!is_writable($cacheFile)) {
                            debugLog("Warning: Cache file $cacheFile is not writable");
                        } else {
                            file_put_contents($cacheFile, $response);
                            debugLog("Fetched and cached IANA RDAP data from $ianaUrl");
                        }
                    }
                }
            }
            curl_close($ch);
        }
    }
    return $rdapData ?: [];
}

// Function to get RDAP server based on TLD using IANA data
function getRdapServer($domain, $ianaRdapData) {
    $tld = strtolower(substr(strrchr($domain, '.'), 1));
    debugLog("Extracted TLD: $tld");

    // Manual fallback for testing common TLDs
    $manualServers = [
        'com' => 'https://rdap.verisign.com/com/v1/',
        'net' => 'https://rdap.verisign.com/net/v1/',
        'org' => 'https://rdap.publicinterestregistry.net/rdap/org/',
        'me' => 'https://rdap.nic.me/',
        'us' => 'https://rdap.usnic.net/',
        'xyz' => 'https://rdap.donuts.co/'
    ];
    if (isset($manualServers[$tld])) {
        $rdapUrl = $manualServers[$tld];
        debugLog("Using manual RDAP server for TLD '$tld': $rdapUrl");
        return rtrim($rdapUrl, '/') . '/domain/' . urlencode($domain);
    }

    foreach ($ianaRdapData['services'] ?? [] as $service) {
        if (isset($service['ldhName']) && $service['ldhName'] === $tld && isset($service['rdapUrl']) && is_array($service['rdapUrl']) && !empty($service['rdapUrl'])) {
            $rdapUrl = $service['rdapUrl'][0];
            debugLog("Found RDAP server for TLD '$tld': $rdapUrl");
            return rtrim($rdapUrl, '/') . '/domain/' . urlencode($domain);
        }
    }

    // Fallback to IANA bootstrap service
    $defaultServer = 'https://rdap.iana.org/domain/' . urlencode($domain);
    debugLog("No RDAP server found for TLD '$tld', using default: $defaultServer");
    return $defaultServer;
}

// Function to perform RDAP lookup
function performRdapLookup($domain, $server, &$rdapTime) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $server);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30-second timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Ensure SSL verification
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/rdap+json', 'User-Agent: MyWHOISApp/1.0']); // Add User-Agent
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_FAILONERROR, false); // Allow error codes to be caught

    $startTime = microtime(true);
    $rdapData = curl_exec($ch);
    $rdapTime = (microtime(true) - $startTime) * 1000;

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    debugLog("RDAP request - Server: $server, Effective URL: $effectiveUrl, HTTP Code: $httpCode, Error: $error");
    if (curl_errno($ch) || $httpCode >= 400) {
        debugLog("Error: RDAP request failed - HTTP $httpCode, Error: $error, Data: " . ($rdapData ?: 'N/A'));
        return false;
    }

    debugLog("RDAP time: " . number_format($rdapTime, 2) . " ms");
    debugLog("RDAP data received: " . substr($rdapData, 0, 200) . "...");
    return $rdapData;
}

// Fetch IANA RDAP data
$ianaRdapData = getIanaRdapData();

// Perform RDAP lookup
$rdapServer = getRdapServer($domain, $ianaRdapData);
debugLog("RDAP server: $rdapServer");
$rdapData = performRdapLookup($domain, $rdapServer, $rdapTime);

if ($rdapData === false) {
    debugLog("RDAP lookup failed, returning null rdap data");
    $rdapData = null;
} else {
    // Check for RDAP error responses (e.g., 404, 500, or error notices)
    $jsonData = json_decode($rdapData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugLog("Error parsing RDAP JSON: " . json_last_error_msg() . ", Raw response: " . substr($rdapData, 0, 200));
        $rdapData = null;
    } elseif (isset($jsonData['errorCode'])) {
        debugLog("RDAP response contains error code: " . $jsonData['errorCode'] . ", Description: " . ($jsonData['description'] ?? 'N/A'));
        $rdapData = null;
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
    'whois' => $rdapData ? trim($rdapData) : null, // Reuse 'whois' key for compatibility
    'whois_time_ms' => $rdapTime ?? 0,
    'total_time_ms' => $totalTime
]);
?>