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

    debugLog("Checking IANA RDAP cache: $cacheFile, Exists: " . (file_exists($cacheFile) ? 'Yes' : 'No'));
    if ($rdapData === null) {
        if (file_exists($cacheFile) && (filemtime($cacheFile) > (time() - $cacheDuration))) {
            $rdapData = json_decode(file_get_contents($cacheFile), true);
            debugLog("Loaded RDAP data from cache, Size: " . filesize($cacheFile) . " bytes, Last Modified: " . date('Y-m-d H:i:s', filemtime($cacheFile)));
        }
        $ianaUrl = 'https://data.iana.org/rdap/dns.json';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ianaUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MyWHOISApp/1.0 (https://ipcow.com)');
        $response = curl_exec($ch);
        debugLog("IANA fetch completed, HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE));
        if (curl_errno($ch)) {
            debugLog("Error fetching IANA RDAP data: " . curl_error($ch));
            $rdapData = [];
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode >= 400) {
                debugLog("Error: IANA fetch returned HTTP $httpCode, Response: " . substr($response, 0, 200));
                $rdapData = [];
            } else {
                $rdapData = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    debugLog("Error parsing IANA RDAP data: " . json_last_error_msg());
                    $rdapData = [];
                } else {
                    if (!is_writable($cacheFile)) {
                        debugLog("Warning: Cache file not writable, attempting to fix permissions");
                        if (chmod($cacheFile, 0664) === false) {
                            debugLog("Failed to change permissions, manual fix required");
                        } else {
                            debugLog("Permissions fixed to 0664");
                        }
                    }
                    $bytesWritten = file_put_contents($cacheFile, $response);
                    if ($bytesWritten === false) {
                        debugLog("Failed to write to cache file");
                    } else {
                        debugLog("Cached IANA RDAP data, Bytes written: $bytesWritten");
                    }
                }
            }
        }
        curl_close($ch);
    }
    return $rdapData ?: [];
}

// Function to get RDAP server based on TLD using IANA data
function getRdapServer($domain, $ianaRdapData) {
    $tld = strtolower(substr(strrchr($domain, '.'), 1));
    debugLog("Extracted TLD: $tld");

    $manualServers = [
        'com' => 'https://rdap.verisign.com/com/v1/',
        'net' => 'https://rdap.verisign.com/net/v1/',
        'org' => 'https://rdap.publicinterestregistry.net/rdap/org/',
        'xyz' => 'https://rdap.centralnic.com/xyz/',
        'us' => 'https://rdap.nic.us/',
        'co' => 'https://rdap.nic.co/',
    ];
    if (isset($manualServers[$tld])) {
        $rdapUrl = $manualServers[$tld];
        debugLog("Using manual RDAP server for TLD '$tld': $rdapUrl");
        return rtrim($rdapUrl, '/') . '/domain/' . urlencode($domain);
    }

    foreach ($ianaRdapData['services'] ?? [] as $service) {
        if (isset($service[0][0]) && $service[0][0] === $tld && isset($service[1][0]) && !empty($service[1][0])) {
            $rdapUrl = $service[1][0];
            debugLog("Found RDAP server for TLD '$tld' in IANA: $rdapUrl");
            return rtrim($rdapUrl, '/') . '/domain/' . urlencode($domain);
        }
    }

    // Fallback for known registrars
    if (preg_match('/cloudflare/i', $domain)) {
        $rdapUrl = 'https://rdap.cloudflare.com/rdap/v1/';
        debugLog("Using Cloudflare RDAP fallback for domain: $rdapUrl");
        return rtrim($rdapUrl, '/') . 'domain/' . urlencode($domain);
    }

    $defaultServer = 'https://rdap.iana.org/domain/' . urlencode($domain);
    debugLog("No RDAP server found for TLD '$tld', using default: $defaultServer");
    return $defaultServer;
}

// Function to perform traditional WHOIS lookup as a fallback
function performWhoisFallback($domain, &$whoisTime) {
    $tld = strtolower(substr(strrchr($domain, '.'), 1));
    debugLog("Attempting WHOIS fallback for domain: $domain, TLD: $tld");

    $whoisServers = [
        'me' => 'whois.nic.me',
        'xyz' => 'whois.nic.xyz',
        'us' => 'whois.nic.us',
        'co' => 'whois.nic.co',
        'io' => 'whois.nic.io',
        'com' => 'whois.verisign-grs.com',
        'net' => 'whois.verisign-grs.com',
        'org' => 'whois.pir.org',
    ];

    if (!isset($whoisServers[$tld])) {
        debugLog("No WHOIS server defined for TLD '$tld'");
        return null;
    }

    $whoisServer = $whoisServers[$tld];
    $startTime = microtime(true);

    $socket = @fsockopen($whoisServer, 43, $errno, $errstr, 10);
    if ($socket === false) {
        debugLog("WHOIS fallback failed: Could not connect to $whoisServer, Error: $errstr ($errno)");
        return null;
    }

    fwrite($socket, "$domain\r\n");
    $response = '';
    while (!feof($socket)) {
        $response .= fgets($socket, 128);
    }
    fclose($socket);

    $whoisTime = (microtime(true) - $startTime) * 1000;
    debugLog("WHOIS fallback time: " . number_format($whoisTime, 2) . " ms");
    debugLog("WHOIS data received: " . substr($response, 0, 200) . "...");

    // Enhanced WHOIS parsing
    $parsedData = ['raw' => trim($response), 'source' => 'whois'];
    $lines = explode("\r\n", $response);
    $currentSection = '';
    $nameServers = []; // To collect multiple Name Server entries

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Handle section headers or multi-line values
        if (preg_match('/^(\w[\w\s]+):$/', $line, $matches)) {
            $currentSection = strtolower(trim($matches[1]));
            $parsedData[$currentSection] = [];
            continue;
        }

        // Parse key-value pairs
        if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
            $key = strtolower(trim($matches[1]));
            $value = trim($matches[2]);
            if ($key === 'registrar') {
                $parsedData['registrar'] = $value;
            } elseif ($key === 'registry expiry date') {
                $parsedData['expiration_date'] = $value;
            } elseif ($key === 'domain name') {
                $parsedData['domain_name'] = $value;
            } elseif ($key === 'name server') {
                $nameServers[] = $value; // Collect all Name Server entries
            } else {
                $parsedData[$key] = $value;
            }
        } elseif ($currentSection && !isset($parsedData[$currentSection])) {
            $parsedData[$currentSection] = $line;
        }
    }

    // Add name servers as an array
    if (!empty($nameServers)) {
        $parsedData['name server'] = $nameServers;
    }

    // Ensure required fields are set
    $parsedData['domain_name'] = $parsedData['domain_name'] ?? $domain;
    $parsedData['registrar'] = $parsedData['registrar'] ?? 'Unknown';
    $parsedData['expiration_date'] = $parsedData['expiration_date'] ?? 'N/A';

    return $parsedData; // Return the parsed data directly as an array
}

// Function to perform RDAP lookup with optimized retries
function performRdapLookup($domain, $rdapServer, &$rdapTime) {
    $maxRetries = 3;
    $retryDelay = 2000; // 2 seconds in milliseconds
    $rdapData = null;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $rdapServer);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/rdap+json',
        'Content-Type: application/json',
        'User-Agent: MyWHOISApp/1.0 (https://ipcow.com)'
    ]);

    $startTime = microtime(true);
    $response = curl_exec($ch);
    $rdapTime = (microtime(true) - $startTime) * 1000;

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    debugLog("RDAP request - Initial attempt, Server: $rdapServer, Effective URL: $effectiveUrl, HTTP Code: $httpCode, Error: $error");
    if ($response && !empty(trim($response))) {
        debugLog("RDAP data received, Length: " . strlen($response));
        $rdapData = $response;
    } else if ($httpCode == 404 || strpos($error, 'Could not resolve host') !== false) {
        debugLog("RDAP failed with 404 or host resolution error, skipping retries");
    } else {
        for ($attempt = 2; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $rdapServer);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/rdap+json',
                'Content-Type: application/json',
                'User-Agent: MyWHOISApp/1.0 (https://ipcow.com)'
            ]);

            $startTime = microtime(true);
            $response = curl_exec($ch);
            $rdapTime = (microtime(true) - $startTime) * 1000;

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            debugLog("RDAP request - Attempt $attempt, Server: $rdapServer, HTTP Code: $httpCode, Error: $error");
            if ($response && !empty(trim($response))) {
                debugLog("RDAP data received on attempt $attempt, Length: " . strlen($response));
                $rdapData = $response;
                break;
            } else {
                debugLog("RDAP attempt $attempt failed, HTTP $httpCode, Error: $error");
                if ($attempt < $maxRetries) {
                    debugLog("Retrying in $retryDelay ms");
                    usleep($retryDelay * 1000);
                }
            }
        }
    }

    if (!$rdapData) {
        debugLog("RDAP lookup failed after initial attempt and retries");
        return null;
    }

    // Validate JSON
    $jsonData = json_decode($rdapData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugLog("Error parsing RDAP JSON: " . json_last_error_msg() . ", Raw response: " . substr($rdapData, 0, 200));
        return null;
    }
    if (isset($jsonData['errorCode'])) {
        debugLog("RDAP response contains error code: " . $jsonData['errorCode'] . ", Description: " . ($jsonData['description'] ?? 'N/A'));
        return null;
    }

    // Add source indicator
    $jsonData['source'] = 'rdap';
    $rdapData = json_encode($jsonData);

    debugLog("RDAP time: " . number_format($rdapTime, 2) . " ms");
    debugLog("RDAP data received: " . substr($rdapData, 0, 200) . "...");
    return $rdapData;
}

// Fetch IANA RDAP data
$ianaRdapData = getIanaRdapData();
debugLog("IANA RDAP data loaded: " . (empty($ianaRdapData) ? 'Empty' : 'Populated'));

// Perform RDAP lookup
$rdapServer = getRdapServer($domain, $ianaRdapData);
debugLog("RDAP server selected: $rdapServer");
$rdapData = performRdapLookup($domain, $rdapServer, $rdapTime);

if ($rdapData === null) {
    debugLog("RDAP lookup failed, attempting WHOIS fallback");
    $whoisData = performWhoisFallback($domain, $whoisTime);
    if ($whoisData) {
        $rdapData = $whoisData;
        $rdapTime = $whoisTime;
        debugLog("WHOIS fallback succeeded");
    } else {
        debugLog("WHOIS fallback failed, returning null data");
    }
} else {
    debugLog("RDAP lookup succeeded");
}

// Calculate total time
$totalTime = (microtime(true) - $scriptStartTime) * 1000;
debugLog("Total time: " . number_format($totalTime, 2) . " ms");

// Return response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'domain' => $domain,
    'whois' => $rdapData ? json_decode(trim($rdapData), true) : null,
    'whois_time_ms' => $rdapTime ?? 0,
    'total_time_ms' => $totalTime
]);