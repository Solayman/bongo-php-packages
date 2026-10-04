<?php

declare(strict_types=1);

namespace Bongo\Packages\Ftp;

use System\Storage\Disk;

class FtpDisk extends Disk
{
    protected ?FtpClient $client = null;

    public function root(): string
    {
        return 'ftp://' . ($this->config['host'] ?? '127.0.0.1') . '/' . ltrim((string) ($this->config['root'] ?? ''), '/');
    }

    public function client(): FtpClient
    {
        if ($this->client === null) {
            $this->client = new FtpClient($this->config);
        }
        return $this->client;
    }

    public function put(string $relative, string $contents): bool
    {
        return $this->client()->upload($relative, $contents);
    }

    public function get(string $relative): ?string
    {
        return $this->client()->download($relative);
    }

    public function exists(string $relative): bool
    {
        return $this->client()->exists($relative);
    }

    public function size(string $relative): int
    {
        return (int) ($this->client()->size($relative) ?? 0);
    }

    public function lastModified(string $relative): int
    {
        return (int) ($this->client()->lastModified($relative) ?? 0);
    }

    public function delete(string|array|null $relative): bool
    {
        if ($relative === null || $relative === '' || $relative === []) {
            return false;
        }

        $paths = (array) $relative;
        $success = true;
        foreach ($paths as $p) {
            if (!$this->client()->delete((string) $p)) {
                $success = false;
            }
        }
        return $success;
    }

    public function files(string $directory = ''): array
    {
        return $this->client()->list($directory);
    }

    public function makeDirectory(string $path): bool
    {
        return $this->client()->makeDirectory($path);
    }

    public function deleteDirectory(string $path): bool
    {
        return $this->client()->deleteDirectory($path);
    }
}
