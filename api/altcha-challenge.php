<?php
require_once '/var/www/config/config.php';

header('Content-Type: application/json');

$challenge = [
  'algorithm' => 'SHA-256',
  'challenge' => bin2hex(random_bytes(20)), // 40 chars
  'salt' => bin2hex(random_bytes(8)),
  'complexity' => 5000, // Reduced from 10000
  'signature' => ''
];

$challenge['signature'] = hash_hmac('sha256', $challenge['challenge'] . $challenge['salt'], ALTCHA_SECRET_KEY);

echo json_encode($challenge);
?>