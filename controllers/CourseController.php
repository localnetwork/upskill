<?php

require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../controllers/AuthController.php';
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

    public static function updateCourseByUuid($uuid)
    {
        $input  = json_decode(file_get_contents('php://input'), true);
        $result = Course::updateCourse($uuid, $input);

        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'message' => $result['message'] ?? 'An error occurred.',
                'errors'  => $result['errors'] ?? null,
            ]);
            return;
        }
        echo json_encode($result);
    }

    public static function updateCoursePriceByUuid($uuid)
    {
        $input  = json_decode(file_get_contents('php://input'), true);
        $result = Course::updateCoursePrice($uuid, $input);

        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'message' => $result['message'] ?? 'An error occurred.',
                'errors'  => $result['errors'] ?? null,
            ]);
            return;
        }

        echo json_encode($result);
    }

    public static function getCourseByUuid($uuid): void
    {

        echo json_encode(Course::viewCourseByUUID($uuid));
    }

    public static function getAuthoredCourses(): void
    {
        // Pagination params
        $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(1, (int)$_GET['perPage']) : 10;

        // Dynamic filters
        $filters = [];
        if (!empty($_GET['title'])) {
            $filters['title'] = $_GET['title'];
        }
        if (!empty($_GET['instructional_level'])) {
            $filters['instructional_level'] = (int)$_GET['instructional_level'];
        }

        // Fetch courses
        $result = Course::getAuthoredCourses($page, $perPage, $filters);

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    public static function getAllCourses(): void
    {
        // Pagination params
        $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(1, (int)$_GET['perPage']) : 10;

        // Dynamic filters
        $filters = [];
        if (!empty($_GET['title'])) {
            $filters['title'] = $_GET['title'];
        }
        if (!empty($_GET['instructional_level'])) {
            $filters['instructional_level'] = (int)$_GET['instructional_level'];
        }

        // Fetch courses 
        $result = Course::browseCourses($page, $perPage, $filters);

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    public static function getCourseBySlug($slug): void
    {
        echo json_encode(Course::viewBySlug($slug));
    }

    public static function instructorCourses($id)
    {
        // Pagination params
        $page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(1, (int)$_GET['perPage']) : 10;

        // Dynamic filters 
        $filters = [];
        if (!empty($_GET['title'])) {
            $filters['title'] = $_GET['title'];
        }
        if (!empty($_GET['instructional_level'])) {
            $filters['instructional_level'] = (int)$_GET['instructional_level'];
        }

        // Fetch courses 
        $result = Course::getInstructorCourses($id, $page, $perPage, $filters);

        header('Content-Type: application/json');
        echo json_encode($result);
    }
}
