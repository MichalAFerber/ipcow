<?php
header('Content-Type: application/json');
$response = ['success' => false, 'data' => [], 'error' => ''];

$ip = $_GET['ip'] ?? '';
if (!$ip || (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))) {
    $response['error'] = 'Invalid or missing IP address.';
    echo json_encode($response);
    exit;
}

$geoUrl = "http://ip-api.com/json/" . urlencode($ip);
$geoJson = file_get_contents($geoUrl);
if ($geoJson === false) {
    $response['error'] = 'Failed to retrieve IP data.';
} else {
    $geo = json_decode($geoJson, true) ?: [];
    if (isset($geo['status']) && $geo['status'] === 'fail') {
        $response['error'] = $geo['message'] ?? 'IP lookup failed.';
    } else {
        $response['success'] = true;
        $response['data'] = [
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
}

echo json_encode($response);
?>