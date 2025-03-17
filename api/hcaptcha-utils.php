<?php
require_once __DIR__ . '/utils.php';

function validateHcaptcha($response) {
    global $logFile;
    debugLog("validateHcaptcha called with response: " . substr($response, 0, 50) . "...");
    
    require_once '/var/www/config/config.php';
    if (!defined('HCAPTCHA_SECRET_KEY')) { // Check for constant
        debugLog("Error: hCaptcha secret key constant not defined in config.php");
        return false;
    }
    debugLog("Secret key loaded from config.php");
    
    $secretKey = HCAPTCHA_SECRET_KEY; // Use the constant
    $url = "https://hcaptcha.com/siteverify";
    $data = array(
        'secret' => $secretKey,
        'response' => $response
    );
    debugLog("Preparing POST data: secret=" . substr($secretKey, 0, 5) . "...");
    
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        )
    );
    debugLog("Sending HTTP request to $url");
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        $error = error_get_last();
        debugLog("Error: Failed to get response from hCaptcha API - " . ($error['message'] ?? 'Unknown error'));
        return false;
    }
    debugLog("HTTP response received: " . substr($result, 0, 100) . "...");
    
    $responseData = json_decode($result, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugLog("Error: Failed to decode JSON response: " . json_last_error_msg());
        return false;
    }
    debugLog("JSON decoded: " . json_encode($responseData));
    if (isset($responseData['error-codes'])) {
        debugLog("hCaptcha error codes: " . implode(', ', $responseData['error-codes']));
    }
    
    return $responseData['success'] ?? false;
}
?>