<?php


require_once __DIR__ . '/../models/CourseSection.php';
require_once __DIR__ . '/../controllers/AuthController.php';
class CourseSectionController
{
    public static function createSection()
    {


        // Implementation for creating a course section
        $input = json_decode(file_get_contents('php://input'), true);
        $result = CourseSection::create($input);
        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'errors' => $result['errors'] ?? null,
                'message' => $result['message'] ?? 'An error occurred.'
            ]);
            return;
        }

        echo json_encode($result);
    }

    public static function getSectionsByCourseId($courseId)
    {
        $result = CourseSection::getSectionsByCourseId($courseId);
        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'errors' => $result['errors'] ?? null,
                'message' => $result['message'] ?? 'An error occurred.'
            ]);
            return;
        }

        echo json_encode($result);
    }

    public static function updateSectionById($id)
    {
        // Implementation for updating a course section
        $data = json_decode(file_get_contents('php://input'), true);
        $result = CourseSection::updateSectionById($id, $data);
        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'errors' => $result['errors'] ?? null,
                'message' => $result['message'] ?? 'An error occurred.'
            ]);
            return;
        }

        echo json_encode($result);
    }

    public static function deleteSectionById($id)
    {
        $result = CourseSection::deleteSectionById($id);
        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'errors' => $result['errors'] ?? null,
                'message' => $result['message'] ?? 'An error occurred.'
            ]);
            return;
        }

        echo json_encode($result);
    }
}
