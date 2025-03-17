<?php
// /home/appleseed/ipcow/core-dev/api/hcaptcha-utils.php

// Include the configuration file
require_once '/var/www/config/config.php';

// Debug log function (shared with whois.php)
function debugLog($message) {
    $logFile = '/var/www/html/whois_debug.log';
    $timestamp = microtime(true);
    $date = date('Y-m-d H:i:s', (int)$timestamp);
    $micro = sprintf("%06d", ($timestamp - floor($timestamp)) * 1000000);
    @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// Function to validate hCaptcha response
function validateHcaptcha($response) {
    $startTime = microtime(true);

    if (empty($response)) {
        debugLog("[$startTime] Error: hCaptcha response missing.");
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
        debugLog("[$startTime] Error: $error");
        return ['success' => false, 'error' => $error];
    }

    $verifyResult = json_decode($verifyResult, true);
    debugLog("[$startTime] hCaptcha verification result: " . json_encode($verifyResult));

    if (!$verifyResult || !$verifyResult['success']) {
        $error = 'hCaptcha verification failed: ' . ($verifyResult['error-codes'][0] ?? 'Unknown error');
        debugLog("[$startTime] Error: $error");
        return ['success' => false, 'error' => $error];
    }

    debugLog("[$startTime] hCaptcha verification successful");
    return ['success' => true];
}