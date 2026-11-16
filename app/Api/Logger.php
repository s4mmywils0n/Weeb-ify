<?php

declare(strict_types=1);

namespace App\Api;

/*
 * Writes one line per event to storage/logs/api.log, always tagged with the request id:
 *
 *   2026-11-16T17:40:02-05:00 INFO  [3f2a9c1e0b7d4a55] GET /anime?sort=title_asc 200 84ms json
 *   2026-11-16T17:40:09-05:00 ERROR [9b1c...] PDOException: SQLSTATE[42P01] ... in AnimeListService.php:118
 *
 * If the file can't be written it falls back to PHP's normal error log (Apache's error.log).
 */
class Logger
{
    public function __construct(private string $file)
    {
    }

    public function info(string $requestId, string $message): void
    {
        $this->write('INFO ', $requestId, $message);
    }

    public function error(string $requestId, string $message): void
    {
        $this->write('ERROR', $requestId, $message);
    }

    private function write(string $level, string $requestId, string $message): void
    {
        // One event = one line, so newlines in messages can't fake extra log entries
        $message = str_replace(["\r", "\n"], ['\r', '\n'], $message);
        $line = sprintf("%s %s [%s] %s\n", date('c'), $level, $requestId !== '' ? $requestId : '-', $message);

        $dir = dirname($this->file);
        if ((is_dir($dir) || @mkdir($dir, 0775, true)) && @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX) !== false) {
            return;
        }
        error_log(rtrim($line));
    }
}
