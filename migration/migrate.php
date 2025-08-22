<?php
require_once __DIR__ . '/../vendor/autoload.php';
use RedBeanPHP\R;
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../schemas/create_table_helper.php';


// Use env() helper for DB   
$host = env('DB_HOST', 'localhost');
$db   = env('DB_NAME', 'upskill'); 
$user = env('DB_USER', 'root');  
$pass = env('DB_PASS', ''); 
$port = env('DB_PORT', '3390');

R::setup("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
R::freeze(false);

$userSchema = require __DIR__ . '/../schemas/UserSchema.php';
require_once __DIR__ . '/../schemas/create_table_helper.php';
// Use RedBean's PDO connection
createTableFromSchema(R::getPDO(), 'users', $userSchema);

echo "Migration complete.\n"; 