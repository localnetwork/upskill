<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;

class User
{
    // Just declare – no default here!
    protected static string $jwt_key;

    /**
     * Return the JWT key from cache or from .env
     */
    protected static function jwtKey(): string
    {
        // Load it once and reuse
        if (!isset(self::$jwt_key)) {
            self::$jwt_key = env('JWT_SECRET', 'default_secret');
        }
        return self::$jwt_key;
    }

    public static function create($data)
    {
        $validator = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'username'         => 'required|min:3|regex:/^[A-Za-z0-9@_-]+$/',
            'firstname'        => 'required|min:2',
            'lastname'         => 'required|min:2',
            'password'         => 'required|min:8|regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
            'confirm_password' => 'required|same:password',
            'email'            => 'required|regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/'
        ], [
            'username:regex'        => 'Username can only contain letters, numbers, dashes (-), underscores (_), or @.',
            'firstname:min'        => 'Firstname must be at least 2 characters long.',
            'lastname:min'         => 'Lastname must be at least 2 characters long.',
            'password:regex'        => 'Password must contain at least one letter, one number, and one special character.',
            'confirm_password:same' => 'Confirm password must match the password.',
            'email:regex'           => 'Please enter a valid email address (e.g. name@example.com).',
        ]);
        $validation->validate();

        // Check if email or username already exists
        if (!$validation->fails()) {
            $exists = \RedBeanPHP\R::findOne(
                'users',
                'email = ? OR username = ?',
                [$data['email'], $data['username']]
            );

            if ($exists) {
                $validation->errors()->add(
                    'email',
                    'custom',
                    'The email or username is already taken. Please try another.'
                );
            }
        }

        if ($validation->fails()) {
            return [
                'error'  => true,
                'status' => 422,
                'errors' => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.'
            ];
        }

        // Save user
        $user = \RedBeanPHP\R::dispense('users');
        $user->uuid     = Uuid::uuid4()->toString();
        $user->username = $data['username'];
        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        $user->email    = $data['email'];

        try {
            $id = \RedBeanPHP\R::store($user);
        } catch (\Exception $e) {
            return [
                'error'  => true,
                'status' => 500,
                'errors' => ['general' => 'Database error: ' . $e->getMessage()]
            ];
        }

        return [
            "user" => [
                "id"       => $id,
                "uuid"     => $user->uuid,
                "username" => $user->username,
                "email"    => $user->email,
            ],
            "message" => "User created successfully",
        ];
    }


    public static function login(array $data)
    {
        header('Content-Type: application/json');

        $validator = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'username' => 'required',
            'password' => 'required',
        ]);
        $validation->validate();

        if ($validation->fails()) {
            http_response_code(422);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Please check the validated fields.',
                'errors'  => $validation->errors()->firstOfAll()
            ]);
            exit;
        }

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $user = self::findByUsername($username)
            ?? self::findByEmail($username);

        if ($user && password_verify($password, $user->password)) {

            $roles = getUserRoles($user->id);

            $payload = [
                'sub'   => $user->id,
                'uuid'  => $user->uuid,
                'roles' => $roles,
                'iat'   => time(),
                'exp'   => time() + 3600 // 1 hour expiry
            ];

            // ✅ Use the getter
            $jwt = JWT::encode($payload, self::jwtKey(), 'HS256');

            echo json_encode([
                'status' => 'success',
                'token'  => $jwt,
                'user'   => [
                    'id'       => $user->id,
                    'username' => $user->username,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email'    => $user->email,
                    'uuid'     => $user->uuid,
                    'roles'    => $roles
                ]
            ]);
            exit;
        } else {
            http_response_code(422);
            echo json_encode(['message' => 'These credentials do not match our records.']);
            exit;
        }
    }

    public static function findByUsername($username)
    {
        return \RedBeanPHP\R::findOne('users', 'username = ?', [$username]);
    }

    public static function findByEmail($email)
    {
        return \RedBeanPHP\R::findOne('users', 'email = ?', [$email]);
    }
}
