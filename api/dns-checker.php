<?php
header('Content-Type: text/plain');
$domain = $_GET['domain'] ?? '';
if ($domain && filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
    $record_types = [
        DNS_A,     // IPv4
        DNS_AAAA,  // IPv6
        DNS_MX,    // Mail servers
        DNS_NS,    // Name servers
        DNS_TXT,   // TXT records
        DNS_CNAME  // Canonical name
    ];
    $results = [];
    foreach ($record_types as $type) {
        $records = @dns_get_record($domain, $type);
        if ($records !== false && !empty($records)) {
            $results = array_merge($results, $records);
        }
    }
    if (!empty($results)) {
        print_r($results);
    } else {
        echo "No DNS records found for $domain.";
    }
} else {
    echo "Invalid domain.";
}
