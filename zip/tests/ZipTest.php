<?php

declare(strict_types=1);

namespace Bongo\Packages\Zip\Tests;

use Bongo\Packages\Zip\Exceptions\ZipException;
use Bongo\Packages\Zip\Zip;
use System\Testing\TestCase;
use ZipArchive;

class ZipTest extends TestCase
{
    protected string $tempDir;

    public function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bongo_zip_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    public function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testCreateArchiveWithFilesAndStrings(): void
    {
        $file1 = $this->tempDir . DIRECTORY_SEPARATOR . 'doc.txt';
        file_put_contents($file1, 'Document text content');

        $zipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'test.zip';

        $builder = Zip::create($zipPath);
        $builder->addFile($file1, 'custom_doc.txt')
            ->addFromString('memory.json', json_encode(['status' => 'ok']))
            ->addEmptyDir('empty_folder')
            ->save();

        $this->assertTrue(file_exists($zipPath));
        $this->assertTrue(filesize($zipPath) > 0);
        $this->assertSame(2, $builder->count());
    }

    public function testOpenReadAndHas(): void
    {
        $zipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'read_test.zip';
        Zip::create($zipPath)
            ->addFromString('welcome.txt', 'Welcome to Bongo PHP!')
            ->save();

        $this->assertTrue(Zip::has($zipPath, 'welcome.txt'));
        $this->assertFalse(Zip::has($zipPath, 'missing.txt'));

        $content = Zip::read($zipPath, 'welcome.txt');
        $this->assertSame('Welcome to Bongo PHP!', $content);
    }

    public function testListArchiveEntries(): void
    {
        $zipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'list_test.zip';
        Zip::create($zipPath)
            ->addFromString('file1.txt', 'AAA')
            ->addFromString('file2.txt', 'BBBB')
            ->save();

        $list = Zip::list($zipPath);
        $this->assertSame(2, count($list));

        $names = array_column($list, 'name');
        $this->assertTrue(in_array('file1.txt', $names, true));
        $this->assertTrue(in_array('file2.txt', $names, true));
    }

    public function testExtractArchive(): void
    {
        $zipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'extract_test.zip';
        Zip::create($zipPath)
            ->addFromString('hello.txt', 'Hello World!')
            ->addFromString('nested/deep.txt', 'Deep content')
            ->save();

        $extractDest = $this->tempDir . DIRECTORY_SEPARATOR . 'extracted';
        Zip::extract($zipPath, $extractDest);

        $this->assertTrue(file_exists($extractDest . '/hello.txt'));
        $this->assertSame('Hello World!', file_get_contents($extractDest . '/hello.txt'));

        $this->assertTrue(file_exists($extractDest . '/nested/deep.txt'));
        $this->assertSame('Deep content', file_get_contents($extractDest . '/nested/deep.txt'));
    }

    public function testDirectoryRecursionAndExclusion(): void
    {
        $sourceDir = $this->tempDir . DIRECTORY_SEPARATOR . 'source';
        mkdir($sourceDir . '/sub', 0755, true);

        file_put_contents($sourceDir . '/file1.txt', 'Content 1');
        file_put_contents($sourceDir . '/sub/file2.txt', 'Content 2');
        file_put_contents($sourceDir . '/ignored.log', 'Log error');

        $zipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'dir_test.zip';
        Zip::create($zipPath)
            ->exclude(['*.log'])
            ->add($sourceDir, 'pkg')
            ->save();

        $this->assertTrue(Zip::has($zipPath, 'pkg/file1.txt'));
        $this->assertTrue(Zip::has($zipPath, 'pkg/sub/file2.txt'));
        $this->assertFalse(Zip::has($zipPath, 'pkg/ignored.log'));
    }

    public function testZipSlipPathTraversalBlocked(): void
    {
        $zipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'malicious.zip';

        // Construct malicious zip with ../
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('../outside.txt', 'Malicious content');
        $zip->close();

        $extractDest = $this->tempDir . DIRECTORY_SEPARATOR . 'safe_zone';

        $this->assertThrows(ZipException::class, function () use ($zipPath, $extractDest) {
            Zip::extract($zipPath, $extractDest);
        });
    }

    public function testExceptionsOnInvalidOperations(): void
    {
        $this->assertThrows(ZipException::class, function () {
            Zip::open('/path/that/does/not/exist/999.zip');
        });

        $zipPath = $this->tempDir . DIRECTORY_SEPARATOR . 'empty.zip';
        Zip::create($zipPath)->addFromString('a.txt', 'A')->save();

        $this->assertThrows(ZipException::class, function () use ($zipPath) {
            Zip::read($zipPath, 'missing.txt');
        });
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $target = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($target)) {
                $this->removeDirectory($target);
            } else {
                @unlink($target);
            }
        }

        @rmdir($dir);
    }
}
