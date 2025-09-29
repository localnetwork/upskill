<?php

require_once __DIR__ . '/../models/CourseCurriculum.php';

class CourseCurriculumController
{
    public static function createCurriculum()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $result = CourseCurriculum::createCurriculum($data);

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
