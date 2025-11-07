<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

require_once __DIR__ . '/../models/Media.php';
require_once __DIR__ . '/../models/CourseLevel.php';
require_once __DIR__ . '/../models/CourseGoal.php';

require_once __DIR__ . '/../models/CourseSection.php';

require_once __DIR__ . '/../models/OrderLine.php';

require_once __DIR__ . '/../models/Video.php';

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use RedBeanPHP\R; // ✅ Import RedBeanPHP static facade 

class Course
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

        $courseGoals = CourseGoal::getCourseGoalByCourseId($course->id);

        $data = [
            'id'          => $course->id,
            'title'       => $course->title,
            'subtitle'    => $course->subtitle,
            'description' => $course->description,
            'slug'        => $course->slug,
            'published'   => $course->published,
            'status'      => $course->status,
            'uuid'        => $course->uuid,
            'created_at'  => $course->created_at,
            'updated_at'  => $course->updated_at,
            'author_id'   => $course->author_id,
            'cover_image' => Media::getMediaById($course->cover_image), // Fetch cover image details
            'price_tier' => CoursePriceTier::getCoursePriceTierById($course->price_tier),
            'instructional_level' => $course->instructional_level,
            'goals'       => $courseGoals,
            'promo_video' => Video::find($course->promo_video)
        ];

        http_response_code(200); // ✅ OK response   
        return [
            'success' => true,
            'statusCode' => 200,
            'message' => 'Course retrieved successfully.',
            'data' => $data
        ];
    }

    public static function viewCourseByID($id)
    {
        $course = R::findOne('courses', 'id = ?', [$id]);


        if (!$course) {
            http_response_code(404); // ✅ Set actual HTTP status header
            return [
                'success' => false,
                'statusCode' => 404,
                'message' => 'Course not found.'
            ];
        }

        $courseGoals = CourseGoal::getCourseGoalByCourseId($course->id);

        $data = [
            'id'          => $course->id,
            'title'       => $course->title,
            'subtitle'    => $course->subtitle,
            'description' => $course->description,
            'slug'        => $course->slug,
            'published'   => $course->published,
            'status'      => $course->status,
            'uuid'        => $course->uuid,
            'created_at'  => $course->created_at,
            'updated_at'  => $course->updated_at,
            'author_id'   => $course->author_id,
            'author'      => User::getPublicProfileById($course->author_id),
            'cover_image' => Media::getMediaById($course->cover_image), // Fetch cover image details
            'price_tier' => CoursePriceTier::getCoursePriceTierById($course->price_tier),
            'instructional_level' => $course->instructional_level,
            'goals'       => $courseGoals // Include course goals 
        ];

        http_response_code(200); // ✅ OK response   
        return [
            'success' => true,
            'statusCode' => 200,
            'message' => 'Course retrieved successfully.',
            'data' => $data
        ];
    }

    public static function updateCourse($uuid, $data = null)
    {
        // Parse JSON body if $data not provided 
        if (!is_array($data)) {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                http_response_code(400);
                return [
                    'error'   => true,
                    'status'  => 400,
                    'message' => 'Invalid request body.'
                ];
            }
        }

        // Auth check 
        $currentUser = AuthController::getCurrentUser();
        if (!$currentUser || !isset($currentUser->user)) {
            http_response_code(403);
            return [
                'error'   => true,
                'status'  => 403,
                'message' => 'Access denied.'
            ];
        }

        // Find course
        $course = R::findOne('courses', 'uuid = ?', [$uuid]);
        if (!$course) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Course not found.'
            ];
        }

        $courseGoals = CourseGoal::getCourseGoalByCourseId($course->id);

        // Authorization
        if ((int)$currentUser->user->id !== (int)$course->author_id) {
            http_response_code(403);
            return [
                'error'   => true,
                'status'  => 403,
                'message' => 'You are not authorized to update this course.'
            ];
        }

        // Validation
        $validator  = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'title'               => 'required|min:3|max:60',
            'subtitle'            => 'max:255',
            'description'         => 'min:200|max:10000',
            'instructional_level' => 'numeric', // optional numeric FK
        ]);
        $validation->validate();

        if ($validation->fails()) {
            http_response_code(422);
            return [
                'error'   => true,
                'status'  => 422,
                'errors'  => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.'
            ];
        }

        // Safely update string fields
        $course->title = is_array($data['title']) ? json_encode($data['title']) : trim($data['title']);
        $course->subtitle = isset($data['subtitle'])
            ? (is_array($data['subtitle']) ? json_encode($data['subtitle']) : trim($data['subtitle']))
            : $course->subtitle;

        $course->description = isset($data['description'])
            ? (is_array($data['description']) ? json_encode($data['description']) : trim($data['description']))
            : $course->description;

        // Instructional level FK validation
        if (!empty($data['instructional_level']) && !is_array($data['instructional_level'])) {
            $levelId = (int)$data['instructional_level'];
            $level = R::findOne('course_levels', 'id = ?', [$levelId]);
            if ($level) {
                $course->instructional_level = $levelId;
            }
            // else: invalid FK, retain existing value 
        }

        // Cover Image (optional)
        if (!empty($data['cover_image']) && !is_array($data['cover_image'])) {
            $course->cover_image = (int)$data['cover_image'];
        }

        echo json_encode($data['promo_video']);

        if (!empty($data['promo_video']) && !is_array($data['promo_video'])) {
            $course->promo_video = (int)$data['promo_video'];
        }

        $course->updated_at = R::isoDateTime();
        R::store($course);

        http_response_code(200);
        return [
            'success' => true,
            'status'  => 200,
            'message' => 'Course updated successfully.',
            'data'    => [
                'id'          => $course->id,
                'title'       => $course->title,
                'subtitle'    => $course->subtitle,
                'description' => $course->description,
                'slug'        => $course->slug,
                'published'   => $course->published,
                'status'      => $course->status,
                'uuid'        => $course->uuid,
                'created_at'  => $course->created_at,
                'updated_at'  => $course->updated_at,
                'author_id'   => $course->author_id,
                'cover_image' => Media::getMediaById($course->cover_image), // Fetch cover image details
                'instructional_level' => CourseLevel::getCourseLevelById($course->instructional_level),
                'promo_video' => Video::find($course->promo_video),
                'goals'       => $courseGoals // Include course goals
            ]
        ];
    }

    public static function updateCoursePrice($uuid, $data = null)
    {
        // Parse JSON body if $data not provided 
        if (!is_array($data)) {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                http_response_code(400);
                return [
                    'error'   => true,
                    'status'  => 400,
                    'message' => 'Invalid request body.'
                ];
            }
        }
        $validator = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'price_tier' => 'required|min:1',
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


        // Auth check 
        $currentUser = AuthController::getCurrentUser();
        if (!$currentUser || !isset($currentUser->user)) {
            http_response_code(403);
            return [
                'error'   => true,
                'status'  => 403,
                'message' => 'Access denied.'
            ];
        }

        // Find course
        $course = R::findOne('courses', 'uuid = ?', [$uuid]);
        if (!$course) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Course not found.'
            ];
        }

        $priceTier = CoursePriceTier::getCoursePriceTierById($data['price_tier']);

        if (!$priceTier) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Price tier not found.'
            ];
        }

        // Authorization
        if ((int)$currentUser->user->id !== (int)$course->author_id) {
            http_response_code(403);
            return [
                'error'   => true,
                'status'  => 403,
                'message' => 'You are not authorized to update this course.'
            ];
        }



        // Price tier FK validation
        $tierId = (int)$data['price_tier'];
        $tier = R::exec('UPDATE courses SET price_tier = ?, updated_at = ? WHERE uuid = ?', [
            $tierId,
            R::isoDateTime(),
            $course->uuid
        ]);

        return [
            'message' => 'Course price updated successfully.',
            'data' => $course,
        ];
    }

    public static function getAuthoredCourses($page = 1, $perPage = 10, $filters = [])
    {
        // Auth check
        $currentUser = AuthController::getCurrentUser();
        if (!$currentUser || !isset($currentUser->user)) {
            http_response_code(401);
            return [
                'error'   => true,
                'status'  => 401,
                'message' => 'Access denied.'
            ];
        }

        $page    = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset  = ($page - 1) * $perPage;

        // Build where conditions dynamically
        $conditions = ['author_id = ?'];
        $params     = [$currentUser->user->id];

        if (!empty($filters['title'])) {
            $conditions[] = 'title LIKE ?';
            $params[] = '%' . $filters['title'] . '%';
        }

        if (!empty($filters['instructional_level'])) {
            $conditions[] = 'instructional_level = ?';
            $params[] = (int)$filters['instructional_level'];
        }

        $whereClause = implode(' AND ', $conditions);

        // Total courses with filters
        $totalCourses = R::count('courses', $whereClause, $params);


        // Fetch paginated results
        $coursesCollection = R::findAll(
            'courses',
            "$whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        // Convert to array
        $courses = R::exportAll($coursesCollection);

        // Attach only existing details
        foreach ($courses as &$course) {
            // Attach cover_image only if it exists
            if (!empty($course['cover_image'])) {
                $cover = Media::getMediaById($course['cover_image']);
                if ($cover) {
                    $course['cover_image'] = $cover;
                } else {
                    unset($course['cover_image']);
                }
            }

            // Attach instructional_level only if it exists
            if (!empty($course['instructional_level'])) {
                $level = CourseLevel::getCourseLevelById($course['instructional_level']);
                if ($level) {
                    $course['instructional_level'] = $level;
                } else {
                    unset($course['instructional_level']);
                }
            }
        }

        $totalPages = ceil($totalCourses / $perPage);

        http_response_code(200);
        return [
            'success'    => true,
            'status'     => 200,
            'data'       => $courses,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total_pages'  => $totalPages,
                'total_items'  => $totalCourses,
            ],
        ];
    }

    public static function browseCourses($page = 1, $perPage = 10, $filters = [])
    {
        try {
            $page    = max(1, (int)$page);
            $perPage = max(1, (int)$perPage);
            $offset  = ($page - 1) * $perPage;



            // Build where conditions dynamically
            $conditions = ['published = 1'];
            $params     = [];

            if (!empty($filters['title'])) {
                $conditions[] = 'title LIKE ?';
                $params[] = '%' . $filters['title'] . '%';
            }

            if (!empty($filters['instructional_level'])) {
                $conditions[] = 'instructional_level = ?';
                $params[] = (int)$filters['instructional_level'];
            }

            $whereClause = 'WHERE ' . implode(' AND ', $conditions);

            // Total courses for pagination
            $totalCourses = R::count('courses', implode(' AND ', $conditions), $params);

            // Build SQL
            $limit  = (int) $perPage;
            $offsetVal = (int) $offset;
            $sql = "$whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offsetVal";

            // Fetch paginated courses
            $courses = R::findAll('courses', $sql, $params);

            // Export beans to arrays
            $courseData = R::exportAll($courses);

            // Attach section counts   
            foreach ($courseData as &$row) {
                $row['author'] = User::getPublicProfileById($row['author_id']);
                $row['cover_image'] = Media::getMediaById($row['cover_image']);
                $row['instructional_level'] = CourseLevel::getCourseLevelById($row['instructional_level']);
                $row['resources_count'] = CourseSection::getCourseSectionCount((int)$row['id']);
                $row['goals'] = CourseGoal::getCourseGoalByCourseId($row['id']);
                $row['price_tier'] = $row['price_tier'] ? CoursePriceTier::getCoursePriceTierById($row['price_tier']) : null;
                $row['is_in_cart'] = Cart::checkCourseInCart($row['id']) || false;
                $row['is_enrolled'] = OrderLine::checkCourseEnrolled($row['id']);
            }
            unset($row);

            return [
                'error' => false,
                'message' => 'Courses retrieved successfully.',
                'meta' => [
                    'page'       => $page,
                    'perPage'    => $perPage,
                    'total'      => (int)$totalCourses,
                    'totalPages' => ($perPage > 0 ? (int)ceil($totalCourses / $perPage) : 1),
                ],
                'data' => $courseData,
            ];
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Database error: ' . $e->getMessage(),
            ];
        }
    }

    public static function viewBySlug($slug)
    {
        $course = R::findOne('courses', 'slug = ?', [$slug]);
        if (!$course) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Course not found.'
            ];
        }

        // Convert bean to array
        $courseArr = $course->export();

        // Add extra data
        $courseArr['author']              = User::getPublicProfileById($course->author_id);
        $courseArr['cover_image']         = Media::getMediaById($course->cover_image);
        $courseArr['instructional_level'] = CourseLevel::getCourseLevelById($course->instructional_level);
        $courseArr['resources_count']     = CourseSection::getCourseSectionCount((int) $course->id);
        $courseArr['goals']               = CourseGoal::getCourseGoalByCourseId($course->id);
        $courseArr['sections']            = CourseSection::getSectionsDataByCourseId((int) $course->id);
        $courseArr['is_in_cart']          = Cart::checkCourseInCart($course->id) || false;
        // $courseArr['is_enrolled']       =  OrderLine::checkCourseEnrolled($course->id) || false;
        $courseArr['price_tier']         = CoursePriceTier::getCoursePriceTierById($course->price_tier);
        $courseArr['is_enrolled']        = OrderLine::checkCourseEnrolled($course->id);

        return $courseArr;
    }

    public static function getInstructorCourses($instructorId, $page = 1, $perPage = 10, $filters = [])
    {
        try {
            $page    = max(1, (int)$page);
            $perPage = max(1, (int)$perPage);
            $offset  = ($page - 1) * $perPage;

            // Build where conditions dynamically 
            $conditions = ['published = 1'];
            $params     = [];

            if (!empty($filters['title'])) {
                $conditions[] = 'title LIKE ?';
                $params[] = '%' . $filters['title'] . '%';
            }

            if (!empty($filters['instructional_level'])) {
                $conditions[] = 'instructional_level = ?';
                $params[] = (int)$filters['instructional_level'];
            }

            if (!empty($instructorId)) {
                $conditions[] = 'author_id = ?';
                $params[] = (int)$instructorId;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $conditions);

            // Total courses for pagination
            $totalCourses = R::count('courses', implode(' AND ', $conditions), $params);

            // Build SQL
            $limit  = (int) $perPage;
            $offsetVal = (int) $offset;
            $sql = "$whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offsetVal";

            // Fetch paginated courses
            $courses = R::findAll('courses', $sql, $params);

            // Export beans to arrays
            $courseData = R::exportAll($courses);

            // Attach section counts   
            foreach ($courseData as &$row) {
                $row['author'] = User::getPublicProfileById($row['author_id']);
                $row['cover_image'] = Media::getMediaById($row['cover_image']);
                $row['instructional_level'] = CourseLevel::getCourseLevelById($row['instructional_level']);
                $row['resources_count'] = CourseSection::getCourseSectionCount((int)$row['id']);
                $row['goals'] = CourseGoal::getCourseGoalByCourseId($row['id']);
                $row['price_tier'] = $row['price_tier'] ? CoursePriceTier::getCoursePriceTierById($row['price_tier']) : null;
                $row['is_in_cart'] = Cart::checkCourseInCart($row['id']) || false;
                $row['is_enrolled'] = OrderLine::checkCourseEnrolled($row['id']);
            }
            unset($row);

            return [
                'error' => false,
                'message' => 'Courses retrieved successfully.',
                'meta' => [
                    'page'       => $page,
                    'perPage'    => $perPage,
                    'total'      => (int)$totalCourses,
                    'totalPages' => ($perPage > 0 ? (int)ceil($totalCourses / $perPage) : 1),
                ],
                'data' => $courseData,
            ];
        } catch (\Exception $e) {
            return [
                'error'   => true,
                'status'  => 500,
                'message' => 'Database error: ' . $e->getMessage(),
            ];
        }
    }

    public static function learnCourse($slug)
    {
        $course = R::findOne('courses', 'slug = ?', [$slug]);
        if (!$course) {
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Course not found.'
            ];
        }

        $courseId = $course->id;

        $is_enrolled = OrderLine::checkCourseEnrolled($courseId);

        if (!$is_enrolled) {
            return [
                'error'   => true,
                'status'  => 403,
                'message' => 'You are not enrolled in this course.'
            ];
        }


        return [
            'error'   => false,
            'status'  => 200,
            'message' => 'Access granted to learn the course.',
            'data'    => [
                'course' => [
                    ...$course->export(),
                    'author'              => User::getPublicProfileById($course->author_id),
                    'resources_count'     => CourseSection::getCourseSectionCount((int) $course->id),
                    'instructional_level' => CourseLevel::getCourseLevelById($course->instructional_level),
                    'sections' => CourseSection::getSectionsDataByCourseId((int) $course->id, $showAsset = true)
                ]
            ]
        ];
    }

    public static function uploadPromoVideo($data)
    {

        $input = $input ?? $_POST;
        $currentUser = AuthController::getCurrentUser();
        $authorId = $currentUser->id ?? $currentUser->user->id ?? null;

        // ✅ Validate file upload
        if (empty($_FILES['promo_video']) || $_FILES['promo_video']['error'] !== UPLOAD_ERR_OK) {
            return self::errorResponse(400, 'No valid file uploaded.');
        }

        $file = $_FILES['promo_video'];
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // ✅ Validate request fields
        $validator = new \Rakit\Validation\Validator();
        $validation = $validator->make($input, [
            'course_uuid' => 'required',
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
            $course_uuid = (int) $input['course_uuid'];
            $now = date('Y-m-d H:i:s');

            // ✅ Ensure course exists
            $course = R::findOne('courses', 'uuid = ?', [$course_uuid]);
            if (!$course) {
                return self::errorResponse(404, 'Course not found.');
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

            $video = Video::find($videoId);

            return $video;
        } catch (\Exception $e) {
            return self::errorResponse(500, $e->getMessage(), $e->getMessage()); // ⚠ debug field only in dev
        }
    }

    public static function unpublish($uuid)
    {
        $currentUser = AuthController::getCurrentUser();
        if (!$currentUser || !isset($currentUser->user)) {
            return [
                'error'   => true,
                'status'  => 403,
                'message' => 'Access denied.'
            ];
        }



        $course = R::findOne('courses', 'uuid = ?', [$uuid]);


        if ($course->published == 0) {
            return [
                'error'   => true,
                'status'  => 400,
                'message' => 'Course is already drafted. Please refresh your page and try again.'
            ];
        }

        if ($currentUser->user->id !== $course->author_id) {
            return [
                'error'   => true,
                'status'  => 403,
                'message' => 'You are not authorized to unpublish this course.'
            ];
        }


        if (!$course) {
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Course not found.'
            ];
        }

        $course->published = 0;
        $course->updated_at = R::isoDateTime();
        R::store($course);

        return [
            'error'   => false,
            'status'  => 200,
            'message' => 'Course unpublished successfully.'
        ];
    }

    public static function publish($uuid)
    {
        $currentUser = AuthController::getCurrentUser();
        if (!$currentUser || !isset($currentUser->user)) {
            return [
                'error'   => true,
                'status'  => 403,
                'message' => 'Access denied.'
            ];
        }



        $course = R::findOne('courses', 'uuid = ?', [$uuid]);


        if ($course->published == 0) {
            return [
                'error'   => true,
                'status'  => 400,
                'message' => 'Course is already unpublished.'
            ];
        }

        if ($currentUser->user->id !== $course->author_id) {
            return [
                'error'   => true,
                'status'  => 403,
                'message' => 'You are not authorized to unpublish this course.'
            ];
        }


        if (!$course) {
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'Course not found.'
            ];
        }

        $course->published = 1;
        $course->updated_at = R::isoDateTime();
        R::store($course);

        return [
            'error'   => false,
            'status'  => 200,
            'message' => 'Course unpublished successfully.'
        ];
    }
}
