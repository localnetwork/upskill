<?php
require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    exit("Missing video ID.");
}

// Use RedBeanPHP to get the video record
$video = R::findOne('videos', 'id = ?', [$id]);

if (!$video) {
    http_response_code(404);
    exit("Video not found.");
}

$path = $video->path;
$mime = $video->mime_type;

if (!file_exists($path)) {
    http_response_code(404);
    exit("File missing on server.");
}

header("Content-Type: $mime");
header("Accept-Ranges: bytes");

$size = filesize($path);
$start = 0;
$end = $size - 1;
$length = $size;

if (isset($_SERVER['HTTP_RANGE'])) {
    [$unit, $range] = explode('=', $_SERVER['HTTP_RANGE'], 2);
    if (strpos($range, ',') !== false) {
        header("HTTP/1.1 416 Requested Range Not Satisfiable");
        exit;
    }

    [$rangeStart, $rangeEnd] = explode('-', $range);
    $start = intval($rangeStart);
    $end = $rangeEnd !== '' ? intval($rangeEnd) : $end;
    $end = ($end > $size - 1) ? $size - 1 : $end;

    $length = $end - $start + 1;
    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$size");
}

header("Content-Length: $length");

$fp = fopen($path, 'rb');
fseek($fp, $start);
$buffer = 8192;
while (!feof($fp) && ($pos = ftell($fp)) <= $end) {
    if ($pos + $buffer > $end) {
        $buffer = $end - $pos + 1;
    }
    echo fread($fp, $buffer);
    flush();
}
fclose($fp);
