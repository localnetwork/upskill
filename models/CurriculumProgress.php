<?php

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../controllers/AuthController.php';

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use RedBeanPHP\R;

class CurriculumProgress
{
    public static function add($data)
    {
        $currentUser = AuthController::getCurrentUser();

        // ✅ Check if already exists
        $found = R::findOne('curriculum_progress', 'author_id = ? AND curriculum_id = ?', [
            $currentUser->user->id,
            $data['curriculum_id']
        ]);

        if ($found) {
            return [
                'id'             => $found->id,
                'uuid'           => $found->uuid,
                'author_id'      => $found->author_id,
                'curriculum_id'  => $found->curriculum_id,
                'created_at'     => $found->created_at ?? null,
            ];
        }

        // ✅ Generate a unique  
        $uuid = Uuid::uuid4()->toString();

        // ✅ Insert using R::exec with UUID and get the last inserted ID
        R::exec(
            'INSERT INTO curriculum_progress (uuid, author_id, curriculum_id, created_at) VALUES (?, ?, ?, ?)',
            [
                $uuid,
                $currentUser->user->id,
                $data['curriculum_id'],
                date('Y-m-d H:i:s')
            ]
        );

        // ✅ Get the last inserted ID
        $id = R::getCell('SELECT LAST_INSERT_ID()');

        // ✅ Fetch the inserted record
        $progress = R::findOne('curriculum_progress', 'id = ?', [$id]);

        // ✅ Return the inserted record 
        return [
            'id'             => $progress->id,
            'uuid'           => $progress->uuid,
            'author_id'      => $progress->author_id,
            'curriculum_id'  => $progress->curriculum_id,
            'created_at'     => $progress->created_at
        ];
    }
}
