<?php
require_once __DIR__ . '/vendor/autoload.php';
use RedBeanPHP\R;
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/lib/helper.php';

// ✅ Use env() helper for DB
$host = env('DB_HOST', 'localhost');
$db   = env('DB_NAME', 'upskill');
$user = env('DB_USER', 'root'); 
$pass = env('DB_PASS', '');
$port = env('DB_PORT', '3390');

// ✅ Connect to database
R::setup("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
R::freeze(false);

// ✅ Convert CamelCase → snake_case
function camelToSnake(string $input): string {
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $input));
}

// ✅ Directory containing schema files
$schemaDir = __DIR__ . '/schemas';

// ✅ Collect schema files with weights
$schemas = [];
foreach (glob($schemaDir . '/*Schema.php') as $file) {
    $base      = basename($file, 'Schema.php');  // e.g. UserProfile
    $tableName = camelToSnake($base) . 's';      // → user_profile + s
    $schema    = require $file;

    // use _weight if present, otherwise default to 100 (migrate last)
    $weight = $schema['_weight'] ?? 100;

    $schemas[] = [
        'file'   => $file,
        'table'  => $tableName,
        'schema' => $schema,
        'weight' => $weight,
    ];
}

// ✅ Sort by weight ascending (lower = earlier)
usort($schemas, fn($a, $b) => $a['weight'] <=> $b['weight']);

// ✅ Run migrations in order
foreach ($schemas as $item) {
    createTableFromSchema(R::getPDO(), $item['table'], $item['schema']);
    echo "Migrated: {$item['table']} (weight: {$item['weight']})\n";
}

echo "✅ All migrations complete.\n";
 