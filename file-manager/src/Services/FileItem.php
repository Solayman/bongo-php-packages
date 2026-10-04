<?php

declare(strict_types=1);

namespace Bongo\Packages\FileManager\Services;

class FileItem implements \JsonSerializable
{
    public function __construct(
        public string $name,
        public string $path,
        public bool $isDir,
        public int $size = 0,
        public string $type = 'file',
        public ?string $url = null,
        public ?string $thumbUrl = null,
        public ?string $extension = null,
        public ?string $updatedAt = null,
        public string $editorLanguage = 'plaintext'
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'is_dir' => $this->isDir,
            'size' => $this->size,
            'size_human' => self::formatBytes($this->size),
            'type' => $this->type,
            'url' => $this->url,
            'thumb_url' => $this->thumbUrl ?? $this->url,
            'extension' => $this->extension,
            'updated_at' => $this->updatedAt,
            'editor_language' => $this->editorLanguage,
            'is_editable' => $this->type === 'code',
            'is_image' => $this->type === 'image',
        ];
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        $val = round($bytes / pow(1024, $i), 2);
        return "{$val} {$units[$i]}";
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
