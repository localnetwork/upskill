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

        // ✅ Get estimated duration (in seconds) using ffprobe
        $video->estimated_duration = null;
        if (!empty($data['path']) && file_exists($data['path'])) {
            $duration = self::getVideoDuration($data['path']);
            $video->estimated_duration = $duration;

            // store video blob (optional if DB supports blob)
            $video->video_blob = file_get_contents($data['path']);
        } else {
            $video->video_blob = null;
        }

        return R::store($video);
    }

    /**
     * Get duration of a video file using ffprobe (returns seconds as float).
     */
    private static function getVideoDuration($filePath)
    {
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($filePath);
        $output = shell_exec($cmd);
        if ($output !== null) {
            return round((float) $output, 2); // duration in seconds (2 decimals)
        }
        return null;
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
