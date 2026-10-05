<?php

declare(strict_types=1);

namespace Bongo\Packages\Ftp\Tests;

use Bongo\Packages\Ftp\Exceptions\FtpException;
use Bongo\Packages\Ftp\FtpClient;
use Bongo\Packages\Ftp\FtpDisk;
use System\Testing\TestCase;

class FtpTest extends TestCase
{
    public function setUp(): void
    {
        FtpClient::fake();
    }

    public function tearDown(): void
    {
        FtpClient::stopFaking();
    }

    public function testFtpClientMockUploadAndDownload(): void
    {
        $client = new FtpClient(['root' => '/']);

        $this->assertTrue($client->upload('test.txt', 'Hello Bongo FTP!'));
        $this->assertTrue($client->exists('test.txt'));
        $this->assertSame('Hello Bongo FTP!', $client->download('test.txt'));
        $this->assertSame(16, $client->size('test.txt'));
        $this->assertNotNull($client->lastModified('test.txt'));

        $list = $client->list();
        $this->assertTrue(in_array('/test.txt', $list, true));

        $this->assertTrue($client->delete('test.txt'));
        $this->assertFalse($client->exists('test.txt'));
        $this->assertNull($client->download('test.txt'));
    }

    public function testFtpDiskStorageDriverIntegration(): void
    {
        $disk = new FtpDisk('ftp', [
            'host' => 'ftp.example.com',
            'root' => '/var/www',
        ]);

        $this->assertSame('ftp://ftp.example.com/var/www', $disk->root());

        $this->assertTrue($disk->put('documents/contract.pdf', 'BINARY_PDF_DATA'));
        $this->assertTrue($disk->exists('documents/contract.pdf'));
        $this->assertSame('BINARY_PDF_DATA', $disk->get('documents/contract.pdf'));
        $this->assertSame(15, $disk->size('documents/contract.pdf'));

        $files = $disk->files('documents');
        $this->assertTrue(count($files) > 0);

        $this->assertTrue($disk->delete('documents/contract.pdf'));
        $this->assertFalse($disk->exists('documents/contract.pdf'));
    }

    public function testDirectories(): void
    {
        $client = new FtpClient(['root' => '/']);
        $this->assertTrue($client->makeDirectory('backups'));
        $client->upload('backups/db.sql', 'SQL');
        $this->assertTrue($client->exists('backups/db.sql'));

        $this->assertTrue($client->deleteDirectory('backups'));
        $this->assertFalse($client->exists('backups/db.sql'));
    }

    public function testConnectionFailureThrowsFtpException(): void
    {
        FtpClient::stopFaking();

        $client = new FtpClient([
            'host' => '127.0.0.1',
            'port' => 59999, // Unreachable port
            'timeout' => 1,
        ]);

        $this->assertThrows(FtpException::class, function () use ($client) {
            $client->connect();
        });
    }

    public function testPathTraversalAttemptCannotEscapeRoot(): void
    {
        $client = new FtpClient(['root' => '/var/www']);
        $client->upload('../../etc/passwd', 'dummy');

        // Path should be normalized safely inside /var/www/etc/passwd, not escaping root
        $this->assertTrue($client->exists('etc/passwd'));
        $this->assertSame('dummy', $client->download('../../etc/passwd'));
    }
}
