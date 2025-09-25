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
}
