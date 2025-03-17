<?php
// Enable error reporting and logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/php_errors.log');
error_reporting(E_ALL);

// Define log paths
$phpErrorLog = '/var/www/html/php_errors.log';
$debugLog = '/var/www/html/whois_debug.log';
$logPath = $debugLog;
$validationResult = validateHcaptcha($hcaptchaResponse, $logPath);

// Start debugging
$startTime = microtime(true);
@file_put_contents($debugLog, "[$startTime] Script started\n", FILE_APPEND | LOCK_EX);

// Include files with error checking
if (!file_exists('/var/www/config/config.php')) {
  $error = "Config file not found: /var/www/config/config.php";
  @file_put_contents($debugLog, "[$startTime] Error: $error\n", FILE_APPEND | LOCK_EX);
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $error]);
  exit;
}
require_once '/var/www/config/config.php';

$hcaptchaUtilsPath = __DIR__ . '/hcaptcha-utils.php';
@file_put_contents($debugLog, "[$startTime] Checking hCaptcha utils path: $hcaptchaUtilsPath\n", FILE_APPEND | LOCK_EX);
@file_put_contents($debugLog, "[$startTime] File exists: " . (file_exists($hcaptchaUtilsPath) ? 'Yes' : 'No') . "\n", FILE_APPEND | LOCK_EX);
if (!file_exists($hcaptchaUtilsPath)) {
  $error = "hCaptcha utils file not found: " . $hcaptchaUtilsPath;
  @file_put_contents($debugLog, "[$startTime] Error: $error\n", FILE_APPEND | LOCK_EX);
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $error]);
  exit;
}
require_once $hcaptchaUtilsPath;
@file_put_contents($debugLog, "[$startTime] Successfully included hcaptcha-utils.php\n", FILE_APPEND | LOCK_EX);

header('Content-Type: application/json');
$response = ['success' => false, 'whois' => [], 'error' => '', 'available' => false];

$hcaptchaResponse = $_GET['h-captcha-response'] ?? '';
@file_put_contents($debugLog, "[$startTime] Received hCaptcha response: $hcaptchaResponse\n", FILE_APPEND | LOCK_EX);
$validationResult = validateHcaptcha($hcaptchaResponse);
if (!$validationResult['success']) {
    $response['error'] = $validationResult['error'];
    @file_put_contents($debugLog, "[$startTime] hCaptcha validation error: " . $response['error'] . "\n", FILE_APPEND | LOCK_EX);
    echo json_encode($response);
    exit;
}

$domain = $_GET['domain'] ?? '';
@file_put_contents($debugLog, "[$startTime] Received domain: $domain\n", FILE_APPEND | LOCK_EX);

if ($domain && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    $whoisStart = microtime(true);
    $whoisOutput = @shell_exec("whois " . escapeshellarg($domain));
    $whoisEnd = microtime(true);
    @file_put_contents($debugLog, "[$startTime] WHOIS time: " . (($whoisEnd - $whoisStart) * 1000) . " ms\n", FILE_APPEND | LOCK_EX);

    if ($whoisOutput === null || trim($whoisOutput) === '') {
        $response['error'] = "No WHOIS data found for $domain or query failed.";
        @file_put_contents($debugLog, "[$startTime] Error: " . $response['error'] . "\n", FILE_APPEND | LOCK_EX);
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
@file_put_contents($debugLog, "[$startTime] Total time: $totalTime ms\n", FILE_APPEND | LOCK_EX);
echo json_encode($response);
?>