<?php
header('Content-Type: application/json');
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR']; // Cloudflare fix
$ipv6 = isset($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) 
        ? $_SERVER['HTTP_X_FORWARDED_FOR'] 
        : 'Not available';
$geo = json_decode(file_get_contents("http://ip-api.com/json/$ip")) ?: (object)[];
$hostname = gethostbyaddr($ip) ?: 'Not available';
echo json_encode([
    'ipv4' => $ip,
    'ipv6' => $ipv6,
    'hostname' => $hostname,
    'isp' => $geo->isp ?? 'Not available',
    'country' => $geo->country ?? 'Not available',
    'region' => $geo->regionName ?? 'Not available',
    'city' => $geo->city ?? 'Not available',
    'latitude' => $geo->lat ?? 'Not available',
    'longitude' => $geo->lon ?? 'Not available',
    'timezone' => $geo->timezone ?? 'Not available'
]);
