<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../controllers/AuthController.php';

use Ramsey\Uuid\Uuid;
use RedBeanPHP\R;

class CourseGoal
{
    protected static $table = 'course_goals';

    public static function updateCourseGoal($id, array $data)
    {
        // ✅ Auth check 
        $course = R::findOne('courses', 'id = ?', [$id]);
        if (!$course) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Course not found.'
            ];
        }

        // $currentUser = AuthController::getCurrentUser();

        // if (!$currentUser->user->id !== $course->author_id) {
        //     http_response_code(403);
        //     return [
        //         'errors'   => true,
        //         'status'  => 403,
        //         'message' => 'You are not allowed to update this course.'
        //     ];
        // }


        // ✅ Validate required fields
        $requiredFields = [
            'requirements_data',
            'what_you_will_learn_data',
            'who_should_attend_data',
        ];
        $missing = array_filter($requiredFields, fn($f) => !array_key_exists($f, $data));
        if ($missing) {
            http_response_code(400);
            return [
                'error'   => true,
                'status'  => 400,
                'message' => 'Validation errors occurred.',
                'errors'  => array_map(fn($f) => "The field '$f' is required.", $missing)
            ];
        }

        // ✅ Encode arrays → JSON (empty array if not valid)
        $encode = fn($key) => json_encode(
            is_array($data[$key]) ? $data[$key] : [],
            JSON_UNESCAPED_UNICODE
        );

        $requirements = $encode('requirements_data');
        $whatLearn    = $encode('what_you_will_learn_data');
        $whoAttend    = $encode('who_should_attend_data');
        $now          = R::isoDateTime();

        try {
            // ✅ Check if course_goals record exists for this course
            $exists = R::findOne(self::$table, 'course_id = ?', [$id]);

            if ($exists) {
                // 🔄 UPDATE
                R::exec(
                    "UPDATE " . self::$table . "
                     SET requirements_data = ?,  
                         what_you_will_learn_data = ?, 
                         who_should_attend_data = ?, 
                         updated_at = ?
                     WHERE course_id = ?",
                    [$requirements, $whatLearn, $whoAttend, $now, $id]
                );

                http_response_code(200);
                return [
                    'success' => true,
                    'status'  => 200,
                    'message' => 'Course goal updated successfully.',
                    'data'    => [
                        'course_id'               => (int) $id,
                        'requirements_data'       => json_decode($requirements, true),
                        'what_you_will_learn_data' => json_decode($whatLearn, true),
                        'who_should_attend_data'  => json_decode($whoAttend, true),
                        'updated_at'              => $now
                    ]
                ];
            }

            // 🆕 CREATE
            $uuid = Uuid::uuid4()->toString();
            R::exec(
                "INSERT INTO " . self::$table . " 
                (uuid, requirements_data, what_you_will_learn_data, who_should_attend_data, course_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$uuid, $requirements, $whatLearn, $whoAttend, $id, $now, $now]
            );

            http_response_code(201);
            return [
                'success' => true,
                'status'  => 201,
                'message' => 'Course goal created successfully.',
                'data'    => [
                    'uuid'                    => $uuid,
                    'course_id'               => (int) $id,
                    'requirements_data'       => json_decode($requirements, true),
                    'what_you_will_learn_data' => json_decode($whatLearn, true),
                    'who_should_attend_data'  => json_decode($whoAttend, true),
                    'created_at'              => $now
                ]
            ];
        } catch (\Throwable $e) {
            http_response_code(500);
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Failed to save course goal.',
                'details' => $e->getMessage()
            ];
        }
    }

    public static function getCourseGoalByCourseId($courseId)
    {
        $goal = R::findOne(self::$table, 'course_id = ?', [$courseId]);
        if (!$goal) {
            return null;
        }

        return [
            'uuid'                    => $goal->uuid,
            'requirements_data'       => json_decode($goal->requirements_data, true),
            'what_you_will_learn_data' => json_decode($goal->what_you_will_learn_data, true),
            'who_should_attend_data'  => json_decode($goal->who_should_attend_data, true),
            'course_id'               => (int) $goal->course_id,
            'created_at'              => $goal->created_at,
            'updated_at'              => $goal->updated_at
        ];
    }
}
