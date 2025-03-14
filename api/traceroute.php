<?php
header('Content-Type: application/json');
$response = ['success' => false, 'data' => [], 'error' => ''];

$target = $_GET['target'] ?? '';

if (!$target) {
    $response['error'] = 'Missing target.';
    echo json_encode($response);
    exit;
}

// Validate target (IP or hostname)
if (!filter_var($target, FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+$/', $target)) {
    $response['error'] = 'Invalid target. Use an IP address or hostname.';
    echo json_encode($response);
    exit;
}

// Prepare traceroute command
$command = "traceroute -n " . escapeshellarg($target) . " 2>&1"; // -n disables DNS lookups for speed
$output = shell_exec($command);

// Debug: Log the raw output
$logPath = '/var/www/html/traceroute_debug.log';
file_put_contents($logPath, "Command: $command\nOutput:\n$output\n\n", FILE_APPEND);

if ($output === null) {
    $response['error'] = 'Unable to execute traceroute: shell_exec might be disabled or traceroute failed.';
    echo json_encode($response);
    exit;
}

$hops = [];
if ($output) {
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^\s*(\d+)\s+([\d.]+)\s+([\d.]+)\s+ms/', $line, $matches)) {
            $hopNumber = $matches[1];
            $ip = $matches[2];
            $latency = $matches[3];
            $hops[] = [
                'hop' => $hopNumber,
                'ip' => $ip,
                'latency' => $latency,
                'hostname' => gethostbyaddr($ip) ?: 'Unknown'
            ];
        }
    }

    $response['success'] = true;
    $response['data']['hops'] = $hops;
} else {
    $response['error'] = 'No traceroute data received.';
    file_put_contents($logPath, "No valid output detected.\n", FILE_APPEND);
}

echo json_encode($response);
?>