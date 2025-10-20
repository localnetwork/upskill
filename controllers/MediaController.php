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

        // ✅ Allowed MIME types and extensions
        $allowedMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        $allowedExtensions = ['jpeg', 'jpg', 'png', 'gif', 'webp'];

        // ✅ Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'File upload error: ' . $file['error']
            ]);
            return;
        }

        // ✅ Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Invalid file type. Only JPEG, JPG, PNG, GIF, and WEBP are allowed.'
            ]);
            return;
        }

        // ✅ Validate extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Invalid file extension. Only JPEG, JPG, PNG, GIF, and WEBP are allowed.'
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
