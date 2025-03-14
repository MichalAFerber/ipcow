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

// Validate target (IP or hostname)
if (!filter_var($target, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+$/', $target)) {
    $response['error'] = 'Invalid target. Use an IP address or hostname.';
    echo json_encode($response);
    exit;
}

// Parse and validate ports
$portList = array_filter(array_map('trim', explode(',', $ports)));
foreach ($portList as $port) {
    if (!is_numeric($port) || $port < 1 || $port > 65535) {
        $response['error'] = 'Invalid port number. Use 1-65535, separated by commas.';
        echo json_encode($response);
        exit;
    }
}

// Prepare nmap command
$portString = implode(',', $portList);
$command = "/usr/bin/nmap -Pn -p " . escapeshellarg($portString) . " " . escapeshellarg($target) . " 2>&1";
$output = shell_exec($command);

// Debug: Log the raw output to a different path
$logPath = '/var/www/html/nmap_debug.log'; // Adjust to a writable path
if (!file_put_contents($logPath, "Command: $command\nOutput: $output\n\n", FILE_APPEND)) {
    error_log("Failed to write to $logPath");
}

// Check if shell_exec is disabled or failed
if ($output === null) {
    $response['error'] = 'Unable to execute port scan: shell_exec might be disabled or nmap failed.';
    echo json_encode($response);
    exit;
}

// Parse nmap output
$results = [];
if ($output && strpos($output, 'Nmap scan report') !== false) {
    $lines = explode("\n", $output);
    $inPortSection = false;
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^Nmap scan report for/', $line)) {
            $inPortSection = true;
            continue;
        }
        if ($inPortSection && preg_match('/^(\d+)\/tcp\s+(open|closed|filtered)\s+(\S+)/i', $line, $matches)) {
            $port = $matches[1];
            $status = ucfirst(strtolower($matches[2]));
            $service = $matches[3];
            $results[$port] = "$status ($service)";
        }
    }

    // Ensure all requested ports are in the results
    foreach ($portList as $port) {
        if (!isset($results[$port])) {
            $results[$port] = 'Filtered (unknown)';
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