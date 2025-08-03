<?php
header('Content-Type: application/json');
$response = ['success' => false, 'output' => '', 'average_rtt' => 'N/A', 'error' => ''];

$host = $_GET['host'] ?? '';

// Debug: Log the received host
$logPath = '/var/www/html/ping_debug.log';
file_put_contents($logPath, "Received host: $host\n", FILE_APPEND);

if ($host && (filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || filter_var($host, FILTER_VALIDATE_IP))) {
    // Sanitize host to prevent command injection
    $host = escapeshellarg($host);
    $os = strtoupper(PHP_OS);
    $command = stripos($os, 'WIN') === 0 ? "ping -n 4 $host 2>&1" : "ping -c 4 $host 2>&1";
    exec($command, $output, $return_var);

    if ($return_var === 0) {
        $response['success'] = true;
        $response['output'] = implode("\n", $output);
        foreach ($output as $line) {
            if (stripos($os, 'WIN') === 0) {
                if (preg_match('/Average = (\d+)ms/', $line, $matches)) {
                    $response['average_rtt'] = $matches[1];
                    break;
                }
            } else {
                if (preg_match('/rtt min\/avg\/max\/mdev = (\d+\.\d+)\/(\d+\.\d+)\/(\d+\.\d+)/', $line, $matches)) {
                    $response['average_rtt'] = $matches[2];
                    break;
                }
            }
        }
    } else {
        $response['error'] = implode("\n", $output) ?: "Ping failed.";
    }
} else {
    $response['error'] = "Invalid host or IP address.";
}

echo json_encode($response);
?>