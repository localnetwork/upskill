<?php
use RedBeanPHP\R;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

$host = env('DB_HOST', 'localhost');
$db   = env('DB_NAME', 'upskill');
$user = env('DB_USER', 'root');
$pass = env('DB_PASS', '');
$port = env('DB_PORT', '3390'); // ✅ default MySQL port

R::setup("mysql:host=$host;port=$port;dbname=$db", $user, $pass);

// Development: allow schema changes
R::freeze(false);

// Optional: show queries while debugging 
// R::debug(true);
 