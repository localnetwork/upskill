<?php

use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class CourseTagSeeder
{
    public static $weight = 30; // optional, lower = first

    public function run(): void
    {
        $tags = [
            [
                'uuid' => Uuid::uuid4()->toString(),
                'title' => 'Web Development',
                'author_id' => 1
            ],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'title' => 'Data Science',
                'author_id' => 1
            ],
        ];

        foreach ($tags as $data) {
            // Check if tag already exists by title (global, ignore user)
            $existing = R::findOne(
                'course_tags',
                'title = ?',
                [$data['title']]
            );

            if ($existing) {
                echo "⚠️ Skipped duplicate tag: {$data['title']}\n";
                continue;
            }

            // Insert tag
            R::exec(
                "INSERT INTO course_tags (uuid, title, created_at, updated_at, author_id) 
                 VALUES (?, ?, ?, ?, ?)",
                [$data['uuid'], $data['title'], R::isoDateTime(), R::isoDateTime(), $data['author_id']]
            );

            echo "✅ Inserted tag: {$data['title']} ({$data['uuid']})\n";
        }

        echo "✅ CourseTagSeeder completed.\n";
    }
}
