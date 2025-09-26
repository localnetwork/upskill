<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';


use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;

use RedBeanPHP\R; // ✅ Import RedBeanPHP static facade 

class Media
{
    public static function createMedia($data)
    {
        // Media creation logic here
        $media = R::dispense('media');
        $media->title = $data['title'];
        $media->description = $data['description'];
        $media->created_at = R::isoDateTime();
        R::store($media);

        return [
            'id' => $media->id,
            'title' => $media->title,
            'description' => $media->description,
            'created_at' => $media->created_at
        ];
    }
}
