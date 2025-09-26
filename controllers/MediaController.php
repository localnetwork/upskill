<?php

require_once __DIR__ . '/../models/Media.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

class MediaController
{
    /**
     * Example GET endpoint (for testing)
     */
    public static function getMediaById($id): void
    {
        header('Content-Type: application/json');
        echo json_encode(["message" => "Hello World"]);
    }

    /**
     * Handle file + form-data upload
     */
    public static function create(): void
    {
        header('Content-Type: application/json');

        // ✅ Expecting multipart/form-data
        $input = $_POST ?? [];
        $file  = $_FILES['file'] ?? null;

        if (!$file) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'No file uploaded. Please send multipart/form-data with a "file" field.'
            ]);
            return;
        }

        try {
            $result = Media::createMedia($input, $file);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => $e->getMessage()
            ]);
        }
    }
}
