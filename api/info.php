<?php
header('Content-Type: application/json');
$response = ['success' => false, 'data' => [], 'error' => ''];

$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$ipv6 = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) 
    ? $_SERVER['REMOTE_ADDR'] 
    : (filter_var($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) 
        ? $_SERVER['HTTP_X_FORWARDED_FOR'] 
        : 'Not available');

$geoUrl = "http://ip-api.com/json/" . urlencode($ip);
$geoJson = file_get_contents($geoUrl);
if ($geoJson === false) {
    $response['error'] = 'Failed to retrieve geolocation data.';
} else {
    $geo = json_decode($geoJson, true) ?: [];
    $response['success'] = true;
    $response['data'] = [
        'ipv4' => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : 'Not available',
        'ipv6' => $ipv6,
        'hostname' => gethostbyaddr($ip) ?: 'Not available',
        'isp' => $geo['isp'] ?? 'Not available',
        'country' => $geo['country'] ?? 'Not available',
        'region' => $geo['regionName'] ?? 'Not available',
        'city' => $geo['city'] ?? 'Not available',
        'latitude' => $geo['lat'] ?? 'Not available',
        'longitude' => $geo['lon'] ?? 'Not available',
        'timezone' => $geo['timezone'] ?? 'Not available'
    ];
}

echo json_encode($response);
?>