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

// Prepare tcptraceroute command with sudo wrapper
$command = "sudo /usr/local/bin/tcptraceroute-wrapper.sh -n " . escapeshellarg($target) . " 80 2>&1";
$output = shell_exec($command);

// Debug: Log the raw output
$logPath = '/var/www/html/traceroute_debug.log';
file_put_contents($logPath, "Command: $command\nOutput:\n$output\n\n", FILE_APPEND);

if ($output === null) {
    $response['error'] = 'Unable to execute traceroute: shell_exec failed or tcptraceroute unavailable.';
    echo json_encode($response);
    exit;
}

$hops = [];
$resolved_ip = null;

// Extract resolved IP from the first line (e.g., "traceroute to google.com (172.253.63.100)")
if (preg_match('/traceroute to [^ ]+ \(([\d.]+)\)/', $output, $matches)) {
    $resolved_ip = $matches[1];
}

if ($output) {
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        $line = trim($line);
        // Match lines like "1  * * *" or "23  * 172.253.63.100 <syn,ack,...>  2.475 ms *"
        if (preg_match('/^\s*(\d+)\s+([0-9.]+|\*)\s*(?:[0-9.]+|\*|<[^>]+>)?\s*([\d.]+)?\s*ms/', $line, $matches)) {
            $hopNumber = $matches[1];
            $ip = $matches[2] === '*' ? 'N/A' : $matches[2];
            $latency = isset($matches[3]) ? $matches[3] : 'N/A';
            $hostname = ($ip !== 'N/A') ? (gethostbyaddr($ip) ?: 'Unknown') : 'N/A';
            $hops[] = [
                'hop' => $hopNumber,
                'ip' => $ip,
                'latency' => $latency,
                'hostname' => $hostname
            ];
        }
    }

    // Log the parsed hops for debugging
    file_put_contents($logPath, "Parsed Hops:\n" . print_r($hops, true) . "\n", FILE_APPEND);

    // Check if the target was reached (compare resolved IP or target)
    $lastHop = end($hops);
    if ($lastHop && ($lastHop['ip'] === $target || ($resolved_ip && $lastHop['ip'] === $resolved_ip))) {
        $response['success'] = true;
        $response['data']['hops'] = $hops;
    } elseif (empty($hops)) {
        $response['error'] = 'No hops detected. The target might be unreachable, or traceroute packets are being blocked by a firewall.';
    } else {
        $response['success'] = true;
        $response['data']['hops'] = $hops;
    }
} else {
    $response['error'] = 'No traceroute data received. Check server configuration.';
    file_put_contents($logPath, "No valid output detected.\n", FILE_APPEND);
}

// Log the final response for debugging
file_put_contents($logPath, "Final Response:\n" . json_encode($response) . "\n\n", FILE_APPEND);

echo json_encode($response);
?>