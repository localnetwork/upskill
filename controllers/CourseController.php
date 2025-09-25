<?php

require_once __DIR__ . '/../models/Course.php';

class CourseController
{
    // Controller methods here
    public static function create()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $result = Course::createCourse($input);

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

    public static function getCourseByUuid($uuid): void
    {
        echo json_encode(Course::viewCourseByUUID($uuid));
    }
}
