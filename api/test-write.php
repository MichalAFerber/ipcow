<?php
header('Content-Type: text/plain');
$user = exec('whoami');
file_put_contents('/tmp/php_test.log', "User: $user\nTime: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
echo "User: $user\nWrote to /tmp/php_test.log\n";
?>