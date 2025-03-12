<?php
header('Content-Type: text/plain');
$domain = $_GET['domain'] ?? '';
if ($domain && filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
    $allRecords = [];
    $types = ['A', 'AAAA', 'MX', 'NS', 'TXT', 'SOA']; // Specific types to query
    foreach ($types as $type) {
        $json = @file_get_contents("https://dns.google/resolve?name=$domain&type=$type");
        if ($json !== false) {
            $data = json_decode($json, true);
            if ($data && isset($data['Answer'])) {
                $allRecords = array_merge($allRecords, $data['Answer']);
            }
        }
    }
    if (!empty($allRecords)) {
        print_r($allRecords);
    } else {
        echo "No DNS records found for $domain.";
    }
} else {
    echo "Invalid domain.";
}
