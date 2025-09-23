<?php

use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class RoleSeeder
{
    public static $weight = 10; // optional, lower = first

    public function run(): void
    {
        $roles = [
            [
                'uuid' => Uuid::uuid4()->toString(),
                'name' => 'Admin'
            ],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'name' => 'Teacher'
            ],
            [
                'uuid' => Uuid::uuid4()->toString(),
                'name' => 'Student'
            ]
        ];

        foreach ($roles as $data) {
            // Check if role already exists by name
            $existing = R::findOne('roles', 'name = ?', [$data['name']]);

            if ($existing) {
                echo "⚠️  Skipped duplicate role: {$data['name']}\n";
                continue;
            }

            $role = R::dispense('roles');
            $role->uuid       = $data['uuid'];
            $role->name       = $data['name'];
            $role->created_at = R::isoDateTime();
            $role->updated_at = R::isoDateTime();
            R::store($role);

            echo "✅ Inserted role: {$data['name']} ({$data['uuid']})\n";
        }

        echo "✅ RoleSeeder completed.\n";
    }
}
