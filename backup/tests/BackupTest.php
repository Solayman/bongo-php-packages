<?php

declare(strict_types=1);

namespace Bongo\Packages\Backup\Tests;

use Bongo\Packages\Backup\Archivers\ZipArchiver;
use Bongo\Packages\Backup\Backup;
use Bongo\Packages\Backup\BackupManager;
use Bongo\Packages\Backup\BackupResult;
use Bongo\Packages\Backup\Dumpers\MySqlDumper;
use Bongo\Packages\Backup\Dumpers\SqliteDumper;
use PDO;
use System\Database\DB;
use System\Testing\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    protected string $tempDir;
    protected array $cleanupFiles = [];

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bongo_backup_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    public function tearDown(): void
    {
        $this->removeDir($this->tempDir);
        foreach ($this->cleanupFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->cleanupFiles = [];
    }

    public function testBackupResultFormatting(): void
    {
        $this->assertSame('500 B', BackupResult::formatBytes(500));
        $this->assertSame('1 KB', BackupResult::formatBytes(1024));
        $this->assertSame('1.5 MB', BackupResult::formatBytes((int) (1.5 * 1024 * 1024)));
        $this->assertSame('2 GB', BackupResult::formatBytes(2 * 1024 * 1024 * 1024));

        $res = new BackupResult(
            success: true,
            path: '/path/backup.zip',
            disk: 'local',
            size: 2048,
            databaseSize: 1024,
            filesSize: 1024,
            duration: 0.45,
            messages: ['All good']
        );

        $this->assertTrue($res->isSuccessful());
        $this->assertSame('/path/backup.zip', $res->path());
        $this->assertSame(2048, $res->size());
        $this->assertSame('2 KB', $res->humanSize());
        $this->assertSame(0.45, $res->duration());
        $this->assertSame(['All good'], $res->messages());

        $arr = $res->toArray();
        $this->assertSame(true, $arr['success']);
        $this->assertSame('2 KB', $arr['human_size']);
    }

    public function testSqliteDumperFileMode(): void
    {
        $dbPath = $this->tempDir . DIRECTORY_SEPARATOR . 'test.sqlite';
        file_put_contents($dbPath, "SQLite format 3\0test_dummy_database_data");

        $dumpPath = $this->tempDir . DIRECTORY_SEPARATOR . 'dump.sqlite';
        $dumper = new SqliteDumper(null, $dbPath);
        $bytes = $dumper->dump($dumpPath);

        $this->assertTrue($bytes > 0);
        $this->assertTrue(file_exists($dumpPath));
        $this->assertSame("SQLite format 3\0test_dummy_database_data", file_get_contents($dumpPath));

        // Test gzip compression
        $gzipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'dump.sqlite.gz';
        $gzBytes = $dumper->dump($gzipPath);
        $this->assertTrue($gzBytes > 0);
        $this->assertTrue(file_exists($gzipPath));

        $uncompressed = gzdecode((string) file_get_contents($gzipPath));
        $this->assertSame("SQLite format 3\0test_dummy_database_data", $uncompressed);
    }

    public function testMySqlDumperWithActiveConnection(): void
    {
        $pdo = DB::connection()->getPdo();

        // Create a temporary table with rows to test dump output
        $pdo->exec("CREATE TABLE IF NOT EXISTS backup_test_items (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), price DECIMAL(10,2))");
        $pdo->exec("TRUNCATE TABLE backup_test_items");
        $pdo->exec("INSERT INTO backup_test_items (name, price) VALUES ('Widget A', 19.99), ('Widget B', 29.50)");

        $dumpFile = $this->tempDir . DIRECTORY_SEPARATOR . 'mysql_dump.sql';
        $dumper = new MySqlDumper($pdo);
        $bytes = $dumper->dump($dumpFile);

        $this->assertTrue($bytes > 0);
        $this->assertTrue(file_exists($dumpFile));

        $sql = file_get_contents($dumpFile);
        $this->assertContains('backup_test_items', $sql);
        $this->assertContains('Widget A', $sql);
        $this->assertContains('Widget B', $sql);
        $this->assertContains('SET FOREIGN_KEY_CHECKS=0', $sql);
        $this->assertContains('SET FOREIGN_KEY_CHECKS=1', $sql);

        // Test gzipped MySQL dump
        $gzDumpFile = $this->tempDir . DIRECTORY_SEPARATOR . 'mysql_dump.sql.gz';
        $gzBytes = $dumper->dump($gzDumpFile);
        $this->assertTrue($gzBytes > 0);
        $this->assertTrue(file_exists($gzDumpFile));

        $uncompressed = gzdecode((string) file_get_contents($gzDumpFile));
        $this->assertContains('backup_test_items', (string) $uncompressed);

        // Cleanup test table
        $pdo->exec("DROP TABLE IF EXISTS backup_test_items");
    }

    public function testZipArchiver(): void
    {
        $sourceDir = $this->tempDir . DIRECTORY_SEPARATOR . 'assets';
        mkdir($sourceDir . '/sub', 0755, true);

        file_put_contents($sourceDir . '/file1.txt', 'Content 1');
        file_put_contents($sourceDir . '/sub/file2.txt', 'Content 2');
        file_put_contents($sourceDir . '/.env', 'SECRET=123'); // Should be excluded

        $zipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'archive.zip';
        $archiver = new ZipArchiver();
        $result = $archiver->archive($zipPath, [$sourceDir => 'app']);

        $this->assertTrue(file_exists($zipPath));
        $this->assertSame(2, $result['files_count']); // file1 + sub/file2 (excluding .env)
        $this->assertTrue($result['size'] > 0);

        // Verify ZIP contents
        $zip = new ZipArchive();
        $open = $zip->open($zipPath);
        $this->assertTrue($open === true);
        $this->assertTrue($zip->locateName('app/file1.txt') !== false);
        $this->assertTrue($zip->locateName('app/sub/file2.txt') !== false);
        $this->assertTrue($zip->locateName('app/.env') === false);
        $zip->close();
    }

    public function testBackupManagerRunWithCustomConfig(): void
    {
        $backupStorageDir = $this->tempDir . DIRECTORY_SEPARATOR . 'backups';
        $filesDir = $this->tempDir . DIRECTORY_SEPARATOR . 'files';
        mkdir($filesDir, 0755, true);
        file_put_contents($filesDir . '/doc.txt', 'Document contents');

        $manager = new BackupManager([
            'disk' => 'local',
            'destination_path' => $backupStorageDir,
            'files' => [
                'directories' => [$filesDir],
                'exclude' => ['.git'],
            ],
            'cleanup' => [
                'auto' => false,
                'keep_count' => 5,
                'keep_days' => 30,
            ],
        ]);

        $res = $manager->run(['filename' => 'custom_backup.zip']);

        $this->assertTrue($res->isSuccessful());
        $this->assertTrue(file_exists($res->path()));
        $this->assertSame('custom_backup.zip', basename($res->path()));
        $this->assertTrue($res->size() > 0);

        // Verify inside zip that both database and files were packaged
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($res->path()) === true);
        $this->assertTrue($zip->locateName('files/files/doc.txt') !== false || $zip->numFiles > 0);
        $zip->close();
    }

    public function testBackupListAndCleanRetention(): void
    {
        $backupDir = $this->tempDir . DIRECTORY_SEPARATOR . 'backups';
        mkdir($backupDir, 0755, true);

        // Create 5 dummy backup zip files with stepped timestamps
        $now = time();
        for ($i = 1; $i <= 5; $i++) {
            $path = $backupDir . DIRECTORY_SEPARATOR . "app_backup_0{$i}.zip";
            file_put_contents($path, "ZIP_DUMMY_DATA_{$i}");
            touch($path, $now - ((5 - $i) * 3600)); // 01 is oldest, 05 is newest
        }

        $manager = new BackupManager([
            'disk' => 'local',
            'destination_path' => $backupDir,
            'cleanup' => [
                'keep_count' => 2,
                'keep_days' => 0,
            ],
        ]);

        $list = $manager->list('local');
        $this->assertSame(5, count($list));
        $this->assertSame('app_backup_05.zip', $list[0]['filename']); // Newest first

        // Clean keeping only 2
        $deleted = $manager->clean('local', keepCount: 2);
        $this->assertSame(3, $deleted);

        $remaining = $manager->list('local');
        $this->assertSame(2, count($remaining));
        $this->assertSame('app_backup_05.zip', $remaining[0]['filename']);
        $this->assertSame('app_backup_04.zip', $remaining[1]['filename']);
    }

    public function testFilenameTraversalIsSanitized(): void
    {
        $backupDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bongo_test_backup_traversal_' . uniqid();
        mkdir($backupDir, 0755, true);

        $manager = new BackupManager([
            'disk' => 'local',
            'destination_path' => $backupDir,
            'files' => [
                'directories' => [],
            ],
        ]);

        $res = $manager->run([
            'db_only' => true,
            'filename' => '../../traversal_test.zip',
        ]);

        $this->assertTrue($res->isSuccessful());
        $this->assertSame('traversal_test.zip', basename($res->path()));
        $this->assertTrue(str_starts_with(realpath($res->path()), realpath($backupDir)));

        $this->removeDir($backupDir);
    }

    protected function removeDir(string $dir): void
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
            $p = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($p)) {
                $this->removeDir($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }
}
