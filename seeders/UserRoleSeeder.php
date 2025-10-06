<?php

use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class UserRoleSeeder
{
    public static $weight = 20; // optional, for ordering

    public function run(): void
    {
        $user_roles = [
            [
                'user_id' => 1,
                'role_id' => 2, // Admin
            ],
            [
                'user_id' => 2,
                'role_id' => 3,  // Learner
            ],
            [
                'user_id' => 3,
                'role_id' => 2, // Instructor
            ],
        ];

        foreach ($user_roles as $ur) {
            $user_id = $ur['user_id'];
            $role_id = $ur['role_id'];

            // Check if this user-role combo already exists
            $existing = R::findOne(
                'user_roles',
                'user_id = ? AND role_id = ?',
                [$user_id, $role_id]
            );

            if ($existing) {
                echo "⚠️  Skipped duplicate: user_id={$user_id}, role_id={$role_id}\n";
                continue;
            }

            $uuid = Uuid::uuid4()->toString();
            $now  = R::isoDateTime();

            R::exec(
                "INSERT INTO user_roles (uuid, user_id, role_id, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?)",
                [$uuid, $user_id, $role_id, $now, $now]
            );

            echo "✅ Inserted: user_id={$user_id}, role_id={$role_id} ({$uuid})\n";
        }

        echo "🎉 UserRoleSeeder completed.\n";
    }
}
