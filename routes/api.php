<?php
// API Routes
require_once __DIR__ . '/router.php';
require_once __DIR__ . '/../controllers/AuthController.php'; 
require_once __DIR__ . '/../middleware/jwt.php';
 
$router = new Router();
 
$router->add('GET', '/', function() {    
    echo json_encode(['message' => 'Welcome to the API']);
});
  
$router->add('POST', '/login', function() { 
    AuthController::login(); 
});

$router->add('GET', '/profile', function() {
    $user = jwt_middleware();
    echo json_encode(['user' => $user]);  
});  
 
// Register route must be above dispatch
$router->add('POST', '/register', function() {
    AuthController::register();
}); 

   
// Dispatch
$router->dispatch($_SERVER['REQUEST_METHOD'], strtok($_SERVER['REQUEST_URI'], '?'));