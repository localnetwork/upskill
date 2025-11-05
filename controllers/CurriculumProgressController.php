<?php

require_once __DIR__ . '/../models/CurriculumProgress.php';

require_once __DIR__ . '/../models/OrderLine.php';

class CurriculumProgressController
{
    public static function addProgress()
    {
        header('Content-Type: application/json');

        // ✅ Parse and sanitize input
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);

        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Invalid JSON input.'
            ]);
            exit;
        }

        // ✅ Validate required fields 
        $validator = new \Rakit\Validation\Validator;
        $validation = $validator->make($input, [
            'curriculum_id' => 'required|numeric',
            'course_id'     => 'required|numeric',
        ]);

        $validation->validate();

        if ($validation->fails()) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Validation failed.',
                'errors'  => $validation->errors()->firstOfAll()
            ]);
            exit;
        }

        // ✅ Check if user is authenticated and enrolled
        $is_enrolled = OrderLine::checkCourseEnrolled($input['course_id']);
        if (!$is_enrolled) {
            http_response_code(403);
            echo json_encode([
                'status'  => 'error',
                'message' => 'You are not enrolled in this course.'
            ]);
            exit;
        }

        // ✅ Attempt to add progress
        try {
            $result = CurriculumProgress::add($input);

            if (!$result) {
                http_response_code(500);
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Failed to add progress. Please try again later.'
                ]);
                exit;
            }

            // ✅ Return success
            echo json_encode([
                'status'  => 'success',
                'message' => 'Progress added successfully.',
                'data'    => $result
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'An unexpected error occurred.',
                'details' => $e->getMessage()
            ]);
            exit;
        }
    }
}
