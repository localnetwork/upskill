<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
class UserController
{
    // Controller methods here
    public static function getUserByUsername($username): void
    {
        echo json_encode(User::getPublicProfile($username));
    }

    public static function update(): void
    {
        $input  = json_decode(file_get_contents('php://input'), true);
        $result = User::updateProfile($input);

        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'message' => $result['message'] ?? 'An error occurred.',
                'errors'  => $result['errors'] ?? null,
            ]);
            return;
        }
        echo json_encode($result);
    }

    public static function uploadProfilePicture(): void
    {
        $input  = json_decode(file_get_contents('php://input'), true);
        $result = User::uploadPicture($input);

        if (!empty($result['error'])) {
            http_response_code($result['status'] ?? 500);
            echo json_encode([
                'message' => $result['message'] ?? 'An error occurred.',
                'errors'  => $result['errors'] ?? null,
            ]);
            return;
        }
        echo json_encode($result);
    }
}
