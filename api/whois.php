<?php
header('Content-Type: application/json');
$response = ['success' => false, 'whois' => '', 'error' => ''];

$domain = $_GET['domain'] ?? '';
if ($domain && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    // Execute the WHOIS command
    $whoisOutput = shell_exec("whois " . escapeshellarg($domain));

    if ($whoisOutput === null || trim($whoisOutput) === '') {
        $response['error'] = "No WHOIS data found for $domain or query failed.";
    } else {
        $response['success'] = true;
        $response['whois'] = trim($whoisOutput);
    }
} else {
    $response['error'] = "Invalid domain.";
}

echo json_encode($response);
?>