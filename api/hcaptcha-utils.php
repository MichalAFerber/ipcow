<?php
// /home/appleseed/ipcow/core-dev/api/hcaptcha-utils.php

// Include the configuration file
require_once '/var/www/config/config.php';

// Function to validate hCaptcha response
function validateHcaptcha($response) {
    $startTime = microtime(true);

    if (empty($response)) {
        error_log("[$startTime] Error: hCaptcha response missing.");
        return ['success' => false, 'error' => 'hCaptcha response missing.'];
    }

    $secretKey = defined('HCAPTCHA_SECRET_KEY') ? HCAPTCHA_SECRET_KEY : die('HCAPTCHA_SECRET_KEY not defined in config.php');
    $verifyUrl = 'https://api.hcaptcha.com/siteverify';
    $verifyData = [
        'secret' => $secretKey,
        'response' => $response,
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
        error_log("[$startTime] Error: $error");
        return ['success' => false, 'error' => $error];
    }

    $verifyResult = json_decode($verifyResult, true);
    error_log("[$startTime] hCaptcha verification result: " . json_encode($verifyResult));

    if (!$verifyResult || !$verifyResult['success']) {
        $error = 'hCaptcha verification failed: ' . ($verifyResult['error-codes'][0] ?? 'Unknown error');
        error_log("[$startTime] Error: $error");
        return ['success' => false, 'error' => $error];
    }

    error_log("[$startTime] hCaptcha verification successful");
    return ['success' => true];
}