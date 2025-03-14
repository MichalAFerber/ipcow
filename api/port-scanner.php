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

// Resolve hostname to IP if necessary
$ip = filter_var($target, FILTER_VALIDATE_IP) ? $target : gethostbyname($target);
if ($ip === $target && !filter_var($ip, FILTER_VALIDATE_IP)) {
    $response['error'] = 'Could not resolve hostname.';
    echo json_encode($response);
    exit;
}

// Scan ports
$results = [];
$timeout = 2; // Seconds to wait for connection
foreach ($portList as $port) {
    $connection = @fsockopen($ip, $port, $errno, $errstr, $timeout);
    if (is_resource($connection)) {
        $results[$port] = 'Open';
        fclose($connection);
    } else {
        $results[$port] = ($errno === 111) ? 'Closed' : 'Filtered'; // 111 = Connection refused
    }
}

$response['success'] = true;
$response['data']['ports'] = $results;

echo json_encode($response);
?>