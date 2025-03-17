<?php
$logFile = '/var/www/html/whois_debug.log';

function debugLog($message) {
    global $logFile;
    $timestamp = microtime(true);
    $formattedMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
}

function validateHcaptcha($response) {
    global $logFile;
    debugLog("validateHcaptcha called with response: $response");
    
    // Include the config file with the secret key
    require_once '/var/www/config/config.php';
    if (!isset($hcaptchaSecretKey)) {
        debugLog("Error: hCaptcha secret key not found in config.php");
        return false;
    }
    debugLog("Secret key loaded from config.php");
    
    $secretKey = $hcaptchaSecretKey;
    $url = "https://hcaptcha.com/siteverify";
    $data = array(
        'secret' => $secretKey,
        'response' => $response
    );
    debugLog("Preparing POST data");
    $options = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        )
    );
    debugLog("Sending HTTP request to $url");
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === false) {
        debugLog("Error: Failed to get response from hCaptcha API");
        return false;
    }
    debugLog("HTTP response received");
    $responseData = json_decode($result, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        debugLog("Error: Failed to decode JSON response: " . json_last_error_msg());
        return false;
    }
    debugLog("JSON decoded: " . json_encode($responseData));
    return $responseData['success'] ?? false;
}
?>