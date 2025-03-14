<?php
header('Content-Type: application/json');
$response = ['success' => false, 'headers' => [], 'status' => null, 'status_text' => '', 'error' => ''];

$url = $_GET['url'] ?? '';
if (empty($url)) {
    $response['error'] = "No URL provided.";
    echo json_encode($response);
    exit;
}

// Validate URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    $response['error'] = "Invalid URL.";
    echo json_encode($response);
    exit;
}

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in the output
curl_setopt($ch, CURLOPT_NOBODY, true); // Use HEAD request (no body)
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
curl_setopt($ch, CURLOPT_MAXREDIRS, 10); // Limit redirects
curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set timeout to 10 seconds

// Execute the request
$curlResponse = curl_exec($ch);

// Check for cURL errors
if ($curlResponse === false) {
    $response['error'] = "Failed to fetch headers: " . curl_error($ch);
    curl_close($ch);
    echo json_encode($response);
    exit;
}

// Get the HTTP status code and status text
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$statusText = curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ? curl_getinfo($ch, CURLINFO_RESPONSE_CODE) : 'Unknown';

// Parse the headers
$headers = [];
$headerText = substr($curlResponse, 0, curl_getinfo($ch, CURLINFO_HEADER_SIZE));
$headerLines = explode("\r\n", trim($headerText));
foreach ($headerLines as $line) {
    if (strpos($line, ':') !== false) {
        list($key, $value) = explode(':', $line, 2);
        $headers[trim($key)] = trim($value);
    }
}

curl_close($ch);

// Prepare the response
$response['success'] = true;
$response['headers'] = $headers;
$response['status'] = $statusCode;
$response['status_text'] = $statusText;

echo json_encode($response);
?>