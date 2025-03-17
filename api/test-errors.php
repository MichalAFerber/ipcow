<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/php_errors.log');
error_reporting(E_ALL);
trigger_error('Test error to verify logging', E_USER_NOTICE);
echo "This should be shown if the script continues.";
?>