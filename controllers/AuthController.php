<?php
// Auth Controller
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../lib/entities/UserHelper.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController
{
    private static string $jwt_key;

    public static function init()
    {
        self::$jwt_key = env('JWT_SECRET'); // assign here
    }



    public static function login()
    {
        // $input = json_decode(file_get_contents('php://input'), true);

        $input = json_decode(file_get_contents('php://input'), true);
        $result = User::login($input);

        try {
            if (isset($result['error']) && $result['error']) {
                http_response_code($result['status']);
                echo json_encode(['errors' => $result['errors']]);
                return;
            }
            // echo json_encode($result);   
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
            return;
        }
    }


    public static function getCurrentUser()
    {
        $headers = getallheaders();
        if (!isset($headers['Authorization'])) {
            http_response_code(401);
            echo json_encode(['error' => 'No token provided']);
            exit;
        }
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $jwt_secret = env('JWT_SECRET',);
        $decoded = AuthController::verify($token, $jwt_secret);
        if (!$decoded) {
            http_response_code(401);
            echo json_encode(['message' => 'Invalid token']);
            exit;
        }
        return $decoded;
    }

    public static function verify($token)
    {
        try {
            // ✅ Use Key object with the secret & algorithm
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
            return $decoded;
        } catch (\Exception $e) {
            return false; // Token invalid or expired
        }
    }
    // Register method using UserRedBean and validation
    public static function register()
    {
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
