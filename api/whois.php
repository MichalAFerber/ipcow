<?php
// Enable error reporting for debugging
ini_set('display_errors', 0); // Do not display errors to the user
ini_set('log_errors', 1); // Log errors
ini_set('error_log', '/var/log/php_errors.log'); // Specify the error log file (adjust path as needed)
error_reporting(E_ALL);

// Debug logging function
function debugLog($message) {
    $logFile = __DIR__ . '/whois_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    // Use error_log to avoid file permission issues
    error_log($logMessage, 3, $logFile);
}

// Function to validate hCaptcha
function validateHCaptcha($response) {
    $secret = 'ES_1eb25e26-63d0-476a-bcb6-ae62a2b04752'; // Replace with your hCaptcha secret key
    $url = 'https://hcaptcha.com/siteverify';
    $data = [
        'secret' => $secret,
        'response' => $response
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10 // Set a timeout for the request
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        debugLog("hCaptcha verification failed: Unable to connect to hCaptcha server");
        return false;
    }

    $resultData = json_decode($result, true);
    return isset($resultData['success']) && $resultData['success'] === true;
}

// Function to perform traditional WHOIS lookup as a fallback
function performWhoisFallback($domain, &$whoisTime) {
    try {
        $tld = strtolower(substr(strrchr($domain, '.'), 1));
        debugLog("Attempting WHOIS fallback for domain: $domain, TLD: $tld");

        $whoisServers = [
            'me' => 'whois.nic.me',
            'xyz' => 'whois.nic.xyz',
            'io' => 'whois.nic.io',
            'co' => 'whois.nic.co',
            'cc' => 'ccwhois.verisign-grs.com',
            'tv' => 'tvwhois.verisign-grs.com',
            'us' => 'whois.nic.us',
            'biz' => 'whois.neulevel.biz',
            'mobi' => 'whois.dotmobiregistry.net',
            'pro' => 'whois.registrypro.pro',
            'info' => 'whois.afilias.net',
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
    } catch (Exception $e) {
        debugLog("Error in performWhoisFallback: " . $e->getMessage());
        return null;
    }
}

// Main script
try {
    $startTime = microtime(true);

    // Validate input
    if (!isset($_GET['domain']) || empty(trim($_GET['domain']))) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Domain parameter is required']);
        exit;
    }

    if (!isset($_GET['h-captcha-response']) || empty(trim($_GET['h-captcha-response']))) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'hCaptcha response is required']);
        exit;
    }

    $domain = strtolower(trim($_GET['domain']));
    $hCaptchaResponse = $_GET['h-captcha-response'];

    // Validate hCaptcha
    if (!validateHCaptcha($hCaptchaResponse)) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'hCaptcha validation failed']);
        exit;
    }

    // Perform WHOIS lookup (simplified for this example; add RDAP logic as needed)
    $whoisTime = 0;
    $rdapData = performWhoisFallback($domain, $whoisTime); // For now, just use WHOIS fallback

    $totalTime = (microtime(true) - $startTime) * 1000;

    // Return response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'domain' => $domain,
        'whois' => $rdapData ? $rdapData : null,
        'whois_time_ms' => $whoisTime ?? 0,
        'total_time_ms' => $totalTime
    ]);
} catch (Exception $e) {
    debugLog("Error in /api/whois.php: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
    exit;
}