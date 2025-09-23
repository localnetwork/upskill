<?php
// env() helper for loading environment variables from .env
function env($key, $default = null)
{
    static $env = null;
    if ($env === null) {
        $env = [];
        if (file_exists(__DIR__ . '/../.env')) {
            foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                list($k, $v) = array_map('trim', explode('=', $line, 2));
                $env[$k] = $v;
            }
        }
    }
    return $env[$key] ?? $default;
}
