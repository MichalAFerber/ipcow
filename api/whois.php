<?php
require_once '/var/www/config/config.php';
require_once '/api/hcaptcha-utils.php'; // Updated path

header('Content-Type: application/json');
$response = ['success' => false, 'whois' => [], 'error' => '', 'available' => false];

$logPath = '/var/www/html/whois_debug.log';
$startTime = microtime(true);

// Enable error logging for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/php_errors.log'); // Temporary error log

$hcaptchaResponse = $_GET['h-captcha-response'] ?? '';
$validationResult = validateHcaptcha($hcaptchaResponse);
if (!$validationResult['success']) {
    $response['error'] = $validationResult['error'];
    echo json_encode($response);
    exit;
}

$domain = $_GET['domain'] ?? '';
file_put_contents($logPath, "[$startTime] Received domain: $domain\n", FILE_APPEND | LOCK_EX);

if ($domain && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    $whoisStart = microtime(true);
    $whoisOutput = shell_exec("whois " . escapeshellarg($domain));
    $whoisEnd = microtime(true);
    file_put_contents($logPath, "[$startTime] WHOIS time: " . (($whoisEnd - $whoisStart) * 1000) . " ms\n", FILE_APPEND | LOCK_EX);

    if ($whoisOutput === null || trim($whoisOutput) === '') {
        $response['error'] = "No WHOIS data found for $domain or query failed.";
    } else {
        $whoisOutputLower = strtolower($whoisOutput);
        if (strpos($whoisOutputLower, 'no match') !== false || 
            strpos($whoisOutputLower, 'not found') !== false || 
            strpos($whoisOutputLower, 'no entries found') !== false) {
            $response['success'] = true;
            $response['available'] = true;
        } else {
            $whoisData = [];
            $lines = explode("\n", trim($whoisOutput));
            foreach ($lines as $line) {
                if (empty($line) || str_starts_with($line, '#') || str_starts_with($line, '%') || str_starts_with($line, '>>>') || str_starts_with($line, 'NOTICE:')) {
                    continue;
                }
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    if (!empty($key) && !empty($value)) {
                        if (isset($whoisData[$key])) {
                            if (!is_array($whoisData[$key])) {
                                $whoisData[$key] = [$whoisData[$key]];
                            }
                            $whoisData[$key][] = $value;
                        } else {
                            $whoisData[$key] = $value;
                        }
                    }
                }
            }
            if (!empty($whoisData)) {
                $response['success'] = true;
                $response['whois'] = $whoisData;
            } else {
                $response['error'] = "Could not parse WHOIS data for $domain.";
            }
        }
    }
} else {
    $response['error'] = "Invalid domain.";
}

$totalTime = (microtime(true) - $startTime) * 1000;
file_put_contents($logPath, "[$startTime] Total time: $totalTime ms\n", FILE_APPEND | LOCK_EX);
echo json_encode($response);
?>