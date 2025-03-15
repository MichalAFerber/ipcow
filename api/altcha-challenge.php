<?php
require_once '/var/www/config/config.php';

header('Content-Type: application/json');

$challenge = [
  'algorithm' => 'SHA-256',
  'challenge' => bin2hex(random_bytes(16)), // Random challenge string
  'salt' => bin2hex(random_bytes(8)), // Random salt
  'complexity' => 10000, // Adjust difficulty (higher = harder for bots, slower for users)
  'signature' => ''
];

// Sign the challenge with the ALTCHA secret key
$challenge['signature'] = hash_hmac('sha256', $challenge['challenge'] . $challenge['salt'], ALTCHA_SECRET_KEY);

echo json_encode($challenge);
?>