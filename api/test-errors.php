<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/logs/php_errors.log');
error_reporting(E_ALL);
trigger_error('Test error to verify logging', E_USER_ERROR);
echo "This won't be shown if error logging works.";
?>