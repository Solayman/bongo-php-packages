<?php

namespace Bongo\Packages\S3\Tests;

use Bongo\Packages\S3\S3Client;
use Bongo\Packages\S3\S3Disk;
use System\Storage\DownloadResponse;
use System\Testing\TestCase;

class S3Test extends TestCase
{
    protected array $config = [
        'driver'                  => 's3',
        'key'                     => 'AKIAIOSFODNN7EXAMPLE',
        'secret'                  => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        'region'                  => 'us-east-1',
        'bucket'                  => 'mybucket',
        'endpoint'                => null,
        'use_path_style_endpoint' => false,
        'url'                     => null,
    ];

    public function tearDown(): void
    {
        S3Client::setMockTransport(null);
    }

    public function testSigV4SigningHeadersAndAuthorization(): void
    {
        $recorded = [];
        S3Client::setMockTransport(function (string $method, string $url, array $headers, string $body) use (&$recorded) {
            $recorded = [
                'method'  => $method,
                'url'     => $url,
                'headers' => $headers,
                'body'    => $body,
            ];
            return ['status' => 200, 'headers' => [], 'body' => ''];
        });

        $client = new S3Client($this->config);
        $client->putObject('test.txt', 'Hello S3');

        $this->assertSame('PUT', $recorded['method']);
        $this->assertSame('https://mybucket.s3.us-east-1.amazonaws.com/test.txt', $recorded['url']);
        $this->assertTrue(isset($recorded['headers']['Authorization']));
        $this->assertContains('AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE', $recorded['headers']['Authorization']);
        $this->assertContains('SignedHeaders=', $recorded['headers']['Authorization']);
        $this->assertContains('Signature=', $recorded['headers']['Authorization']);
        $this->assertSame('mybucket.s3.us-east-1.amazonaws.com', $recorded['headers']['Host']);
        $this->assertSame(hash('sha256', 'Hello S3'), $recorded['headers']['x-amz-content-sha256']);
    }

    public function testPutAndGetObject(): void
    {
        S3Client::setMockTransport(function (string $method, string $url, array $headers, string $body) {
            if ($method === 'PUT') {
                return ['status' => 200, 'headers' => [], 'body' => ''];
            }
            if ($method === 'GET') {
                return ['status' => 200, 'headers' => ['content-type' => 'text/plain'], 'body' => 'Stored file payload'];
            }
            return ['status' => 404, 'headers' => [], 'body' => ''];
        });

        $disk = new S3Disk('s3', $this->config);
        $this->assertTrue($disk->put('documents/file.txt', 'Stored file payload'));
        $this->assertSame('Stored file payload', $disk->get('documents/file.txt'));
    }

    public function testExistsAndMissing(): void
    {
        S3Client::setMockTransport(function (string $method, string $url) {
            if (str_contains($url, 'found.txt')) {
                return ['status' => 200, 'headers' => ['content-length' => '100'], 'body' => ''];
            }
            return ['status' => 404, 'headers' => [], 'body' => ''];
        });

        $disk = new S3Disk('s3', $this->config);
        $this->assertTrue($disk->exists('found.txt'));
        $this->assertFalse($disk->missing('found.txt'));

        $this->assertFalse($disk->exists('not_found.txt'));
        $this->assertTrue($disk->missing('not_found.txt'));
    }

    public function testSizeAndLastModified(): void
    {
        S3Client::setMockTransport(function (string $method, string $url) {
            return [
                'status'  => 200,
                'headers' => [
                    'content-length' => '2048',
                    'last-modified'  => 'Thu, 01 Oct 2026 12:00:00 GMT',
                ],
                'body'    => '',
            ];
        });

        $disk = new S3Disk('s3', $this->config);
        $this->assertSame(2048, $disk->size('file.bin'));
        $this->assertSame(strtotime('Thu, 01 Oct 2026 12:00:00 GMT'), $disk->lastModified('file.bin'));
    }

    public function testDeleteObject(): void
    {
        $deleted = false;
        S3Client::setMockTransport(function (string $method, string $url) use (&$deleted) {
            if ($method === 'DELETE' && str_contains($url, 'old.log')) {
                $deleted = true;
                return ['status' => 204, 'headers' => [], 'body' => ''];
            }
            return ['status' => 400, 'headers' => [], 'body' => ''];
        });

        $disk = new S3Disk('s3', $this->config);
        $this->assertTrue($disk->delete('logs/old.log'));
        $this->assertTrue($deleted);
    }

    public function testPublicUrlAndCustomCdnUrl(): void
    {
        // Default virtual-host S3 URL
        $disk = new S3Disk('s3', $this->config);
        $this->assertSame('https://mybucket.s3.us-east-1.amazonaws.com/avatar.png', $disk->url('avatar.png'));

        // Custom CDN / public URL prefix
        $cdnConfig = array_merge($this->config, ['url' => 'https://cdn.mybongoapp.com']);
        $cdnDisk = new S3Disk('s3', $cdnConfig);
        $this->assertSame('https://cdn.mybongoapp.com/avatar.png', $cdnDisk->url('avatar.png'));

        // Path-style endpoint (MinIO)
        $minioConfig = array_merge($this->config, [
            'endpoint'                => 'http://127.0.0.1:9000',
            'use_path_style_endpoint' => true,
        ]);
        $minioDisk = new S3Disk('s3', $minioConfig);
        $this->assertSame('http://127.0.0.1:9000/mybucket/avatar.png', $minioDisk->url('avatar.png'));
    }

    public function testPresignedTemporaryUrl(): void
    {
        $disk = new S3Disk('s3', $this->config);
        $url = $disk->temporaryUrl('reports/financial.xlsx', 3600);

        $this->assertContains('https://mybucket.s3.us-east-1.amazonaws.com/reports/financial.xlsx?', $url);
        $this->assertContains('X-Amz-Algorithm=AWS4-HMAC-SHA256', $url);
        $this->assertContains('X-Amz-Credential=AKIAIOSFODNN7EXAMPLE', $url);
        $this->assertContains('X-Amz-Expires=3600', $url);
        $this->assertContains('X-Amz-SignedHeaders=host', $url);
        $this->assertContains('X-Amz-Signature=', $url);
    }

    public function testListObjectsV2(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ListBucketResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/">
    <Name>mybucket</Name>
    <Prefix>photos/</Prefix>
    <Contents>
        <Key>photos/dog.jpg</Key>
        <Size>12345</Size>
    </Contents>
    <Contents>
        <Key>photos/cat.jpg</Key>
        <Size>67890</Size>
    </Contents>
</ListBucketResult>
XML;

        S3Client::setMockTransport(function (string $method, string $url) use ($xml) {
            return ['status' => 200, 'headers' => [], 'body' => $xml];
        });

        $disk = new S3Disk('s3', $this->config);
        $files = $disk->files('photos');
        $this->assertCount(2, $files);
        $this->assertTrue(in_array('photos/dog.jpg', $files, true));
        $this->assertTrue(in_array('photos/cat.jpg', $files, true));
    }

    public function testDownloadResponseWithAutoCleanup(): void
    {
        S3Client::setMockTransport(function (string $method, string $url) {
            return ['status' => 200, 'headers' => [], 'body' => 'Binary file stream contents'];
        });

        $disk = new S3Disk('s3', $this->config);
        $response = $disk->download('exports/report.csv', 'custom_report.csv');

        $this->assertTrue($response instanceof DownloadResponse);
        $this->assertSame('custom_report.csv', $response->filename);
        $this->assertTrue($response->deleteAfterSend);
        $this->assertTrue(is_file($response->path));
        $this->assertSame('Binary file stream contents', file_get_contents($response->path));

        // Clean up temp file
        @unlink($response->path);
    }
}
