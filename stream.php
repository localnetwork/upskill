<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RedBeanPHP\R;

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/models/OrderLine.php';

// ✅ Restrict access only to your production domain   
$allowedOrigin = 'http://localhost:3000'; // change this!
header("Access-Control-Allow-Origin: $allowedOrigin");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type, Range");
header("Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length, Content-Type");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// ✅ Check for Authorization header
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!$authHeader) {
    http_response_code(401);
    echo json_encode(["message" => "Missing Authorization header."]);
    exit();
}

$token = str_replace('Bearer ', '', $authHeader);
$secretKey = env('JWT_SECRET');

try {
    $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

    // Optional: Reject expired tokens manually if not handled by library
    if (isset($decoded->exp) && $decoded->exp < time()) {
        throw new Exception("Token expired");
    }
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(["message" => "Invalid or expired token."]);
    exit();
}

// ✅ Required parameters
$course_id = $_GET['course_id'] ?? null;
$id = $_GET['id'] ?? null;
if (!$course_id || !$id) {
    http_response_code(400);
    echo json_encode(["message" => "Missing parameters."]);
    exit();
}

// ✅ Verify user enrollment
$is_enrolled = OrderLine::checkCourseEnrolled($course_id);
if (!$is_enrolled) {
    http_response_code(403);
    echo json_encode(["message" => "Access denied."]);
    exit();
}

// ✅ Fetch video record
$video = R::findOne('videos', ' id = ? ', [$id]);
if (!$video) {
    http_response_code(404);
    echo json_encode(["message" => "Video not found."]);
    exit();
}

$filePath = __DIR__ . $video->path;
if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(["message" => "File missing on server."]);
    exit();
}

// ✅ Send video content safely
$mimeType = mime_content_type($filePath);
header("Content-Type: $mimeType");
header("Accept-Ranges: bytes");

$size = filesize($filePath);
$start = 0;
$end = $size - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    [$param, $range] = explode('=', $_SERVER['HTTP_RANGE'], 2);
    [$rangeStart, $rangeEnd] = explode('-', $range);

    $start = intval($rangeStart);
    $end = $rangeEnd !== '' ? intval($rangeEnd) : $end;

    if ($start > $end || $start >= $size) {
        header("HTTP/1.1 416 Requested Range Not Satisfiable");
        exit;
    }

    header("HTTP/1.1 206 Partial Content");
    header("Content-Range: bytes $start-$end/$size");
}

$length = $end - $start + 1;
header("Content-Length: $length");

$fp = fopen($filePath, 'rb');
fseek($fp, $start);
$bufferSize = 8192;

while (!feof($fp) && ftell($fp) <= $end) {
    echo fread($fp, $bufferSize);
    flush();
}

fclose($fp);
exit();
