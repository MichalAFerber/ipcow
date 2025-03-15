<?php
header('Content-Type: application/json');
$response = ['success' => false, 'records' => [], 'error' => ''];

$domain = $_GET['domain'] ?? '';
if ($domain && filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
    $allRecords = [];
    $types = ['A', 'AAAA', 'MX', 'NS', 'TXT', 'SOA', 'HTTPS', 'CAA', 'HINFO']; // Expanded types
    foreach ($types as $type) {
        $json = file_get_contents("https://dns.google/resolve?name=" . urlencode($domain) . "&type=$type");
        if ($json === false) {
            $response['error'] = "Failed to query DNS for $type records.";
            continue;
        }
        $data = json_decode($json, true);
        if ($data && isset($data['Answer'])) {
            $allRecords = array_merge($allRecords, $data['Answer']);
        }
    }
    if (!empty($allRecords)) {
        $response['success'] = true;
        $response['records'] = $allRecords;
    } else {
        $response['error'] = "No DNS records found for $domain.";
    }
} else {
    $response['error'] = "Invalid domain.";
}

echo json_encode($response);
?>