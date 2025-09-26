<?php

require_once __DIR__ . '/../models/Media.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
class MediaController
{
    // Controller methods here
    public static function getMediaById($id): void
    {
        echo json_encode("Hello World");
    }

    public static function create(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $result = Media::createMedia($input);

        if (!empty($result['error'])) {
            http_response_code($result['status']);
            echo json_encode([
                'errors' => $result['errors'] ?? null, // ✅ Safe access
                'message' => $result['message'] ?? null
            ]);
            return;
        }
        echo json_encode($result);
    }
}
