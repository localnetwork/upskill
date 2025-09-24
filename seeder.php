<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php'; // R::setup()

use RedBeanPHP\R;

$path = __DIR__ . '/seeders';

$seeders = [];

// Collect all seeders and their weights
foreach (glob($path . '/*.php') as $file) {
    require_once $file;

    $className = pathinfo($file, PATHINFO_FILENAME);

    if (!class_exists($className)) {
        echo "⚠️  No class found in {$file}\n";
        continue;
    }

    $weight = 50; // default weight if not defined

    // If class has a static property $weight, use it
    if (property_exists($className, 'weight')) {
        $weight = $className::$weight;
    }

    // Or if class has a static method weight(), use that
    if (method_exists($className, 'weight')) {
        $weight = $className::weight();
    }

    $seeders[] = [
        'class' => $className,
        'weight' => $weight
    ];
}

// Sort seeders by weight ascending (lower weight runs first)
usort($seeders, fn($a, $b) => $a['weight'] <=> $b['weight']);

// Run seeders
foreach ($seeders as $seederInfo) {
    $className = $seederInfo['class'];
    echo "▶ Running {$className} (weight: {$seederInfo['weight']})...\n";

    $seeder = new $className();
    if (method_exists($seeder, 'run')) {
        $seeder->run();
    } else {
        echo "⚠️  {$className} has no run() method.\n";
    }
}

echo "🎉 All seeders finished.\n";
