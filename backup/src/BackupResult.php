<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup;

class BackupResult
{
    public function __construct(
        public bool $success,
        public string $path,
        public string $disk = 'local',
        public int $size = 0,
        public int $databaseSize = 0,
        public int $filesSize = 0,
        public float $duration = 0.0,
        public array $messages = []
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->success;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function duration(): float
    {
        return $this->duration;
    }

    public function messages(): array
    {
        return $this->messages;
    }

    public function humanSize(): string
    {
        return static::formatBytes($this->size);
    }

    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'path' => $this->path,
            'disk' => $this->disk,
            'size' => $this->size,
            'human_size' => $this->humanSize(),
            'database_size' => $this->databaseSize,
            'files_size' => $this->filesSize,
            'duration' => $this->duration,
            'messages' => $this->messages,
        ];
    }
}
