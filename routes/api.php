<?php

// API Routes
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/CourseController.php';
require_once __DIR__ . '/../controllers/MediaController.php';
require_once __DIR__ . '/../middleware/jwt.php';
require_once __DIR__ . '/../middleware/instructor.php';

$router = new Router();

$router->add('GET', '/', function () {
    echo json_encode(['message' => 'Upskill Management API']);
});

// ✅ Grouped API routes
$router->group('/api', function ($r, $prefix) {

    // Public routes
    $r->add('GET', $prefix . '/', function () {
        echo json_encode(['message' => 'Welcome to the API']);
    });

    $r->add('POST', $prefix . '/login', function () {
        AuthController::login();
    });

    $r->add('POST', $prefix . '/register', function () {
        AuthController::register();
    });

    // Protected routes
    $r->add('GET', $prefix . '/profile', function () {
        $user = jwt_middleware();
        echo json_encode(['user' => $user]);
    });

    // ✅ Dynamic user route 
    $r->add('GET', $prefix . '/user/<id>', function ($id) {
        UserController::getUserByUsername($id);
    });

    $r->add('POST', $prefix . '/courses', function () {
        instructor_middleware();
        CourseController::create();
    });


    $r->add('GET', $prefix . '/courses/authored', function () {
        instructor_middleware();
        CourseController::getAuthoredCourses();
    });

    $r->add('GET', $prefix . '/courses/<id>', function ($id) {
        CourseController::getCourseByUuid(uuid: $id);
    });



    $r->add('PUT', $prefix . '/courses/<id>', function ($id) {
        CourseController::updateCourseByUuid(uuid: $id);
    });


    $r->add('POST', $prefix . '/media', function () {
        instructor_middleware();
        MediaController::create();
    });
});

// ✅ Dispatch request
$router->dispatch($_SERVER['REQUEST_METHOD'], strtok($_SERVER['REQUEST_URI'], '?'));
