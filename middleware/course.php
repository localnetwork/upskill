<?php

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../models/Course.php';

use RedBeanPHP\R;

function course_owner_middleware($id)
{
    $currentUser = AuthController::getCurrentUser();
    $course = R::findOne('courses', 'uuid = ?', [$id]);

    if (!$course) {
        http_response_code(404);
        echo json_encode([
            'error'   => true,
            'status'  => 404,
            'message' => 'Course not found.'
        ]);
        exit; // 🔴 Stop everything
    }

    // Debug log (remove in production)
    // var_dump($currentUser->user->id, $course->author_id);

    if ((int)$currentUser->user->id !== (int)$course->author_id) {
        http_response_code(403); // 403 Forbidden is more appropriate
        echo json_encode([
            'error'   => true,
            'status'  => 403,
            'message' => 'You are not allowed to access this course.',
            'current_user' => $currentUser->user->id,
            'firstname' => $currentUser->user->firstname,
            'author_id' => $course->author_id
        ]);
        exit; // 🔴 Must exit to prevent next middleware 
    }
}
