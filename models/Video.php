<?php

use Ramsey\Uuid\Uuid;
use RedBeanPHP\R;

class Video
{

    private static function encryptVideoFile($inputPath, $outputPath, &$iv)
    {
        $key = hex2bin("SAMPLE_KEY_HERE"); // 32 bytes = AES-256
        $iv  = random_bytes(16);                     // 16 bytes for CBC

        $fpIn  = fopen($inputPath, 'rb');
        $fpOut = fopen($outputPath, 'wb');

        // write IV at the start of file
        fwrite($fpOut, $iv);

        $bufferSize = 8192;
        while (!feof($fpIn)) {
            $chunk = fread($fpIn, $bufferSize);
            $encrypted = openssl_encrypt($chunk, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            fwrite($fpOut, $encrypted);
        }

        fclose($fpIn);
        fclose($fpOut);
    }

    public static function find($id)
    {
        $video = R::exec('SELECT * FROM videos WHERE id = ?', [$id]);
        return $video;
    }

    public static function create($data)
    {
        $video = R::dispense('videos');
        $video->uuid = Uuid::uuid4()->toString();
        $video->title = $data['title'];
        $video->type = $data['type'];
        $video->size = $data['size'];
        $video->author_id = $data['author_id'];
        $video->created_at = R::isoDateTime();
        $video->updated_at = R::isoDateTime();
        $video->resource_type = 'video';
        $video->estimated_duration = null;

        if (!empty($data['path']) && file_exists($data['path'])) {

            // --- Set upload folder relative to project root ---
            $uploadFolder = '/assets/videos/';
            if (!is_dir(__DIR__ . '/../' . $uploadFolder)) {
                mkdir(__DIR__ . '/../' . $uploadFolder, 0755, true);
            }

            // --- Generate filenames ---
            $encryptedFilename = 'encrypted_' . basename($data['path']);
            $relativePath = $uploadFolder . $encryptedFilename;
            $serverPath = __DIR__ . '/../' . $relativePath; // full path for writing

            // --- Encrypt the video ---
            $iv = null;
            self::encryptVideoFile($data['path'], $serverPath, $iv);

            // --- Save path (relative) and IV in DB --- 
            $video->path = $relativePath;
            $video->iv = bin2hex($iv);

            // optional: do NOT store the key in DB, keep it server-side
            // $video->encryption_key = 'SAMPLE_KEY_HERE';

            // --- Get estimated duration ---
            $video->estimated_duration = self::getVideoDuration($data['path']);
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
