<?php
header('Content-Type: application/json');
$host = $_GET['host'] ?? '';
$response = ['success' => false, 'output' => '', 'average_rtt' => 'N/A'];

if ($host && filter_var($host, FILTER_VALIDATE_DOMAIN) || filter_var($host, FILTER_VALIDATE_IP)) {
    // Use exec to run the ping command (adjust based on your OS)
    $command = "ping -c 4 $host 2>&1"; // -c 4 for 4 pings, adjust for Windows if needed
    exec($command, $output, $return_var);

    if ($return_var === 0) {
        $response['success'] = true;
        $response['output'] = implode("\n", $output);
        // Parse average RTT (Linux format: "rtt min/avg/max = ...")
        foreach ($output as $line) {
            if (preg_match('/rtt min\/avg\/max\/mdev = (\d+\.\d+)\/(\d+\.\d+)\/(\d+\.\d+)/', $line, $matches)) {
                $response['average_rtt'] = $matches[2]; // Average RTT in ms
                break;
            }
        }
    } else {
        $response['output'] = implode("\n", $output) ?: "Ping failed.";
    }
} else {
    $response['output'] = "Invalid host or IP address.";
}

echo json_encode($response);
?>
