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
require_once __DIR__ . '/../controllers/CartController.php';
require_once __DIR__ . '/../controllers/CoursePriceTierController.php';
require_once __DIR__ . '/../controllers/CheckoutController.php';
require_once __DIR__ . '/../controllers/OrderController.php';

$router = new Router();

$router->add('GET', '/', function () {
    echo json_encode(['message' => 'Upskill Management API']);
});

// ✅ Grouped API routes
$router->group('/api', function ($r, $prefix) {

    // ==================== PUBLIC ROUTES ====================
    $r->add('GET', $prefix . '/', function () {
        echo json_encode(['message' => 'Welcome to the API']);
    });

    // Auth Routes
    $r->add('POST', $prefix . '/login', function () {
        AuthController::login();
    });

    $r->add('POST', $prefix . '/register', function () {
        AuthController::register();
    });

    // ==================== USER ROUTES ====================
    $r->add('GET', $prefix . '/profile', function () {
        $user = jwt_middleware();
        echo json_encode(['user' => $user]);
    });

    $r->add('PUT', $prefix . '/profile', function () {
        jwt_middleware();
        UserController::update();
    });

    $r->add('PUT', $prefix . '/profile/user-picture', function () {
        jwt_middleware();
        UserController::uploadProfilePicture();
    });

    $r->add('GET', $prefix . '/user/<id>', function ($id) {
        UserController::getUserByUsername($id);
    });

    // ==================== COURSE ROUTES ====================
    // Public Course Routes
    $r->add('GET', $prefix . '/courses', function () {
        CourseController::getAllCourses();
    });

    $r->add('GET', $prefix . '/courses/route/<slug>', function ($slug) {
        CourseController::getCourseBySlug($slug);
    });

    // Instructor Course Routes
    $r->add('GET', $prefix . '/courses/authored', function () {
        instructor_middleware();
        CourseController::getAuthoredCourses();
    });

    $r->add('POST', $prefix . '/courses', function () {
        instructor_middleware();
        CourseController::create();
    });

    $r->add('GET', $prefix . '/instructor/courses/<id>', function ($id) {
        CourseController::instructorCourses($id);
    });

    $r->add('GET', $prefix . '/courses/<id>', function ($id) {
        instructor_middleware();
        course_owner_middleware($id);
        CourseController::getCourseByUuid($id);
    });

    $r->add('PUT', $prefix . '/courses/<id>', function ($id) {
        CourseController::updateCourseByUuid(uuid: $id);
    });

    $r->add('PUT', $prefix . '/courses/<id>/pricing', function ($id) {
        instructor_middleware();
        CourseController::updateCoursePriceByUuid(uuid: $id);
    });


    $r->add('PUT', $prefix . '/courses/<id>/goals', function ($id) {
        instructor_middleware();
        CourseGoalController::updateCourseGoal(id: $id);
    });

    // ==================== COURSE SECTION ROUTES ====================
    $r->add('POST', $prefix . '/course-sections', function () {
        CourseSectionController::createSection();
    });

    $r->add('GET', $prefix . '/course-sections/course/<id>', function ($id) {
        CourseSectionController::getSectionsByCourseId($id);
    });

    $r->add('GET', $prefix . '/course-sections/<id>/curriculums', function ($id) {
        CourseCurriculumController::getCurriculumsBySectionId($id);
    });

    $r->add('PUT', $prefix . '/course-sections/<id>', function ($id) {
        CourseSectionController::updateSectionById($id);
    });

    $r->add('PUT', $prefix . '/course-sections/<id>/curriculums/sort', function ($id) {
        return CourseCurriculumController::sortCurriculums($id);
    });

    $r->add('DELETE', $prefix . '/course-sections/<id>', function ($id) {
        CourseSectionController::deleteSectionById($id);
    });

    // ==================== COURSE CURRICULUM ROUTES ====================
    $r->add('POST', $prefix . '/course-curriculums', function () {
        CourseCurriculumController::createCurriculum();
    });

    $r->add('GET', $prefix . '/course-curriculums/<id>', function ($id) {
        CourseCurriculumController::getCurriculumById($id);
    });

    $r->add('PUT', $prefix . '/course-curriculums/<id>', function ($id) {
        CourseCurriculumController::updateCurriculumById($id);
    });

    $r->add('DELETE', $prefix . '/course-curriculums/<id>', function ($id) {
        CourseCurriculumController::deleteCurriculumById($id);
    });

    // ==================== COURSE RESOURCES ROUTES ====================
    // Video Resources
    $r->add('POST', $prefix . '/course-resources/videos', function () {
        CourseCurriculumVideoController::addVideoToCurriculum();
    });

    $r->add('DELETE', $prefix . '/course-resources/videos/<id>', function ($id) {
        CourseCurriculumVideoController::deleteVideoFromCurriculum($id);
    });

    // Article Resources
    $r->add('POST', $prefix . '/course-resources/articles', function () {
        CourseCurriculumArticleController::addArticleToCurriculum();
    });

    $r->add('DELETE', $prefix . '/course-resources/articles/<id>', function ($id) {
        CourseCurriculumArticleController::deleteArticleFromCurriculum($id);
    });


    // ==================== COURSE PRICE TIERS ====================
    $r->add('GET', $prefix . '/course-price-tiers', function () {
        CoursePriceTierController::get();
    });


    // ==================== MEDIA ROUTES ====================
    $r->add('POST', $prefix . '/media', function () {
        instructor_middleware();
        MediaController::create();
    });

    // ==================== CART ROUTES ====================
    $r->add('POST', $prefix . '/cart', function () {
        jwt_middleware();
        CartController::addToCart();
    });

    $r->add('GET', $prefix . '/cart', function () {
        jwt_middleware();
        CartController::getCartItems();
    });

    $r->add('DELETE', $prefix . '/cart/<id>', function ($id) {
        jwt_middleware();
        CartController::removeFromCart($id);
    });

    $r->add('GET', $prefix . '/cart/count', function () {
        CartController::getCartCount();
    });


    // ==================== CHECKOUT ROUTES ====================
    $r->add('POST', $prefix . '/checkout', function () {
        jwt_middleware();
        CheckoutController::create();
    });


    // ==================== ORDER ROUTES ==================== 
    $r->add('GET', $prefix . '/orders/<id>', function ($id) {
        OrderController::show($id);
    });
});

// ✅ Dispatch request
$router->dispatch($_SERVER['REQUEST_METHOD'], strtok($_SERVER['REQUEST_URI'], '?'));
