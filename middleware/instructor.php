<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/env.php';

function instructor_middleware()
{
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);

    if (!isset($headers['authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Authorization header missing']);
        exit;
    }

    if (!preg_match('/Bearer\s(\S+)/', $headers['authorization'], $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Invalid Authorization format']);
        exit;
    }

    $token = $matches[1];

    try {
        $jwt_secret = env('JWT_SECRET');
        $decoded = JWT::decode($token, new Key($jwt_secret, 'HS256'));

        // ✅ Check if user has the required role
        $roles = $decoded->user->roles ?? [];
        $hasEducator = false;

        foreach ($roles as $role) {
            if (strcasecmp($role->name, 'Educator') === 0) {
                $hasEducator = true;
                break;
            }
        }

        if (!$hasEducator) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden', 'message' => 'Educator role required']);
            exit;
        }
        return $decoded;
    } catch (\Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => $e->getMessage()]);
        exit;
    }
}
