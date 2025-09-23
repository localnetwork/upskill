

<?php
// JWT Middleware
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../config/database.php'; // for env()

function jwt_middleware()
{
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'No token provided']);
        exit;
    }
    $token = str_replace('Bearer ', '', $headers['Authorization']);
    $jwt_secret = env('JWT_SECRET', 'your_secret_key');
    $decoded = AuthController::verify($token, $jwt_secret);
    if (!$decoded) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token']);
        exit;
    }
    return $decoded;
}
