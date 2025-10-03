<?php

use Ramsey\Uuid\Uuid;
use RedBeanPHP\R;

class Video
{
    public static function find($id)
    {
        return R::findOne('videos', 'id = ?', [$id]);
    }

    public static function create($data)
    {
        $video              = R::dispense('videos');
        $video->uuid        = Uuid::uuid4()->toString();
        $video->title       = $data['title'];
        $video->path        = $data['path']; // still store path if you want
        $video->type        = $data['type'];
        $video->size        = $data['size'];
        $video->author_id   = $data['author_id'];
        $video->created_at  = R::isoDateTime();
        $video->updated_at  = R::isoDateTime();
        $video->resource_type = 'video';

        // Read binary contents of the file and assign to blob field
        if (!empty($data['path']) && file_exists($data['path'])) {
            $video->video_blob = file_get_contents($data['path']);
        } else {
            $video->video_blob = null; // or throw an exception
        }

        return R::store($video);
    }
    public static function delete($id)
    {
        $video = R::findOne('videos', 'id = ?', [$id]);
        if ($video) {
            R::trash($video);
            return [
                'status'  => 200,
                'message' => 'Video deleted successfully.'
            ];
        }
        return [
            'status'  => 404,
            'message' => 'Video not found.'
        ];
    }
}
