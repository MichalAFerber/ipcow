<?php
header('Content-Type: application/json');
$response = ['success' => false, 'data' => [], 'error' => ''];

$target = $_GET['target'] ?? '';
$ports = $_GET['ports'] ?? '';

if (!$target || !$ports) {
    $response['error'] = 'Missing target or ports.';
    echo json_encode($response);
    exit;
}

// Clean and validate target
$target = trim($target);
$target = preg_replace('/^https?:\/\//', '', $target); // Remove http:// or https://
$target = rtrim($target, '/'); // Remove trailing slash

// Debug: Log the cleaned target
$logPath = '/var/www/html/nmap_debug.log';
file_put_contents($logPath, "Target received: $target\n", FILE_APPEND);

// Validate target (IP or hostname)
$ip = filter_var($target, FILTER_VALIDATE_IP);
$hostname = preg_match('/^[a-zA-Z0-9.-]+$/', $target);
if (!$ip && !$hostname) {
    $response['error'] = 'Invalid target. Use an IP address or hostname.';
    file_put_contents($logPath, "Validation failed: Not an IP or hostname\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Resolve hostname to IP if necessary
if (!$ip) {
    $resolvedIp = gethostbyname($target);
    if ($resolvedIp === $target) {
        $response['error'] = 'Invalid target. Could not resolve hostname to an IP address.';
        file_put_contents($logPath, "Resolution failed: $target could not be resolved\n", FILE_APPEND);
        echo json_encode($response);
        exit;
    }
    $target = $resolvedIp; // Use the resolved IP for nmap
}
file_put_contents($logPath, "Target resolved to: $target\n", FILE_APPEND);

// Parse and validate ports
$portList = array_filter(array_map('trim', explode(',', $ports)));
foreach ($portList as $port) {
    if (!is_numeric($port) || $port < 1 || $port > 65535) {
        $response['error'] = 'Invalid port number. Use 1-65535, separated by commas.';
        file_put_contents($logPath, "Invalid port: $port\n", FILE_APPEND);
        echo json_encode($response);
        exit;
    }
}

// Prepare nmap command
$portString = implode(',', $portList);
$command = "/usr/bin/nmap -Pn -p " . escapeshellarg($portString) . " " . escapeshellarg($target) . " 2>&1";
$output = shell_exec($command);

// Debug: Log the raw output
file_put_contents($logPath, "Command: $command\nOutput:\n$output\n\n", FILE_APPEND);

// Check if shell_exec is disabled or failed
if ($output === null) {
    $response['error'] = 'Unable to execute port scan: shell_exec might be disabled or nmap failed.';
    file_put_contents($logPath, "shell_exec failed\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Parse nmap output with enhanced debugging
$results = [];
if ($output && strpos($output, 'Nmap scan report') !== false) {
    $lines = explode("\n", $output);
    $inPortSection = false;
    file_put_contents($logPath, "Parsed Lines:\n", FILE_APPEND);
    foreach ($lines as $index => $line) {
        $line = trim($line);
        file_put_contents($logPath, "Line $index: $line\n", FILE_APPEND);
        if (preg_match('/^PORT\s+STATE\s+SERVICE/', $line)) {
            $inPortSection = true;
            file_put_contents($logPath, "Entered port section at line $index\n", FILE_APPEND);
            continue;
        }
        if ($inPortSection && preg_match('/^(\d+)\/tcp\s+(open|closed|filtered)\s+(\S+)/i', $line, $matches)) {
            $port = $matches[1];
            $status = ucfirst(strtolower($matches[2]));
            $service = $matches[3];
            $results[$port] = "$status ($service)";
            file_put_contents($logPath, "Matched: Port $port = $status ($service)\n", FILE_APPEND);
        }
    }

    // Ensure all requested ports are in the results
    foreach ($portList as $port) {
        if (!isset($results[$port])) {
            $results[$port] = 'Filtered (unknown)';
            file_put_contents($logPath, "Added: Port $port = Filtered (unknown)\n", FILE_APPEND);
        }
    }

    $response['success'] = true;
    $response['data']['ports'] = $results;
} else {
    $response['error'] = 'No valid port scan data received. Check target and server configuration.';
    file_put_contents($logPath, "No valid output detected.\n", FILE_APPEND);
}

echo json_encode($response);
?>