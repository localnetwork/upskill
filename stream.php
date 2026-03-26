<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/Video.php';

// JWT auth, CORS, etc. (reuse your existing code) 
$allowedOrigin = 'http://localhost:3000';
header("Access-Control-Allow-Origin: $allowedOrigin");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, Range");
header("Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length, Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// ✅ Authorization
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$authHeader) {
    http_response_code(401);
    exit(json_encode(["message" => "Missing Authorization"]));
}
$token = str_replace('Bearer ', '', $authHeader);
// decode JWT here, verify user access...

// ✅ Get video ID
$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    exit(json_encode(["message" => "Missing video ID"]));
}

// ✅ Fetch video
$video = Video::find($id);
if (!$video || !file_exists($video->path)) {
    http_response_code(404);
    exit(json_encode(["message" => "Video not found"]));
}

// ✅ Prepare streaming
$fp = fopen($video->path, 'rb');
if (!$fp) {
    http_response_code(500);
    exit(json_encode(["message" => "Cannot open file"]));
}

// read IV from start of file (first 16 bytes)
$iv = hex2bin($video->iv);

// total file size
$size = filesize($video->path) - 16; // subtract IV
$start = 0;
$end = $size - 1;

// handle HTTP Range requests
if (isset($_SERVER['HTTP_RANGE'])) {
    [$param, $range] = explode('=', $_SERVER['HTTP_RANGE'], 2);
    [$rangeStart, $rangeEnd] = explode('-', $range);
    $start = max(0, intval($rangeStart));
    $end = $rangeEnd !== '' ? intval($rangeEnd) : $end;
    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/$size");
}
$length = $end - $start + 1;

header("Content-Type: " . $video->type);
header("Accept-Ranges: bytes");
header("Content-Length: $length");

// move pointer to start + IV
fseek($fp, 16 + $start);

$bufferSize = 8192;
$key = hex2bin('SAMPLE_KEY_HERE');

$bytesSent = 0;
while (!feof($fp) && $bytesSent < $length) {
    $readLength = min($bufferSize, $length - $bytesSent);
    $chunk = fread($fp, $readLength);
    $decrypted = openssl_decrypt($chunk, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    echo $decrypted;
    flush();
    $bytesSent += $readLength;
}

fclose($fp);
exit();
