<?php
header('Content-Type: text/plain');
require 'vendor/autoload.php';

use Net_DNS2_Resolver;

$domain = $_GET['domain'] ?? '';
if ($domain && filter_var($domain, FILTER_VALIDATE_DOMAIN)) {
    try {
        $resolver = new Net_DNS2_Resolver(['nameservers' => ['8.8.8.8']]);
        $response = $resolver->query($domain, 'ANY');
        print_r($response->answer);
    } catch (Exception $e) {
        echo "DNS query failed: " . $e->getMessage();
    }
} else {
    echo "Invalid domain.";
}
