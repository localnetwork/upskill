<?php

use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class UserRoleSeeder
{
    public static $weight = 20; // optional, for ordering

    public function run(): void
    {
        // Find the user with username = 'test'
        $user = R::findOne('users', 'username = ?', ['test']);

        if (!$user) {
            echo "❌ User with username 'test' not found.\n";
            return;
        }

        // Assign Admin role (role_id = 1)
        $uuid = Uuid::uuid4()->toString();
        $user_id = $user->id;
        $role_id = 1; // Admin role

        // Check if the user-role combination already exists
        $existing = R::findOne(
            'user_roles',
            'user_id = ? AND role_id = ?',
            [$user_id, $role_id]
        );

        if ($existing) {
            echo "⚠️  Skipped duplicate: user_id={$user_id}, role_id={$role_id}\n";
            return;
        }

        // Use raw insert since RedBean cannot dispense 'user_roles'
        R::exec(
            "INSERT INTO user_roles (uuid, user_id, role_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?)",
            [$uuid, $user_id, $role_id, R::isoDateTime(), R::isoDateTime()]
        );

        echo "✅ Inserted user_role: user_id={$user_id}, role_id={$role_id} ({$uuid})\n";
        echo "✅ UserRoleSeeder completed.\n";
    }
}
