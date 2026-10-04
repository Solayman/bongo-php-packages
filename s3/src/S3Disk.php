<?php

namespace Bongo\Packages\S3;

use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use System\Http\HttpException;
use System\Storage\Disk;
use System\Storage\DownloadResponse;

/**
 * Storage Disk driver backed by Amazon S3 and S3-compatible object storage.
 *
 * Implements the standard Bongo Storage API:
 *   Storage::disk('s3')->put('uploads/photo.jpg', $data);
 *   Storage::disk('s3')->get('uploads/photo.jpg');
 *   Storage::disk('s3')->url('uploads/photo.jpg');
 *   Storage::disk('s3')->temporaryUrl('invoices/10.pdf', 3600);
 *   Storage::disk('s3')->download('invoices/10.pdf');
 */
class S3Disk extends Disk
{
    protected ?S3Client $client = null;

    public function root(): string
    {
        return 's3://' . ($this->config['bucket'] ?? 'default');
    }

    /**
     * Get underlying S3Client instance.
     */
    public function client(): S3Client
    {
        if ($this->client === null) {
            $this->client = new S3Client($this->config);
        }
        return $this->client;
    }

    /**
     * Set explicit S3Client instance (for testing/mocking).
     */
    public function setClient(S3Client $client): void
    {
        $this->client = $client;
    }

    /**
     * Normalize object key and guard against path traversal.
     */
    public function path(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || preg_match('/^[a-z]:/i', $relative)) {
            throw new InvalidArgumentException("Invalid storage path [$relative]");
        }
        return ltrim($relative, '/');
    }

    public function put(string $relative, string $contents): bool
    {
        return $this->client()->putObject($this->path($relative), $contents);
    }

    public function putFile(string $relative, string $source, bool $isUpload = false): void
    {
        if (!is_file($source)) {
            throw new RuntimeException("Source file does not exist: $source");
        }

        $contents = file_get_contents($source);
        if ($contents === false) {
            throw new RuntimeException("Could not read source file: $source");
        }

        $ok = $this->put($relative, $contents);
        if (!$ok) {
            throw new RuntimeException("Could not store the file at $relative");
        }

        if ($isUpload) {
            @unlink($source);
        }
    }

    public function get(string $relative): ?string
    {
        return $this->client()->getObject($this->path($relative));
    }

    public function exists(string $relative): bool
    {
        return $this->client()->headObject($this->path($relative)) !== null;
    }

    public function missing(string $relative): bool
    {
        return !$this->exists($relative);
    }

    public function size(string $relative): int
    {
        $headers = $this->client()->headObject($this->path($relative));
        if ($headers === null) {
            return 0;
        }
        return (int) ($headers['content-length'] ?? 0);
    }

    public function lastModified(string $relative): int
    {
        $headers = $this->client()->headObject($this->path($relative));
        if ($headers === null || !isset($headers['last-modified'])) {
            return 0;
        }
        return (int) strtotime($headers['last-modified']);
    }

    public function delete(string|array|null $relative): bool
    {
        if ($relative === null || $relative === '' || $relative === []) {
            return false;
        }

        $paths = (array) $relative;
        $success = true;
        foreach ($paths as $p) {
            if (!$this->client()->deleteObject($this->path($p))) {
                $success = false;
            }
        }

        return $success;
    }

    public function url(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }
        return $this->client()->getPublicUrl($this->path($relative));
    }

    /**
     * Generate a presigned temporary URL for secure direct downloads.
     *
     * @param string $relative Target object path
     * @param int|DateTimeInterface $expiration Lifetime in seconds, or expiration DateTime
     * @param array $options Additional options
     */
    public function temporaryUrl(string $relative, mixed $expiration, array $options = []): string
    {
        $seconds = 3600;
        if ($expiration instanceof DateTimeInterface) {
            $seconds = max(1, $expiration->getTimestamp() - time());
        } elseif (is_numeric($expiration)) {
            $seconds = (int) $expiration;
            if ($seconds > 86400 * 365) {
                // Given a unix timestamp
                $seconds = max(1, $seconds - time());
            }
        }

        $method = strtoupper((string) ($options['method'] ?? 'GET'));
        return $this->client()->generatePresignedUrl($this->path($relative), $seconds, $method);
    }

    /**
     * Stream an S3 object as a DownloadResponse with automatic temporary file cleanup.
     */
    public function download(string $relative, ?string $filename = null, array $headers = []): DownloadResponse
    {
        $contents = $this->get($relative);
        if ($contents === null) {
            throw new HttpException(404, "File not found [$relative]");
        }

        $filename ??= basename(str_replace('\\', '/', $relative));
        $tempPath = tempnam(sys_get_temp_dir(), 'bongo_s3_dl_');
        file_put_contents($tempPath, $contents);

        return new DownloadResponse(
            path: $tempPath,
            filename: $filename,
            headers: $headers,
            mimeType: null,
            deleteAfterSend: true
        );
    }

    public function files(string $directory = ''): array
    {
        return $this->client()->listObjectsV2($directory, '/');
    }

    public function allFiles(string $directory = ''): array
    {
        return $this->client()->listObjectsV2($directory, '');
    }

    public function makeDirectory(string $directory): bool
    {
        $path = rtrim($this->path($directory), '/') . '/';
        return $this->client()->putObject($path, '');
    }

    public function deleteDirectory(string $directory): bool
    {
        $cleanDir = rtrim($this->path($directory), '/');
        $files = $this->allFiles($cleanDir);
        foreach ($files as $file) {
            $this->client()->deleteObject($file);
        }
        $this->client()->deleteObject($cleanDir . '/');
        return true;
    }
}
