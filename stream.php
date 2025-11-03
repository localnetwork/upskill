<?php

use RedBeanPHP\R;

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/models/OrderLine.php';

// ✅ Enable CORS early — before any output
// $origin = env('APP_URL') ?? '*';
$origin = '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Range, X-Requested-With, Authorization");
header("Access-Control-Expose-Headers: Content-Range, Accept-Ranges, Content-Length");

// ✅ Handle preflight (OPTIONS) request properly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No content
    exit();
}

// ✅ Get course_id from query 
$course_id = $_GET['course_id'] ?? null;

// echo json_encode(['course_id' => OrderLine::checkCourseEnrolled($course_id)]);


if (!$course_id) {
    header("Content-Type: application/json");
    http_response_code(400);
    echo json_encode(["message" => "Missing course_id."]);
    exit();
}

// // ✅ Check enrollment  
$is_enrolled = OrderLine::checkCourseEnrolled($course_id);


if (!$is_enrolled) {
    header("Content-Type: application/json");
    http_response_code(403);
    echo json_encode(["message" => "Access denied. User not enrolled in this course."]);
    exit();
}

// ✅ Get video ID  
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Content-Type: application/json");
    http_response_code(400);
    echo json_encode(["message" => "Missing video ID."]);
    exit();
}

// ✅ Fetch video using RedBeanPHP
$video = R::findOne('videos', 'id = ?', [$id]);
if (!$video) {
    header("Content-Type: application/json");
    http_response_code(404);
    echo json_encode(["message" => "Video not found."]);
    exit();
}

// ✅ Resolve full file path
$path = __DIR__ . '/' . ltrim($video->path, '/');

echo json_encode(['path' => $path]);
$mime = $video->mime_type ?? 'video/mp4';

// ✅ Validate file existence
if (!file_exists($path)) {
    header("Content-Type: application/json");
    http_response_code(404);
    echo json_encode(["message" => "File missing on server."]);
    exit();
}

// ✅ Prepare for streaming
ignore_user_abort(true);
clearstatcache(true, $path);
$size = filesize($path);
$start = 0;
$end = $size - 1;
$length = $size;

// ✅ Handle partial content (Range)
if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
    $start = $matches[1] !== '' ? intval($matches[1]) : 0;
    $end = $matches[2] !== '' ? intval($matches[2]) : $end;
    $end = min($end, $size - 1);
    $length = $end - $start + 1;

    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$size");
} else {
    header('HTTP/1.1 200 OK');
}

header("Content-Type: $mime");
header("Accept-Ranges: bytes");
header("Content-Length: $length");

// ✅ Clean output buffers before streaming
while (ob_get_level()) {
    ob_end_clean();
}

// ✅ Stream file safely
$fp = fopen($path, 'rb');
fseek($fp, $start);

$buffer = 65536; // 64KB chunks
while (!feof($fp) && ftell($fp) <= $end) {
    echo fread($fp, $buffer);
    flush();
    if (connection_aborted()) break;
}

fclose($fp);
exit();
