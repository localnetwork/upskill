<?php
// Example RedBeanPHP User model
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php'; 
  
 class User { 
 
    public static function create($data) { 
        $validator = new \Rakit\Validation\Validator;
        $validation = $validator->make($data, [
            'username' => 'required|min:3',
            'password' => 'required|min:6',
            'email' => 'required|email'
        ]);
        $validation->validate();
        if ($validation->fails()) {
            return [
                'error' => true,
                'status' => 422,
                'errors' => $validation->errors()->firstOfAll()
            ];
        }
        $existing = \RedBeanPHP\R::findOne('users', 'email = ?', [$data['email']]);
        if ($existing) {
            return [
                'error' => true,
                'status' => 422,
                'errors' => ['email' => 'The email is already taken.']
            ];
        }
        $user = \RedBeanPHP\R::dispense('users');
        $user->username = $data['username'];
        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        $user->email = $data['email'];
        $id = \RedBeanPHP\R::store($user);
        if (!$id) {
            return [
                'error' => true,
                'status' => 500,
                'errors' => ['general' => 'Failed to create user.']
            ];
        }
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email
        ];
    } 

 
    public static function findByUsername($username) {
        return \R::findOne('users', 'username = ?', [$username]);
    }
}
