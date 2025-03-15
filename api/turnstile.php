<?php
require_once '/var/www/config/config.php';

function validateTurnstile($turnstileResponse, $secretKey = TURNSTILE_SECRET_KEY) {
  $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
  $data = [
    'secret' => $secretKey,
    'response' => $turnstileResponse,
    'remoteip' => $_SERVER['REMOTE_ADDR']
  ];

  $options = [
    'http' => [
      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
      'method' => 'POST',
      'content' => http_build_query($data)
    ]
  ];

  $context = stream_context_create($options);
  $result = file_get_contents($url, false, $context);
  return json_decode($result, true);
}
?>