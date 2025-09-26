<?php

use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class CourseLevelSeeder
{
    public static $weight = 30; // optional, lower = first

    public function run(): void
    {
        // If you have a default author, define it here
        $authorId = 1; // Change to a real user ID or remove if not needed

        $levels = [
            ['title' => 'Beginner'],
            ['title' => 'Intermediate'],
            ['title' => 'Advanced'],
            ['title' => 'All Levels'],
        ];

        foreach ($levels as $data) {
            // Check if level already exists by title
            $existing = R::findOne('course_levels', 'title = ?', [$data['title']]);

            if ($existing) {
                echo "⚠️ Skipped duplicate level: {$data['title']}\n";
                continue;
            }

            $uuid = Uuid::uuid4()->toString();

            R::exec(
                "INSERT INTO course_levels (uuid, title, created_at, updated_at)
                 VALUES (?, ?, ?, ?)",
                [$uuid, $data['title'], R::isoDateTime(), R::isoDateTime()]
            );

            echo "✅ Inserted level: {$data['title']} ({$uuid})\n";
        }

        echo "✅ CourseLevelSeeder completed.\n";
    }
}
