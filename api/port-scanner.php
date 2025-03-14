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

// Debug: Log the raw output
file_put_contents('/tmp/nmap_debug.log', "Command: $command\nOutput: $output\n\n", FILE_APPEND);

// Check if shell_exec is disabled
if ($output === null) {
    $response['error'] = 'Unable to execute port scan: shell_exec might be disabled.';
    echo json_encode($response);
    exit;
}

// Parse nmap output
$results = [];
if ($output) {
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        if (preg_match('/^(\d+)\/tcp\s+(open|closed|filtered)\s+(\S+)/i', trim($line), $matches)) {
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
    $response['error'] = 'Failed to execute port scan or no output received.';
}

echo json_encode($response);
?>