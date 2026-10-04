<?php

namespace Bongo\Packages\Sms\Drivers;

use Bongo\Packages\Sms\Contracts\SMSDriver;

class LogDriver implements SMSDriver
{
    /** @var array<int, array{to: string, message: string, time: string}> In-memory sent messages for testing */
    protected static array $sent = [];

    protected string $filePath;

    public function __construct(array $config = [])
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $file = $config['file'] ?? 'storage/logs/sms.log';
        $this->filePath = str_starts_with($file, '/') || preg_match('/^[A-Za-z]:/', $file)
            ? $file
            : $base . '/' . ltrim($file, '/');
    }

    public function send(string $to, string $message): array
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $now = date('Y-m-d H:i:s');
        $entry = sprintf("[%s] TO: %s | MSG: %s\n", $now, $to, str_replace(["\r", "\n"], ' ', $message));
        @file_put_contents($this->filePath, $entry, FILE_APPEND | LOCK_EX);

        $record = [
            'to'      => $to,
            'message' => $message,
            'time'    => $now,
        ];
        self::$sent[] = $record;

        return [
            'success' => true,
            'id'      => 'LOG_' . bin2hex(random_bytes(6)),
            'raw'     => $record,
            'error'   => null,
        ];
    }

    /**
     * Get sent messages recorded in memory.
     *
     * @return array<int, array{to: string, message: string, time: string}>
     */
    public static function sent(): array
    {
        return self::$sent;
    }

    /**
     * Clear recorded messages in memory.
     */
    public static function clear(): void
    {
        self::$sent = [];
    }
}
