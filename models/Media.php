<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../controllers/AuthController.php';

use Ramsey\Uuid\Uuid;
use RedBeanPHP\R;

class Media
{
    /**
     * Format bytes to human-readable size (KB, MB, GB…)
     */
    private static function formatSize($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function createMedia($data, $file)
    {
        // Validate user authentication
        $currentUser = self::validateUserAuthentication();

        // Validate and sanitize input data
        $title = $data['title'] ?? null;
        $description = $data['description'] ?? null;

        // Validate file upload
        self::validateFileUpload($file);

        // Process and store the file
        $fileInfo = self::processFileUpload($file);

        // Create media record
        $mediaData = self::createMediaRecord($title, $description, $fileInfo, $currentUser->user->id);

        return $mediaData;
    }

    /**
     * Validate user authentication
     * @throws \Exception if user is not authenticated
     */
    private static function validateUserAuthentication()
    {
        $currentUser = AuthController::getCurrentUser();

        if (!$currentUser || !isset($currentUser->user->id)) {
            throw new \Exception('Unauthorized. User not found.');
        }

        return $currentUser;
    }

    /**
     * Validate file upload
     * @throws \Exception if file is invalid
     */
    private static function validateFileUpload($file)
    {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('No valid file uploaded');
        }

        // Optional: Add file size and type validation
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxFileSize) {
            throw new \Exception('File size exceeds maximum allowed size');
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType = mime_content_type($file['tmp_name']);

        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new \Exception('Invalid file type. Only images are allowed');
        }
    }

    /**
     * Process and store uploaded file
     * @return array File information
     * @throws \Exception if file processing fails
     */
    private static function processFileUpload($file)
    {
        $uploadDir = __DIR__ . '/../assets/images/';

        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new \Exception('Failed to create upload directory');
            }
        }

        // Generate unique filename
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uniqueName = Uuid::uuid4()->toString() . '.' . $ext;
        $destination = $uploadDir . $uniqueName;

        // Detect MIME type
        $mimeType = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? 'unknown');

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \Exception('Failed to move uploaded file');
        }

        // Get file size
        $fileSize = filesize($destination);

        if ($fileSize === false) {
            throw new \Exception('Failed to determine file size');
        }

        return [
            'unique_name' => $uniqueName,
            'path' => '/assets/images/' . $uniqueName,
            'mime_type' => $mimeType,
            'size' => $fileSize
        ];
    }

    /**
     * Create media record in database
     * @return array Media data
     * @throws \Exception if database operation fails
     */
    private static function createMediaRecord($title, $description, $fileInfo, $authorId)
    {
        $uuid = Uuid::uuid4()->toString();
        $createdAt = R::isoDateTime();

        try {
            R::exec(
                "INSERT INTO media (title, uuid, description, path, author_id, type, size, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $title,
                    $uuid,
                    $description,
                    $fileInfo['path'],
                    $authorId,
                    $fileInfo['mime_type'],
                    $fileInfo['size'],
                    $createdAt
                ]
            );

            $mediaId = R::getInsertID();

            return [
                'id' => $mediaId,
                'uuid' => $uuid,
                'title' => $title,
                'description' => $description,
                'path' => $fileInfo['path'],
                'type' => $fileInfo['mime_type'],
                'author_id' => $authorId,
                'created_at' => $createdAt,
                'size_bytes' => $fileInfo['size'],
                'size_readable' => self::formatSize($fileInfo['size'])
            ];
        } catch (\Exception $e) {
            // Clean up uploaded file if database insert fails
            $fullPath = __DIR__ . '/../assets/images/' . $fileInfo['unique_name'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            throw new \Exception('Failed to create media record: ' . $e->getMessage());
        }
    }

    public static function getMediaById($id)
    {
        $media = R::findOne('media', 'id = ?', [$id]);
        if (!$media) {
            return null;
        }

        $filePath = __DIR__ . '/../' . ltrim($media->path, '/');
        if (!file_exists($filePath)) {
            return null;
        }

        $fileSize = filesize($filePath);

        return [
            'id'            => $media->id,
            'title'         => $media->title,
            'description'   => $media->description,
            'path'          => $media->path,
            'type'          => $media->type,
            'author_id'     => $media->author_id,
            'created_at'    => $media->created_at,
            'size_bytes'    => $fileSize,
            'size_readable' => self::formatSize($fileSize) // e.g. "2.34 MB"
        ];
    }
}
