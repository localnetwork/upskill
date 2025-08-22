<?php
// RedBeanPHP setup
require_once __DIR__ . '/../vendor/autoload.php';
use RedBeanPHP\R;
require_once __DIR__ . '/../config/env.php';
 
// Use env() helper for DB  
$host = env('DB_HOST', 'localhost');
$db   = env('DB_NAME', 'upskill');
$user = env('DB_USER', 'root');  
$pass = env('DB_PASS', '');
$port = env('DB_PORT', '3390');

R::setup("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
R::freeze(false); 