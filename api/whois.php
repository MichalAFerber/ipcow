<?php
header('Content-Type: application/json');
$response = ['success' => false, 'whois' => [], 'error' => ''];

$domain = $_GET['domain'] ?? '';
if ($domain && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    // Execute the WHOIS command
    $whoisOutput = shell_exec("whois " . escapeshellarg($domain));

    if ($whoisOutput === null || trim($whoisOutput) === '') {
        $response['error'] = "No WHOIS data found for $domain or query failed.";
    } else {
        // Parse the WHOIS output into key-value pairs
        $whoisData = [];
        $lines = explode("\n", trim($whoisOutput));
        foreach ($lines as $line) {
            // Skip empty lines, comments, or notices
            if (empty($line) || str_starts_with($line, '#') || str_starts_with($line, '%') || str_starts_with($line, '>>>') || str_starts_with($line, 'NOTICE:')) {
                continue;
            }
            // Split on the first colon to separate key and value
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                // Skip if key or value is empty
                if (!empty($key) && !empty($value)) {
                    // Handle repeated keys (like Name Server) by converting to array
                    if (isset($whoisData[$key])) {
                        if (!is_array($whoisData[$key])) {
                            $whoisData[$key] = [$whoisData[$key]];
                        }
                        $whoisData[$key][] = $value;
                    } else {
                        $whoisData[$key] = $value;
                    }
                }
            }
        }

        if (!empty($whoisData)) {
            $response['success'] = true;
            $response['whois'] = $whoisData;
        } else {
            $response['error'] = "Could not parse WHOIS data for $domain.";
        }
    }
} else {
    $response['error'] = "Invalid domain.";
}

echo json_encode($response);
?>