<?php
header('Content-Type: text/plain');

// Test nmap execution
$command = "/usr/bin/nmap -Pn -p 80 kk.ferber.me 2>&1";
$output = shell_exec($command);

// Log the command and output
file_put_contents('/tmp/nmap_debug.log', "Command: $command\nOutput: $output\n\n", FILE_APPEND);

// Return the output for debugging
echo "Command: $command\nOutput: $output\n";
?>