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
            'username'         => 'required|max:10|min:3|regex:/^[A-Za-z0-9@_-]+$/',
            'firstname'        => 'required|max:30|min:2',
            'lastname'         => 'required|max:30|min:2',
            'password'         => 'required|max:16|min:8|regex:/^(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
            'confirm_password' => 'required|max:16|same:password',
            'email'            => 'required|max:60|regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
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
            $user->verified  = 0;
            $user->status    = 1;

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
            'user'   => [
                'id'       => $user->id,
                'username' => $user->username,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email'    => $user->email,
                'uuid'     => $user->uuid,
                'verified' => $user->verified,
                'status'   => $user->status,
                'roles'    => $roles,
            ],
            'iat'   => time(),
            'exp'   => time() + 3600 // 1 hour expiry
        ];

        $jwt = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        echo json_encode([
            'message' => 'User registered successfully.',
            'token'  => $jwt,
            'user'   => [
                'id'        => $user->id,
                'username'  => $user->username,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'email'     => $user->email,
                'uuid'      => $user->uuid,
                'roles'     => $roles,
                'verified'  => $user->verified,
                'status'    => $user->status,
                'biography' => $user->biography,
                'headline'  => $user->headline,
                'link_website' => $user->link_website,
                'link_x' => $user->link_x,
                'link_linkedin' => $user->link_linkedin,
                'link_instagram' => $user->link_instagram,
                'link_facebook' => $user->link_facebook,
                'link_tiktok' => $user->link_tiktok,
                'link_youtube' => $user->link_youtube,
                'link_github' => $user->link_github,
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

            $roles = UserRole::getUserRoles($user->id);

            $payload = [
                'user'   => [
                    'id'       => $user->id,
                    'username' => $user->username,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email'    => $user->email,
                    'uuid'     => $user->uuid,
                    'verified' => $user->verified,
                    'status'   => $user->status,
                    'roles'    => $roles,

                ],
                'iat'   => time(),
                'exp'   => time() + 3600 // 1 hour expiry
            ];

            // ✅ Use the getter
            $jwt = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

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
                    'verified' => $user->verified,
                    'status'   => $user->status,
                    'roles'    => $roles,
                    'biography' => $user->biography,
                    'headline'  => $user->headline,
                    'user_picture' => Media::getMediaById($user->user_picture),
                    'link_website' => $user->link_website,
                    'link_x' => $user->link_x,
                    'link_linkedin' => $user->link_linkedin,
                    'link_instagram' => $user->link_instagram,
                    'link_facebook' => $user->link_facebook,
                    'link_tiktok' => $user->link_tiktok,
                    'link_youtube' => $user->link_youtube,
                    'link_github' => $user->link_github,
                ]
            ]);
            exit;
        } else {
            http_response_code(422);
            echo json_encode(['message' => 'These credentials do not match our records.']);
            exit;
        }
    }

    public static function getPublicProfile($username)
    {
        $user = self::findByUsername($username);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        $roles = UserRole::getUserRoles($user->id);

        echo json_encode([
            'id'        => $user->id,
            'username'  => $user->username,
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
            'uuid'      => $user->uuid,
            'roles'     => $roles,
            'biography' => $user->biography,
            'headline'  => $user->headline,
            'user_picture' => Media::getMediaById($user->user_picture),
            'link_website' => $user->link_website,
            'link_x' => $user->link_x,
            'link_linkedin' => $user->link_linkedin,
            'link_instagram' => $user->link_instagram,
            'link_facebook' => $user->link_facebook,
            'link_tiktok' => $user->link_tiktok,
            'link_youtube' => $user->link_youtube,
            'link_github' => $user->link_github,
        ]);
        exit;
    }

    public static function findByUsername($username)
    {
        return \RedBeanPHP\R::findOne('users', 'username = ?', [$username]);
    }

    public static function findByEmail($email)
    {
        return \RedBeanPHP\R::findOne('users', 'email = ?', [$email]);
    }

    public static function getPublicProfileById($id)
    {
        $user = R::load('users', $id);
        if (!$user->id) {
            http_response_code(404);
            return [
                'error'   => true,
                'status'  => 404,
                'message' => 'User not found.'
            ];
        }

        $roles = UserRole::getUserRoles($user->id);

        return [
            'error'   => false,
            'status'  => 200,
            'data'    => [
                'id'        => $user->id,
                'username'  => $user->username,
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'uuid'      => $user->uuid,
                'roles'     => $roles,
                'biography' => $user->biography,
                'headline'  => $user->headline,
                'user_picture' => Media::getMediaById($user->user_picture),
                'link_website' => $user->link_website,
                'link_x' => $user->link_x,
                'link_linkedin' => $user->link_linkedin,
                'link_instagram' => $user->link_instagram,
                'link_facebook' => $user->link_facebook,
                'link_tiktok' => $user->link_tiktok,
                'link_youtube' => $user->link_youtube,
                'link_github' => $user->link_github,
            ]
        ];
    }

    public static function updateProfile($data)
    {
        $currentUser = AuthController::getCurrentUser();

        if (!$currentUser) {
            return [
                'error'   => true,
                'status'  => 401,
                'message' => 'Unauthorized.'
            ];
        }

        $validator = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'firstname' => 'required|max:30|min:2',
            'lastname'  => 'required|max:30|min:2',
        ]);
        $validation->validate();

        if ($validation->fails()) {
            return [
                'error'   => true,
                'status'  => 422,
                'errors'  => $validation->errors()->firstOfAll(),
                'message' => 'Please check the validated fields.'
            ];
        }

        // ✅ Start transaction
        R::begin();
        try {
            // ✅ Load the existing user
            $user = R::load('users', id: $currentUser->user->id);
            if (!$user->id) {
                throw new \Exception('User not found.');
            }

            // ✅ Update only allowed fields
            $user->firstname       = $data['firstname'];
            $user->lastname        = $data['lastname'];
            $user->biography       = $data['biography'] ?? $user->biography;
            $user->headline        = $data['headline'] ?? $user->headline;
            $user->link_website    = $data['link_website'] ?? $user->link_website;
            $user->link_x          = $data['link_x'] ?? $user->link_x;
            $user->link_linkedin   = $data['link_linkedin'] ?? $user->link_linkedin;
            $user->link_instagram  = $data['link_instagram'] ?? $user->link_instagram;
            $user->link_facebook   = $data['link_facebook'] ?? $user->link_facebook;
            $user->link_tiktok     = $data['link_tiktok'] ?? $user->link_tiktok;
            $user->link_youtube    = $data['link_youtube'] ?? $user->link_youtube;
            $user->link_github     = $data['link_github'] ?? $user->link_github;
            $user->updated_at      = R::isoDateTime();

            // ✅ Save the changes
            R::store($user);
            R::commit();

            return [
                'error'   => false,
                'status'  => 200,
                'message' => 'Profile updated successfully.',
                'data' => $user
            ];
        } catch (\Exception $e) {
            R::rollback();
            return [
                'error'  => true,
                'status' => 500,
                'errors' => ['general' => 'Transaction failed: ' . $e->getMessage()],
            ];
        }
    }

    public static function uploadPicture($data)
    {
        $currentUser = AuthController::getCurrentUser();

        if (!$currentUser) {
            return [
                'error'   => true,
                'status'  => 401,
                'message' => 'Unauthorized.'
            ];
        }

        $validator = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'user_picture' => 'required',
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

        R::exec("UPDATE users SET user_picture = ? WHERE id = ?", [$data['user_picture'], $currentUser->user->id]);

        return [
            'error'   => false,
            'status'  => 200,
            'data' => Media::getMediaById($data['user_picture']),
            'message' => 'Profile picture uploaded successfully.',
        ];
    }
}
