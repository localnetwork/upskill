<?php
use RedBeanPHP\R;
use Ramsey\Uuid\Uuid;

class UserSeeder
{
    public static $weight = 10; // lower runs first
     public function run(): void 
    {
        $users = [
            [
                'username' => 'test',
                'email'    => 'test@test.com',
                'uuid'     => Uuid::uuid4()->toString(), // ✅ generated here
                'password' => password_hash('1', PASSWORD_DEFAULT),
            ],
        ];

        foreach ($users as $data) {
            // 🔎 Check if a user with the same email already exists
            $existing = R::findOne('users', ' email = ? ', [$data['email']]);

            if ($existing) {
                echo "⚠️  Skipped duplicate: {$data['email']}\n";
                continue; // Skip inserting
            }

            $user = R::dispense('users');
            $user->uuid       = $data['uuid'];      // ✅ assign uuid to bean
            $user->username   = $data['username'];
            $user->email      = $data['email'];
            $user->password   = $data['password'];
            $user->created_at = R::isoDateTime();
            $user->updated_at = R::isoDateTime();
            R::store($user);

            echo "✅ Inserted: {$data['email']} ({$data['uuid']})\n";
        }

        echo "✅ UserSeeder completed.\n";
    }
} 
 