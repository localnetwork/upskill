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
        // ✅ Get the authenticated user
        $currentUser = AuthController::getCurrentUser();
        if (!$currentUser || !isset($currentUser->user->id)) {
            throw new \Exception('Unauthorized. User not found.');
        }

        // ✅ Ensure $data is always an array
        $title       = isset($data['title']) ? $data['title'] : null;
        $description = isset($data['description']) ? $data['description'] : null;

        // ✅ Check if a file was uploaded
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('No valid file uploaded');
        }

        // ✅ Directory to store images
        $uploadDir = __DIR__ . '/../assets/images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // ✅ Create a unique filename (keep original extension)
        $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uniqueName  = Uuid::uuid4()->toString() . '.' . $ext;
        $destination = $uploadDir . $uniqueName;

        // ✅ Detect MIME type (e.g. image/png, image/jpeg)
        $mimeType = mime_content_type($file['tmp_name']) ?: ($file['type'] ?? 'unknown');

        // ✅ Move the file to /assets/images
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \Exception('Failed to move uploaded file');
        }

        // ✅ Get the actual size of the saved file (in bytes)
        $fileSize = filesize($destination);

        // ✅ Store media record in the **medias** table
        $media = R::dispense('medias');
        $media->title       = $title;
        $media->description = $description;
        $media->path        = '/assets/images/' . $uniqueName;
        $media->author_id   = $currentUser->user->id;   // Link to user
        $media->type        = $mimeType;               // Store MIME type
        $media->size        = $fileSize;               // Store size in bytes
        $media->created_at  = R::isoDateTime();

        R::store($media);

        // ✅ Return full response including human-readable size
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

    public static function getMediaById($id)
    {
        $media = R::findOne('medias', 'id = ?', [$id]);
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
