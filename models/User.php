<?php
// Example RedBeanPHP User model
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php'; 

use Ramsey\Uuid\Uuid;

class User
{       public static function create($data) 
        { 
            $validator = new \Rakit\Validation\Validator;

            $validation = $validator->make($data, [ 
                'username' => 'required|min:3|regex:/^[A-Za-z0-9@_-]+$/',
                'password' => 'required|min:6',
                'email'    => 'required|regex:/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/'
            ], [
                'username:regex' => 'Username can only contain letters, numbers, dashes (-), underscores (_), or @.',
                'email:regex'    => 'Please enter a valid email address (e.g. name@example.com).'
            ]);

            $validation->validate();

            if (!$validation->fails()) {
                $exists = \RedBeanPHP\R::findOne( 
                    'users',
                    'email = ? OR username = ?',
                    [$data['email'], $data['username']]
                );

                if ($exists) {
                    $validation->errors()->add(
                        'user_exists',
                        'custom',
                        'The email or username is already taken. Please try another.'
                    );
                }
            }

            if ($validation->fails()) {
                return [
                    'error'  => true, 
                    'status' => 422,
                    'errors' => $validation->errors()->firstOfAll()
                ]; 
            }

            $user = \RedBeanPHP\R::dispense('users');
            $user->uuid     = Uuid::uuid4()->toString(); // <-- add UUID
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
                    "uuid"     => $user->uuid, // include UUID in response
                    "username" => $user->username,
                    "email"    => $user->email,
                ], 
                "message" => "User created successfully",
            ];
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
  