<?php
require_once '/var/www/config/config.php';

function validateHcaptcha($hcaptchaResponse) {
    global $logPath;
    $startTime = microtime(true);

    if (empty($hcaptchaResponse)) {
        $error = 'hCaptcha response missing.';
        @file_put_contents($logPath, "[$startTime] Error: $error\n", FILE_APPEND | LOCK_EX);
        return ['success' => false, 'error' => $error];
    }

    @file_put_contents($logPath, "[$startTime] Received hCaptcha response: $hcaptchaResponse\n", FILE_APPEND | LOCK_EX);

    $secretKey = defined('HCAPTCHA_SECRET_KEY') ? HCAPTCHA_SECRET_KEY : die('HCAPTCHA_SECRET_KEY not defined in config.php');
    $verifyUrl = 'https://api.hcaptcha.com/siteverify';
    $verifyData = [
        'secret' => $secretKey,
        'response' => $hcaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($verifyData),
        ],
    ];
    $context = stream_context_create($options);
    $verifyResult = @file_get_contents($verifyUrl, false, $context);

    if ($verifyResult === false) {
        $error = 'Failed to connect to hCaptcha verification server.';
        @file_put_contents($logPath, "[$startTime] Error: $error\n", FILE_APPEND | LOCK_EX);
        return ['success' => false, 'error' => $error];
    }

    $verifyResult = json_decode($verifyResult, true);
    @file_put_contents($logPath, "[$startTime] hCaptcha verification result: " . json_encode($verifyResult) . "\n", FILE_APPEND | LOCK_EX);

    if (!$verifyResult || !$verifyResult['success']) {
        $error = 'hCaptcha verification failed: ' . ($verifyResult['error-codes'][0] ?? 'Unknown error');
        @file_put_contents($logPath, "[$startTime] Error: $error\n", FILE_APPEND | LOCK_EX);
        return ['success' => false, 'error' => $error];
    }

    @file_put_contents($logPath, "[$startTime] hCaptcha verification successful\n", FILE_APPEND | LOCK_EX);
    return ['success' => true];
}
?>