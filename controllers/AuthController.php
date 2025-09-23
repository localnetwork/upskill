<?php
// Auth Controller
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../lib/entities/UserHelper.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;  
  
class AuthController {
    private static string $jwt_key;

        public static function init() {
            self::$jwt_key = env('JWT_SECRET_KEY', 'default_secret'); // assign here
        }


    
        public static function login() { 
            $input = json_decode(file_get_contents('php://input'), true);

            // Find user by username or email
            $user = User::findByUsername($input['username'] ?? '')  
                ?? User::findByEmail($input['username'] ?? ''); 

            if ($user && password_verify($input['password'] ?? '', $user->password)) {

                 
                $roles = getUserRoles($user->id); 

                // JWT payload
                $payload = [ 
                    'sub'  => $user->id,
                    'uuid' => $user->uuid,
                    'roles'=> $roles,      // include roles in JWT
                    'iat'  => time(),
                    'exp'  => time() + 3600
                ];

                $jwt = JWT::encode($payload, self::$jwt_key, 'HS256');  

                // Prepare user data for response
                $user_data = [
                    'id'       => $user->id, 
                    'username' => $user->username, 
                    'email'    => $user->email,
                    'uuid'     => $user->uuid,
                    'roles'    => $roles
                ]; 

                echo json_encode([
                    'token' => $jwt,
                    'user'  => $user_data
                ]);

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
            $result = User::create($input);

            if (isset($result['error']) && $result['error']) {
                http_response_code($result['status']);
                echo json_encode(['errors' => $result['errors']]);  
                return;
            }
            echo json_encode($result);
        } 
} 


AuthController::init();  