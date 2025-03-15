<?php
require_once '/var/www/config/config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'whois' => [], 'error' => '', 'available' => false];

$logPath = '/var/www/html/whois_debug.log';
$startTime = microtime(true); // Start timing for debugging

// Get ALTCHA payload from the request
$altchaPayload = $_GET['altcha'] ?? '';
if (empty($altchaPayload)) {
  $response['error'] = 'ALTCHA payload missing.';
  file_put_contents($logPath, "Error: ALTCHA payload missing\n", FILE_APPEND);
  echo json_encode($response);
  exit;
}

// Decode the ALTCHA payload
$payload = json_decode(base64_decode($altchaPayload), true);
if (!$payload || !isset($payload['challenge'], $payload['signature'], $payload['number'], $payload['salt'])) {
  $response['error'] = 'Invalid ALTCHA payload.';
  file_put_contents($logPath, "Error: Invalid ALTCHA payload\n", FILE_APPEND);
  echo json_encode($response);
  exit;
}

// Fetch the challenge (in production, store challenges in a cache or DB to prevent reuse)
$challengeStart = microtime(true);
$challengeData = json_decode(file_get_contents('http://localhost/api/altcha-challenge.php'), true);
$challengeEnd = microtime(true);
file_put_contents($logPath, "Challenge fetch time: " . (($challengeEnd - $challengeStart) * 1000) . " ms\n", FILE_APPEND);

if (!$challengeData || !isset($challengeData['challenge'], $challengeData['signature'])) {
  $response['error'] = 'Failed to fetch ALTCHA challenge.';
  file_put_contents($logPath, "Error: Failed to fetch ALTCHA challenge\n", FILE_APPEND);
  echo json_encode($response);
  exit;
}

// Verify the signature
$expectedSignature = hash_hmac('sha256', $payload['challenge'] . $payload['salt'], ALTCHA_SECRET_KEY);
if ($payload['signature'] !== $expectedSignature) {
  $response['error'] = 'ALTCHA verification failed: Invalid signature.';
  file_put_contents($logPath, "Error: Invalid ALTCHA signature\n", FILE_APPEND);
  echo json_encode($response);
  exit;
}

// Verify the proof-of-work
$hash = hash('sha256', $payload['challenge'] . $payload['salt'] . $payload['number']);
$requiredZeros = ceil($challengeData['complexity'] / 4); // Approximate leading zeros for difficulty
$leadingZeros = 0;
for ($i = 0; $i < strlen($hash); $i++) {
  if ($hash[$i] !== '0') break;
  $leadingZeros++;
}

if ($leadingZeros < $requiredZeros) {
  $response['error'] = 'ALTCHA verification failed: Insufficient proof-of-work.';
  file_put_contents($logPath, "Error: Insufficient proof-of-work (Leading zeros: $leadingZeros, Required: $requiredZeros)\n", FILE_APPEND);
  echo json_encode($response);
  exit;
}

// Log successful verification
file_put_contents($logPath, "ALTCHA verification successful\n", FILE_APPEND);

// Proceed with WHOIS lookup
$domain = $_GET['domain'] ?? '';
file_put_contents($logPath, "Received domain: $domain\n", FILE_APPEND);

if ($domain && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
  $whoisStart = microtime(true);
  $whoisOutput = shell_exec("whois " . escapeshellarg($domain));
  $whoisEnd = microtime(true);
  file_put_contents($logPath, "WHOIS time: " . (($whoisEnd - $whoisStart) * 1000) . " ms\n", FILE_APPEND);

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
file_put_contents($logPath, "Total time: $totalTime ms\n", FILE_APPEND);
echo json_encode($response);
?>