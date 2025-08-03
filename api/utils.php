<?php
$logFile = '/var/www/html/whois_debug.log';
function debugLog($message) {
    global $logFile;
    if (empty($logFile)) {
        $logFile = '/tmp/whois_debug.log';
    }
    $timestamp = microtime(true);
    $formattedMessage = "[$timestamp] $message\n";
    file_put_contents($logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
}
?>