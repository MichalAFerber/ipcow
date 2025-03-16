<?php
require_once '/var/www/config/config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'whois' => [], 'error' => '', 'available' => false];

$logPath = '/var/www/html/whois_debug.log';
$startTime = microtime(true);

// Get hCaptcha response
$hcaptchaResponse = $_GET['h-captcha-response'] ?? '';
if (empty($hcaptchaResponse)) {
  $response['error'] = 'hCaptcha response missing.';
  file_put_contents($logPath, "[$startTime] Error: hCaptcha response missing\n", FILE_APPEND);
  echo json_encode($response);
  exit;
}

// Validate hCaptcha response
$secretKey = defined('HCAPTCHA_SECRET_KEY') ? HCAPTCHA_SECRET_KEY : 'your-hcaptcha-secret-key-here'; // Replace with your secret key if not defined
$verifyUrl = 'https://hcaptcha.com/siteverify';
$verifyData = [
  'secret' => $secretKey,
  'response' => $hcaptchaResponse,
  'remoteip' => $_SERVER['REMOTE_ADDR'] // Optional: Include the user's IP address
];

$options = [
  'http' => [
    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
    'method'  => 'POST',
    'content' => http_build_query($verifyData),
  ],
];
$context  = stream_context_create($options);
$verifyResult = file_get_contents($verifyUrl, false, $context);
$verifyResult = json_decode($verifyResult, true);

file_put_contents($logPath, "[$startTime] hCaptcha verification result: " . json_encode($verifyResult) . "\n", FILE_APPEND);

if (!$verifyResult || !$verifyResult['success']) {
  $response['error'] = 'hCaptcha verification failed.';
  file_put_contents($logPath, "[$startTime] Error: hCaptcha verification failed\n", FILE_APPEND);
  echo json_encode($response);
  exit;
}

file_put_contents($logPath, "[$startTime] hCaptcha verification successful\n", FILE_APPEND);

// Process the domain lookup
$domain = $_GET['domain'] ?? '';
file_put_contents($logPath, "[$startTime] Received domain: $domain\n", FILE_APPEND);

if ($domain && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
  $whoisStart = microtime(true);
  $whoisOutput = shell_exec("whois " . escapeshellarg($domain));
  $whoisEnd = microtime(true);
  file_put_contents($logPath, "[$startTime] WHOIS time: " . (($whoisEnd - $whoisStart) * 1000) . " ms\n", FILE_APPEND);

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
file_put_contents($logPath, "[$startTime] Total time: $totalTime ms\n", FILE_APPEND);
echo json_encode($response);
?>