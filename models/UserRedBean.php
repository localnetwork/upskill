<?php
// Example RedBeanPHP User model
require_once __DIR__ . '/RedBeanSetup.php';
require_once __DIR__ . '/../vendor/autoload.php';
 
 class UserRedBean {
 
    public static function create($data) {
        $validator = new \Rakit\Validation\Validator; 
        $validation = $validator->make($data, [
            'username' => 'required|min:3',
            'password' => 'required|min:6',
        ]); 
        $validation->validate();
        if ($validation->fails()) {
            // Output validation errors and stop execution
            http_response_code(422);
            echo json_encode(['errors' => $validation->errors()->firstOfAll()]);
            exit;
        }
        $user = \RedBeanPHP\R::dispense('users');
        $user->username = $data['username'];
        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
        \RedBeanPHP\R::store($user);
        return $user;
    } 

    // Example: This will create the 'users' table if it doesn't exist
    public static function exampleCreateTable() {
        $user = \R::dispense('users');
        $user->username = 'example';
        $user->password = password_hash('secret', PASSWORD_DEFAULT);
        \R::store($user);
        return $user;
    } 

    public static function findByUsername($username) {
        return \R::findOne('users', 'username = ?', [$username]);
    }
}
