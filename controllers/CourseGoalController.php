<?php

require_once __DIR__ . '/../models/CourseGoal.php';
class CourseGoalController
{
    // Controller methods would go  
    public static function updateCourseGoal($id)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $result = CourseGoal::updateCourseGoal($id, $input);

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
