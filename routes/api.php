<?php
// API Routes
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../middleware/jwt.php';

$router = new Router();

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
});

// ✅ Dispatch request
$router->dispatch($_SERVER['REQUEST_METHOD'], strtok($_SERVER['REQUEST_URI'], '?'));
