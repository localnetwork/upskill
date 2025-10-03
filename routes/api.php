<?php

// API Routes
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/CourseController.php';
require_once __DIR__ . '/../controllers/CourseGoalController.php';
require_once __DIR__ . '/../controllers/MediaController.php';
require_once __DIR__ . '/../middleware/jwt.php';
require_once __DIR__ . '/../middleware/instructor.php';
require_once __DIR__ . '/../middleware/course.php';
require_once __DIR__ . '/../controllers/CourseSectionController.php';

require_once __DIR__ . '/../controllers/CourseCurriculumController.php';

require_once __DIR__ . '/../controllers/CourseCurriculumVideoController.php';

require_once __DIR__ . '/../controllers/CourseCurriculumArticleController.php';

require_once __DIR__ . '/../controllers/VideoController.php';

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

    $r->add('GET', $prefix . '/courses', function () {
        CourseController::getAllCourses();
    });

    $r->add('POST', $prefix . '/courses', function () {
        instructor_middleware();
        CourseController::create();
    });

    $r->add('POST', $prefix . '/course-sections', function () {
        // instructor_middleware();
        CourseSectionController::createSection();
    });

    $r->add('GET', $prefix . '/course-sections/course/<id>', function ($id) {
        // instructor_middleware();
        CourseSectionController::getSectionsByCourseId($id);
    });

    $r->add('PUT', $prefix . '/course-sections/<id>', function ($id) {
        // instructor_middleware(); 
        CourseSectionController::updateSectionById($id);
    });

    $r->add('DELETE', $prefix . '/course-sections/<id>', function ($id) {
        CourseSectionController::deleteSectionById($id);
    });

    $r->add('GET', $prefix . '/course-sections/<id>/curriculums', function ($id) {
        CourseCurriculumController::getCurriculumsBySectionId($id);
    });

    $r->add('PUT', $prefix . '/course-sections/<id>/curriculums/sort', function ($id) {
        return CourseCurriculumController::sortCurriculums($id);
    });


    $r->add('POST', $prefix . '/course-curriculums', function () {
        CourseCurriculumController::createCurriculum();
    });

    $r->add('POST', $prefix . '/course-resources/videos', function () {
        CourseCurriculumVideoController::addVideoToCurriculum();
    });

    $r->add('DELETE', $prefix . '/course-resources/videos/<id>', function ($id) {
        CourseCurriculumVideoController::deleteVideoFromCurriculum($id);
    });

    $r->add('POST', $prefix . '/course-resources/articles', function () {
        CourseCurriculumArticleController::addArticleToCurriculum();
    });


    $r->add('DELETE', $prefix . '/course-resources/articles/<id>', function ($id) {
        CourseCurriculumArticleController::deleteArticleFromCurriculum($id);
    });

    $r->add('PUT', $prefix . '/course-curriculums/<id>', function ($id) {
        CourseCurriculumController::updateCurriculumById($id);
    });

    $r->add('GET', $prefix . '/course-curriculums/<id>', function ($id) {
        CourseCurriculumController::getCurriculumById($id);
    });

    $r->add('DELETE', $prefix . '/course-curriculums/<id>', function ($id) {
        CourseCurriculumController::deleteCurriculumById($id);
    });


    $r->add('PUT', $prefix . '/courses/<id>/goals', function ($id) {
        instructor_middleware();
        // course_owner_middleware($id);  
        CourseGoalController::updateCourseGoal(id: $id);
    });



    $r->add('GET', $prefix . '/courses/authored', function () {
        instructor_middleware();
        CourseController::getAuthoredCourses();
    });



    $r->add('GET', $prefix . '/courses/<id>', function ($id) {
        instructor_middleware();
        course_owner_middleware($id);
        CourseController::getCourseByUuid($id);
    });



    $r->add('PUT', $prefix . '/courses/<id>', function ($id) {
        CourseController::updateCourseByUuid(uuid: $id);
    });


    $r->add('POST', $prefix . '/media', function () {
        instructor_middleware();
        MediaController::create();
    });

    // $r->add('GET', '/videos/stream/<id>', function ($id) {
    //     VideoController::stream((int) $id);
    // });
});

// ✅ Dispatch request
$router->dispatch($_SERVER['REQUEST_METHOD'], strtok($_SERVER['REQUEST_URI'], '?'));
