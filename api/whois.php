<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration
const DEFAULT_WHOIS_SERVER = 'whois.iana.org';
const DEFAULT_PORT = 43;
const MAX_REFERRAL_DEPTH = 3; // Prevent infinite loops
const TIMEOUT = 10; // Socket timeout in seconds

// Validate domain
$domain = isset($_GET['domain']) ? trim($_GET['domain']) : '';
if (empty($domain) || !preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain)) {
    echo json_encode(['success' => false, 'error' => 'Invalid domain']);
    exit;
}

// Validate hCaptcha response
$hcaptcha_response = isset($_GET['h-captcha-response']) ? $_GET['h-captcha-response'] : '';
$secret_key = 'YOUR_HCAPTCHA_SECRET_KEY'; // Replace with your hCaptcha secret key
if (empty($hcaptcha_response)) {
    echo json_encode(['success' => false, 'error' => 'hCaptcha response missing']);
    exit;
}

$hcaptcha_verify_url = 'https://hcaptcha.com/siteverify';
$hcaptcha_data = [
    'secret' => $secret_key,
    'response' => $hcaptcha_response,
    'remoteip' => $_SERVER['REMOTE_ADDR']
];

$hcaptcha_response = file_get_contents($hcaptcha_verify_url . '?' . http_build_query($hcaptcha_data));
$hcaptcha_result = json_decode($hcaptcha_response, true);

if (!$hcaptcha_result['success']) {
    echo json_encode(['success' => false, 'error' => 'hCaptcha verification failed']);
    exit;
}

// Function to perform a WHOIS query
function queryWhois($domain, $server, $port = DEFAULT_PORT) {
    $start_time = microtime(true);

    $socket = @fsockopen($server, $port, $errno, $errstr, TIMEOUT);
    if (!$socket) {
        return ['success' => false, 'error' => "Cannot connect to WHOIS server $server: $errstr ($errno)"];
    }

    // Send the WHOIS query
    fwrite($socket, "$domain\r\n");

    // Read the response
    $response = '';
    while (!feof($socket)) {
        $response .= fgets($socket, 128);
    }
    fclose($socket);

    $end_time = microtime(true);
    $duration_ms = ($end_time - $start_time) * 1000;

    return ['success' => true, 'data' => $response, 'time_ms' => $duration_ms];
}

// Function to parse WHOIS response for a refer field
function parseRefer($whois_data) {
    $lines = explode("\n", $whois_data);
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^refer:\s*([^\s]+)/i', $line, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

// Recursive WHOIS lookup function
function recursiveWhois($domain, $server = DEFAULT_WHOIS_SERVER, $depth = 0) {
    if ($depth > MAX_REFERRAL_DEPTH) {
        return ['success' => false, 'error' => 'Maximum referral depth reached', 'time_ms' => 0];
    }

    // Query the current WHOIS server
    $result = queryWhois($domain, $server);
    if (!$result['success']) {
        return $result;
    }

    $whois_data = $result['data'];
    $current_time_ms = $result['time_ms'];

    // Look for a refer field in the response
    $refer_server = parseRefer($whois_data);
    if ($refer_server) {
        // Follow the referral
        $refer_result = recursiveWhois($domain, $refer_server, $depth + 1);
        if ($refer_result['success']) {
            // Combine the timing from the current query and the referred query
            $refer_result['time_ms'] += $current_time_ms;
            return $refer_result;
        } else {
            // If the referral fails, return the current WHOIS data as a fallback
            return ['success' => true, 'data' => $whois_data, 'time_ms' => $current_time_ms];
        }
    }

    // No referral found, return the current WHOIS data
    return ['success' => true, 'data' => $whois_data, 'time_ms' => $current_time_ms];
}

// Main execution
$total_start_time = microtime(true);

// Perform the recursive WHOIS lookup
$result = recursiveWhois($domain);

$total_end_time = microtime(true);
$total_time_ms = ($total_end_time - $total_start_time) * 1000;

if (!$result['success']) {
    echo json_encode([
        'success' => false,
        'error' => $result['error'],
        'total_time_ms' => $total_time_ms
    ]);
    exit;
}

// Return the final WHOIS data
echo json_encode([
    'success' => true,
    'domain' => $domain,
    'whois' => $result['data'],
    'whois_time_ms' => $result['time_ms'],
    'total_time_ms' => $total_time_ms
]);
?>