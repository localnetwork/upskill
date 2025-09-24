<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';


require_once __DIR__ . '/../models/UserRole.php';

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;

use RedBeanPHP\R; // ✅ Import RedBeanPHP static facade 

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
            'email'            => 'required|regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
            'role'             => 'required|in:2,3', // 2 = Teacher, 3 = Student
        ], [
            'username:regex'        => 'Username can only contain letters, numbers, dashes (-), underscores (_), or @.',
            'firstname:min'         => 'Firstname must be at least 2 characters long.',
            'lastname:min'          => 'Lastname must be at least 2 characters long.',
            'password:regex'        => 'Password must contain at least one letter, one number, and one special character.',
            'confirm_password:same' => 'Confirm password must match the password.',
            'email:regex'           => 'Please enter a valid email address.',
            'role:in'               => 'Role must be either Teacher (2) or Student (3).',
        ]);
        $validation->validate();

        // Extra DB checks if base validation passed
        if (!$validation->fails()) {
            if (R::findOne('users', 'username = ?', [$data['username']])) {
                $validation->errors()->add('username', 'custom', 'The username is already taken.');
            }
            if (R::findOne('users', 'email = ?', [$data['email']])) {
                $validation->errors()->add('email', 'custom', 'The email is already registered.');
            }
        }

        if ($validation->fails()) {
            return [
                'error'   => true,
                'status'  => 422,
                'errors'  => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.'
            ];
        }

        // ✅ Transaction: both user and role must succeed
        R::begin();
        try {
            // Create the user
            $user = R::dispense('users');
            $user->uuid      = Uuid::uuid4()->toString();
            $user->username  = $data['username'];
            $user->password  = password_hash($data['password'], PASSWORD_DEFAULT);
            $user->email     = $data['email'];
            $user->firstname = $data['firstname'];
            $user->lastname  = $data['lastname'];

            $userId = R::store($user); // may throw exception

            // Assign role
            $roleId = (int) $data['role'];
            $userRoleId = UserRole::create($userId, $roleId); // must return an ID

            if (!$userRoleId) {
                throw new \Exception('Failed to create user role record.');
            }

            // ✅ Commit only if both inserts succeeded
            R::commit();
        } catch (\Exception $e) {
            R::rollback();
            return [
                'error'  => true,
                'status' => 500,
                'errors' => ['general' => 'Transaction failed: ' . $e->getMessage()]
            ];
        }

        // ✅ Generate JWT only if commit succeeded
        $roles = UserRole::getUserRoles($userId);
        $payload = [
            'sub'   => $userId,
            'uuid'  => $user->uuid,
            'roles' => $roles,
            'iat'   => time(),
            'exp'   => time() + 3600
        ];
        $jwt = JWT::encode($payload, self::jwtKey(), 'HS256');

        echo json_encode([
            'status' => 'success',
            'token'  => $jwt,
            'user'   => [
                'id'        => $userId,
                'username'  => $user->username,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'email'     => $user->email,
                'uuid'      => $user->uuid,
                'roles'     => $roles
            ]
        ]);
        exit;
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
