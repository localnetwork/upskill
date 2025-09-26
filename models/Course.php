<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use RedBeanPHP\R; // ✅ Import RedBeanPHP static facade 

class Course
{
    public static function createCourse($data)
    {
        $validator = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'title' => 'required|min:3|max:60',
        ]);
        $validation->validate();

        if ($validation->fails()) {
            return [
                'error'   => true,
                'status'  => 422,
                'errors'  => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.'
            ];
        }

        // 🔹 Base slug
        $baseSlug = preg_replace('/[^A-Za-z0-9-]+/', '-', strtolower($data['title']));
        $baseSlug = trim($baseSlug, '-'); // remove trailing/leading '-'

        // 🔹 Check existing slugs starting with the same base
        $existingSlugs = R::getCol(
            'SELECT slug FROM courses WHERE slug LIKE ?',
            [$baseSlug . '%']
        );

        $slug = $baseSlug;

        if (in_array($baseSlug, $existingSlugs)) {
            // Find max increment
            $max = 0;
            foreach ($existingSlugs as $existing) {
                if (preg_match('/^' . preg_quote($baseSlug, '/') . '-(\d+)$/', $existing, $matches)) {
                    $num = intval($matches[1]);
                    if ($num > $max) $max = $num;
                }
            }
            $slug = $baseSlug . '-' . ($max + 1);
        }

        $currentUser = AuthController::getCurrentUser();

        try {
            $course = R::dispense('courses');
            $course->uuid       = Uuid::uuid4()->toString();
            $course->title      = $data['title'];
            $course->slug       = $slug; // ✅ save incremental slug 
            $course->published  = 0;
            $course->status     = 1;
            $course->created_at = R::isoDateTime();
            $course->updated_at = R::isoDateTime();
            $course->author_id  = $currentUser->user->id;


            R::store($course);

            $data = [
                'title'      => $data['title'],
                'slug'       => $slug,
                'published'  => 0,
                'status'     => 1,
                'uuid'       => $course->uuid,
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
                'author_id'  => $currentUser->user->id
            ];

            return [
                'data'   => $data,
                "message" => "Course created successfully"
            ];
        } catch (Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Server error: ' . $e->getMessage()
            ];
        }
    }


    public static function viewCourseByUUID($uuid)
    {
        $course = R::findOne('courses', 'uuid = ?', [$uuid]);

        if (!$course) {
            http_response_code(404); // ✅ Set actual HTTP status header
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'Course not found.'
            ];
        }

        $data = [
            'title'       => $course->title,
            'subtitle'    => $course->subtitle,
            'description' => $course->description,
            'slug'        => $course->slug,
            'published'   => $course->published,
            'status'      => $course->status,
            'uuid'        => $course->uuid,
            'created_at'  => $course->created_at,
            'updated_at'  => $course->updated_at,
            'author_id'   => $course->author_id
        ];

        http_response_code(200); // ✅ OK response
        return [
            'success' => true,
            'statusCode' => 200,
            'message' => 'Course retrieved successfully.',
            'data' => $data
        ];
    }

    public static function updateCourse($uuid, $data)
    {
        $course = R::findOne('courses', 'uuid = ?', [$uuid]);

        if (!$course) {
            http_response_code(404); // ✅ Set actual HTTP status header 
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'Course not found.'
            ];
        }

        $validator = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'title' => 'required|min:3|max:60',
            'subtitle' => 'max:255',
            'description' => 'min:200|max:1000',
        ]);
        $validation->validate();

        if ($validation->fails()) {
            return [
                'error'   => true,
                'status'  => 422,
                'errors'  => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.'
            ];
        }
    }
}
