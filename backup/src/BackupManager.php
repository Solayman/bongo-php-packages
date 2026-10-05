<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup;

use Bongo\Packages\Backup\Archivers\ZipArchiver;
use Bongo\Packages\Backup\Dumpers\DumperInterface;
use Bongo\Packages\Backup\Dumpers\MySqlDumper;
use Bongo\Packages\Backup\Dumpers\PostgresDumper;
use Bongo\Packages\Backup\Dumpers\SqliteDumper;
use Bongo\Packages\Backup\Exceptions\BackupException;
use PDO;
use System\Database\DB;
use System\Storage\Storage;
use Throwable;

class BackupManager
{
    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (function_exists('config') ? (array) (config('backup') ?? []) : []);
    }

    /**
     * Execute a backup run.
     *
     * @param array $options [
     *     'db_only' => bool,
     *     'files_only' => bool,
     *     'disk' => string,
     *     'filename' => string,
     *     'connection' => string
     * ]
     */
    public function run(array $options = []): BackupResult
    {
        $startTime = microtime(true);
        $messages = [];

        $dbOnly = (bool) ($options['db_only'] ?? false);
        $filesOnly = (bool) ($options['files_only'] ?? false);
        $disk = (string) ($options['disk'] ?? ($this->config['disk'] ?? 'local'));
        $connectionName = $options['connection'] ?? ($this->config['database']['connection'] ?? null);

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bongo_backup_' . uniqid();
        mkdir($tmpDir, 0755, true);

        $timestamp = date('Y-m-d_His');
        $rawFilename = (string) ($options['filename'] ?? "{$appName}_backup_{$timestamp}.zip");
        $zipFilename = basename(str_replace(["\0", '\\'], ['', '/'], $rawFilename));
        if ($zipFilename === '' || $zipFilename === '.' || $zipFilename === '..') {
            $zipFilename = "{$appName}_backup_{$timestamp}.zip";
        }

        $dbSize = 0;
        $filesSize = 0;
        $dbDumpFile = null;

        try {
            // 1. Database dump
            if (!$filesOnly) {
                $messages[] = "Starting database backup...";
                $dumper = $this->createDumper($connectionName);
                $driver = $this->getConnectionDriver($connectionName);
                $ext = $driver === 'sqlite' ? 'sqlite' : 'sql';
                $dbDumpFile = $tmpDir . DIRECTORY_SEPARATOR . "database.{$ext}";

                $dbSize = $dumper->dump($dbDumpFile);
                $messages[] = "Database dumped successfully (" . BackupResult::formatBytes($dbSize) . ").";
            }

            // 2. Package into archive or standalone file
            $archivePath = $tmpDir . DIRECTORY_SEPARATOR . $zipFilename;
            $archiver = new ZipArchiver((array) ($this->config['files']['exclude'] ?? []));

            $sources = [];
            if ($dbDumpFile !== null && file_exists($dbDumpFile)) {
                $sources[$dbDumpFile] = '';
            }

            if (!$dbOnly) {
                $fileDirs = (array) ($this->config['files']['directories'] ?? [
                    'storage/app',
                    'public/storage',
                ]);

                foreach ($fileDirs as $dir) {
                    $resolvedDir = $this->resolvePath($dir);
                    if (is_dir($resolvedDir)) {
                        $sources[$resolvedDir] = 'files/' . basename($dir);
                    }
                }
                $messages[] = "Archiving files from configured directories...";
            }

            $archived = $archiver->archive($archivePath, $sources);
            $totalSize = $archived['size'];
            $filesSize = max(0, $totalSize - $dbSize);
            $messages[] = "Archive created with {$archived['files_count']} entries (" . BackupResult::formatBytes($totalSize) . ").";

            // 3. Store to destination disk
            $finalPath = $this->storeToDisk($archivePath, $zipFilename, $disk);
            $messages[] = "Backup saved to disk [{$disk}] at: {$finalPath}";

            // 4. Auto-clean if enabled
            if ((bool) ($this->config['cleanup']['auto'] ?? true)) {
                $deleted = $this->clean($disk);
                if ($deleted > 0) {
                    $messages[] = "Cleaned {$deleted} older backup(s) per retention policy.";
                }
            }

            $duration = round(microtime(true) - $startTime, 2);

            return new BackupResult(
                success: true,
                path: $finalPath,
                disk: $disk,
                size: $totalSize,
                databaseSize: $dbSize,
                filesSize: $filesSize,
                duration: $duration,
                messages: $messages
            );
        } catch (Throwable $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $messages[] = "Backup failed: " . $e->getMessage();

            return new BackupResult(
                success: false,
                path: '',
                disk: $disk,
                size: 0,
                duration: $duration,
                messages: $messages
            );
        } finally {
            $this->removeDirectory($tmpDir);
        }
    }

    /**
     * List all existing backups on the specified disk.
     */
    public function list(?string $disk = null): array
    {
        $disk = $disk ?? ($this->config['disk'] ?? 'local');
        $backups = [];

        if ($disk === 'local') {
            $backupDir = $this->getLocalBackupDirectory();
            if (!is_dir($backupDir)) {
                return [];
            }

            $files = scandir($backupDir);
            if ($files === false) {
                return [];
            }

            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || !str_ends_with($file, '.zip')) {
                    continue;
                }

                $full = $backupDir . DIRECTORY_SEPARATOR . $file;
                $size = filesize($full) ?: 0;
                $mtime = filemtime($full) ?: time();

                $backups[] = [
                    'filename' => $file,
                    'path' => $full,
                    'size' => $size,
                    'human_size' => BackupResult::formatBytes($size),
                    'date' => date('Y-m-d H:i:s', $mtime),
                    'timestamp' => $mtime,
                    'disk' => 'local',
                ];
            }
        } elseif (class_exists(Storage::class)) {
            $files = Storage::disk($disk)->files('backups');
            foreach ($files as $file) {
                if (!str_ends_with($file, '.zip')) {
                    continue;
                }
                $size = Storage::disk($disk)->size($file) ?? 0;
                $mtime = Storage::disk($disk)->lastModified($file) ?? time();

                $backups[] = [
                    'filename' => basename($file),
                    'path' => $file,
                    'size' => $size,
                    'human_size' => BackupResult::formatBytes($size),
                    'date' => date('Y-m-d H:i:s', $mtime),
                    'timestamp' => $mtime,
                    'disk' => $disk,
                ];
            }
        }

        // Sort descending by timestamp
        usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $backups;
    }

    /**
     * Clean old backups according to retention policy.
     */
    public function clean(?string $disk = null, ?int $keepCount = null, ?int $keepDays = null): int
    {
        $disk = $disk ?? ($this->config['disk'] ?? 'local');
        $keepCount = $keepCount ?? (int) ($this->config['cleanup']['keep_count'] ?? 10);
        $keepDays = $keepDays ?? (int) ($this->config['cleanup']['keep_days'] ?? 30);

        $backups = $this->list($disk);
        if (count($backups) <= $keepCount && $keepDays <= 0) {
            return 0;
        }

        $deletedCount = 0;
        $cutoffTime = $keepDays > 0 ? time() - ($keepDays * 86400) : 0;

        foreach ($backups as $index => $backup) {
            $isBeyondKeepCount = $index >= $keepCount;
            $isBeyondKeepDays = $cutoffTime > 0 && $backup['timestamp'] < $cutoffTime;

            if ($isBeyondKeepCount || $isBeyondKeepDays) {
                if ($disk === 'local') {
                    if (@unlink($backup['path'])) {
                        $deletedCount++;
                    }
                } elseif (class_exists(Storage::class)) {
                    if (Storage::disk($disk)->delete($backup['path'])) {
                        $deletedCount++;
                    }
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Create appropriate database dumper.
     */
    public function createDumper(?string $connection = null): DumperInterface
    {
        $conn = DB::connection($connection);
        $driver = $conn->getDriver();
        $pdo = $conn->getPdo();

        return match ($driver) {
            'sqlite' => new SqliteDumper($pdo),
            'pgsql' => new PostgresDumper($pdo),
            default => new MySqlDumper($pdo),
        };
    }

    protected function getConnectionDriver(?string $connection = null): string
    {
        return DB::connection($connection)->getDriver();
    }

    protected function storeToDisk(string $sourceFile, string $filename, string $disk): string
    {
        if ($disk === 'local') {
            $destDir = $this->getLocalBackupDirectory();
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $target = $destDir . DIRECTORY_SEPARATOR . $filename;
            if (!@copy($sourceFile, $target)) {
                throw new BackupException("Failed to save backup file to [{$target}].");
            }
            return $target;
        }

        if (class_exists(Storage::class)) {
            $contents = file_get_contents($sourceFile);
            if ($contents === false) {
                throw new BackupException("Failed to read generated archive for cloud disk upload.");
            }
            $target = 'backups/' . $filename;
            Storage::disk($disk)->put($target, $contents);
            return $target;
        }

        throw new BackupException("Unsupported storage disk [{$disk}] for backups.");
    }

    public function getLocalBackupDirectory(): string
    {
        $custom = $this->config['destination_path'] ?? null;
        if ($custom !== null) {
            return $this->resolvePath($custom);
        }

        return $this->resolvePath('storage/backups');
    }

    protected function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/') || (strlen($path) > 2 && $path[1] === ':')) {
            return $path;
        }

        $base = defined('APP_ROOT') ? APP_ROOT : (function_exists('base_path') ? base_path() : dirname(__DIR__, 4) . '/BongoMvc');
        return rtrim($base, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $target = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($target)) {
                $this->removeDirectory($target);
            } else {
                @unlink($target);
            }
        }

        @rmdir($dir);
    }
}
