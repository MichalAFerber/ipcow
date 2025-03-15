<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
echo json_encode([
    "algorithm" => "SHA-256",
    "challenge" => bin2hex(random_bytes(20)),
    "salt" => bin2hex(random_bytes(8)),
    "complexity" => 500, // Reduced from 5000
    "signature" => hash_hmac('sha256', bin2hex(random_bytes(20)) . bin2hex(random_bytes(8)), '2473d8c162ed07b0f9a56f9a026860e995b14dbff9769f1a43bc9742a8319798')
]);
?>