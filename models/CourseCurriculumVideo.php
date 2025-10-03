<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

require_once __DIR__ . '/../models/Video.php';
require_once __DIR__ . '/../models/CourseCurriculum.php';

use Ramsey\Uuid\Uuid;
use RedBeanPHP\R;

class CourseCurriculumVideo
{
    /** 🔹 Helper for consistent error responses */
    private static function errorResponse($status, $message, $errors = null)
    {
        return array_filter([
            'error'   => true,
            'status'  => $status,
            'message' => $message,
            'errors'  => $errors
        ]);
    }
    public static function addVideoToCurriculum($input = null)
    {
        $input = $input ?? $_POST;
        $currentUser = AuthController::getCurrentUser();
        $authorId = $currentUser->id ?? $currentUser->user->id ?? null;

        // ✅ Validate file upload
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return self::errorResponse(400, 'No valid file uploaded.');
        }

        $file = $_FILES['file'];
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // ✅ Validate request fields
        $validator = new \Rakit\Validation\Validator();
        $validation = $validator->make($input, [
            'curriculum_id' => 'required|integer',
        ]);
        $validation->validate();

        if ($validation->fails()) {
            return self::errorResponse(422, 'Please check the validated fields.', $validation->errors()->firstOfAll());
        }

        // ✅ Allowed file types 
        $allowedTypes = ['mp4', 'webm', 'avi', 'mov'];
        if (!in_array($fileType, $allowedTypes)) {
            return self::errorResponse(415, 'Invalid file type. Allowed: mp4, webm, avi, mov.');
        }

        try {
            $uuid = Uuid::uuid4()->toString();
            $curriculumId = (int) $input['curriculum_id'];
            $now = date('Y-m-d H:i:s');

            // ✅ Ensure curriculum exists
            $curriculum = R::findOne('course_curriculums', 'id = ?', [$curriculumId]);
            if (!$curriculum) {
                return self::errorResponse(404, 'Curriculum not found.');
            }

            // Delete other contents if resource type is changed to video
            if ($curriculum->curriculum_resource_type !== 'video') {
                R::exec('DELETE FROM course_curriculum_articles WHERE curriculum_id = ?', [$curriculumId]);
            }

            // ✅ Update resource type if not video
            if ($curriculum->curriculum_resource_type !== 'video') {
                R::exec(
                    'UPDATE course_curriculums SET curriculum_resource_type = ? WHERE id = ?',
                    ['video', $curriculumId]
                );
            }

            // ✅ Ensure upload directory
            $uploadDir = __DIR__ . '/../assets/videos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // ✅ File handling
            $fileName = $uuid . '.' . $fileType;
            $filePath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return self::errorResponse(500, 'Failed to move uploaded file.');
            }

            // ✅ Create video record
            $videoTitle = $input['title'] ?? pathinfo($file['name'], PATHINFO_FILENAME);
            $videoId = Video::create([
                'title'     => $videoTitle,
                'path'      => '/assets/videos/' . $fileName,
                'type'      => $fileType,
                'size'      => $file['size'],
                'author_id' => $authorId
            ]);

            if (!$videoId) {
                return self::errorResponse(500, 'Failed to create video record.');
            }

            // ✅ Insert into curriculum_videos
            R::exec(
                'INSERT INTO course_curriculum_videos 
                 (uuid, title, path, type, size, curriculum_id, video_id, created_at, author_id)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [
                    $uuid,
                    $videoTitle,
                    '/assets/videos/' . $fileName,
                    $fileType,
                    $file['size'],
                    $curriculumId,
                    $videoId,
                    $now,
                    $authorId
                ]
            );

            // ✅ Return success  
            return [
                'error'   => false,
                'status'  => 201,
                'message' => 'Video added to curriculum successfully.',
                'data'    => [
                    'asset'      => Video::find($videoId),
                    'curriculum' => CourseCurriculum::getCurriculumById($curriculumId)
                ]
            ];
        } catch (\Exception $e) {
            return self::errorResponse(500, $e->getMessage(), $e->getMessage()); // ⚠ debug field only in dev
        }
    }

    public static function getVideosByCurriculumId($curriculumId)
    {
        try {
            $video = R::findOne('course_curriculum_videos', 'curriculum_id = ?', [$curriculumId]);

            if (!$video) {
                return null;
            }

            // Export record
            $data = R::exportAll($video)[0];

            return $data;
        } catch (\Exception $e) {
            return self::errorResponse(500, 'Database error: ' . $e->getMessage());
        }
    }

    public static function deleteCurriculumVideoById($curriculumId)
    {
        try {
            $video = R::findOne('course_curriculum_videos', 'curriculum_id = ?', [(int) $curriculumId]);

            if (!$video) {
                return null;
            }

            // Delete video file from server
            $filePath = __DIR__ . '/../' . ltrim($video->path, '/');
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete video record
            R::exec('DELETE FROM course_curriculum_videos WHERE curriculum_id = ?', [$curriculumId]);
            Video::delete($video->video_id);

            R::exec(
                'UPDATE course_curriculums SET curriculum_resource_type = ? WHERE id = ?',
                ['null', $curriculumId]
            );

            return [
                'error'   => false,
                'status'  => 200,
                'message' => 'Video deleted from curriculum successfully.',
                'data' => [
                    'curriculum' => CourseCurriculum::getCurriculumById($curriculumId)
                ]
            ];
        } catch (\Exception $e) {
            return self::errorResponse(500, 'Database error: ' . $e->getMessage());
        }
    }

    public static function delete($id)
    {
        try {
            $id = (int) $id;

            $video = R::findOne('course_curriculum_videos', 'curriculum_id = ?', [$id]);

            if (!$video) {
                return self::errorResponse(404, 'Video not found.');
            }

            // Delete video file from server
            $filePath = __DIR__ . '/../' . ltrim($video->path, '/');
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete video record
            R::exec('DELETE FROM course_curriculum_videos WHERE id = ?', [$id]);
            Video::delete($video->video_id);

            return [
                'error'   => false,
                'status'  => 200,
                'message' => 'Video deleted successfully.'
            ];
        } catch (\Exception $e) {
            return self::errorResponse(500, 'Database error: ' . $e->getMessage());
        }
    }

    public static function getCurriculumVideoCounts($curriculumIds)
    {
        try {
            if (empty($curriculumIds) || !is_array($curriculumIds)) {
                return 0;
            }

            $placeholders = implode(',', array_fill(0, count($curriculumIds), '?'));
            $query = "SELECT COUNT(*) as total 
                  FROM course_curriculum_videos
                  WHERE curriculum_id IN ($placeholders)";

            $total = R::getCell($query, $curriculumIds);

            return (int) $total;
        } catch (\Exception $e) {
            return 0; // or handle via your error response
        }
    }
}
