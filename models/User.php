<?php
// User Model (with schema-like structure)
class User {
    public $id;
    public $username;
    public $password; 

    public function __construct($id, $username, $password) {
        $this->id = $id;
        $this->username = $username;
        $this->password = $password;
    }

    // Simulate DB lookup
    public static function findByUsername($username) {
        // Example user
        if ($username === 'admin') {
            return new User(1, 'admin', password_hash('password', PASSWORD_DEFAULT));
        }
        return null;
    }
}
