<?php

use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class CoursePriceTierSeeder
{
    public static $weight = 30; // optional, lower = first

    public function run(): void
    {

        $tiers = [
            ['title' => 'Free', 'price' => 0.00],
            ['title' => 'Tier 1', 'price' => 50.00],
            ['title' => 'Tier 2', 'price' => 100.00],
            ['title' => 'Tier 3', 'price' => 200.00],
            ['title' => 'Tier 4', 'price' => 250.00],
            ['title' => 'Tier 5', 'price' => 300.00],
            ['title' => 'Tier 6', 'price' => 400.00],
            ['title' => 'Tier 7', 'price' => 500.00],
        ];

        foreach ($tiers as $data) {
            // Check if tier already exists by title
            $existing = R::findOne('course_price_tiers', 'title = ?', [$data['title']]);

            if ($existing) {
                echo "⚠️ Skipped duplicate tier: {$data['title']}\n";
                continue;
            }

            $uuid = Uuid::uuid4()->toString();

            R::exec(
                "INSERT INTO course_price_tiers (uuid, title, price, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?)",
                [$uuid, $data['title'], $data['price'], R::isoDateTime(), R::isoDateTime()]
            );

            echo "✅ Inserted tier: {$data['title']} ({$uuid})\n";
        }

        echo "✅ CoursePriceTierSeeder completed.\n";
    }
}
