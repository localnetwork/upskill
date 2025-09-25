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
                'title' => 'Web Development'
            ],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'title' => 'Data Science'
            ],
        ];

        foreach ($tags as $data) {
            $user = R::findOne('users', 'username = ?', ['test']);
            if (!$user) {
                echo "❌ User 'test' not found.\n";
                return;
            }

            // Check if tag already exists by title and user
            $existing = R::findOne(
                'course_tags',
                'title = ? AND user_id = ?',
                [$data['title'], $user->id]
            );

            if ($existing) {
                echo "⚠️ Skipped duplicate tag: {$data['title']}\n";
                continue;
            }

            R::exec(
                "INSERT INTO course_tags (uuid, title, created_at, updated_at, author_id) 
     VALUES (?, ?, ?, ?, ?)",
                [$data['uuid'], $data['title'], R::isoDateTime(), R::isoDateTime(), $user->id]
            );


            echo "✅ Inserted tag: {$data['title']} ({$data['uuid']})\n";
        }

        echo "✅ CourseTagSeeder completed.\n";
    }
}
