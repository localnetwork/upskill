<?php
// Auth Controller
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/UserRedBean.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

  class AuthController { 
     private static $jwt_key = 'your_secret_key'; 

    public static function login() {
        $input = json_decode(file_get_contents('php://input'), true);
        $user = User::findByUsername($input['username'] ?? '');
        if ($user && password_verify($input['password'] ?? '', $user->password)) {
            $payload = [
                'sub' => $user->id,
                'username' => $user->username,
                'iat' => time(),
                'exp' => time() + 3600
            ];
            $jwt = JWT::encode($payload, self::$jwt_key, 'HS256');
            echo json_encode(['token' => $jwt]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    }

    public static function verify($token) {
        try {
            $decoded = JWT::decode($token, new Key(self::$jwt_key, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            return false;
        } 
    } 

    // Register method using UserRedBean and validation
    public static function register() {
        $input = json_decode(file_get_contents('php://input'), true);
        $result = UserRedBean::create($input);
        if (isset($result['errors'])) {
            http_response_code(422); 
            echo json_encode(['errors' => $result['errors']]);
            return;
        }
        echo json_encode(['user' => [
            'id' => $result->id,  
            'username' => $result->username
        ]]);
    } 
}
 