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
                'uuid'     => Uuid::uuid4()->toString(),
                'user_id'  => 1, // replace with actual user ID
                'role_id'  => 1, // replace with actual role ID
            ],
        ];   

        foreach ($user_roles as $data) {
            // Check if the user-role combination already exists
            $existing = R::findOne(
                'user_roles',
                'user_id = ? AND role_id = ?',
                [$data['user_id'], $data['role_id']]
            ); 

            if ($existing) {
                echo "⚠️  Skipped duplicate: user_id={$data['user_id']}, role_id={$data['role_id']}\n";
                continue;
            } 

            $bean = R::dispense('user_roles');
            $bean->uuid       = $data['uuid'];
            $bean->user_id    = $data['user_id'];
            $bean->role_id    = $data['role_id'];
            $bean->created_at = R::isoDateTime();  
            $bean->updated_at = R::isoDateTime(); 
            R::store($bean);

            echo "✅ Inserted user_role: user_id={$data['user_id']}, role_id={$data['role_id']} ({$data['uuid']})\n";
        }

        echo "✅ UserRoleSeeder completed.\n"; 
    }
} 
