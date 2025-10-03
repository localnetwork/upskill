<?php

require_once __DIR__ . '/../models/CourseCurriculumVideo.php';

class CourseCurriculumVideoController
{
    public static function addVideoToCurriculum()
    {
        // 🚀 Pass $_POST automatically (from multipart/form-data)
        $result = CourseCurriculumVideo::addVideoToCurriculum($_POST);

        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'errors'  => $result['errors'] ?? null,
                'message' => $result['message'] ?? 'An error occurred.'
            ]);
            return;
        }

        http_response_code($result['status'] ?? 200);
        echo json_encode($result);
    }

    public static function deleteVideoFromCurriculum($id)
    {
        $result = CourseCurriculumVideo::deleteCurriculumVideoById($id);

        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'errors'  => $result['errors'] ?? null,
                'message' => $result['message'] ?? 'An error occurred.'
            ]);
            return;
        }

        http_response_code($result['status'] ?? 200);
        echo json_encode($result);
    }
}
