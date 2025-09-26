<?php

use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class CourseLectureTypeSeeder
{
    public static $weight = 10; // optional, lower = first

    public function run(): void
    {
        $types = [
            [
                'uuid' => Uuid::uuid4()->toString(),
                'title' => 'Video'
            ],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'title' => 'Article'
            ],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'title' => 'Downloadable Resource'
            ],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'title' => 'Quiz'
            ]
        ];

        foreach ($types as $data) {
            // Check if type already exists by title
            $existing = R::findOne('course_curriculum_types', 'title = ?', [$data['title']]);

            if ($existing) {
                echo "⚠️  Skipped duplicate type: {$data['title']}\n";
                continue;
            }

            R::exec(
                "INSERT INTO course_curriculum_types (uuid, title, created_at, updated_at) VALUES (?, ?, ?, ?)",
                [$data['uuid'], $data['title'], R::isoDateTime(), R::isoDateTime()]
            );

            echo "✅ Inserted type: {$data['title']} ({$data['uuid']})\n";
        }

        echo "✅ CourseCurriculumTypeSeeder completed.\n";
    }
}
