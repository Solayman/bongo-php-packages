<?php

declare(strict_types=1);

namespace Bongo\Packages\QrCode\Tests;

use Bongo\Packages\QrCode\QrCode;
use Bongo\Packages\QrCode\QrMatrix;
use Bongo\Packages\QrCode\Exceptions\QrCodeException;
use System\Testing\TestCase;

class QrCodeTest extends TestCase
{
    protected array $tempFiles = [];

    public function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/src/helpers.php';
    }

    public function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    public function testMatrixGeneration(): void
    {
        $matrix = QrMatrix::create('HELLO WORLD', QrMatrix::ECC_M);
        $this->assertTrue(!empty($matrix));
        $this->assertSame(21, count($matrix)); // Version 1 is 21x21
        $this->assertSame(21, count($matrix[0]));

        // Top-left finder pattern center should be dark
        $this->assertTrue($matrix[3][3]);
    }

    public function testSvgGeneration(): void
    {
        $qr = qr_code('https://bongo-php.com')->size(200);
        $svg = $qr->svg();

        $this->assertTrue(str_contains($svg, '<svg'));
        $this->assertTrue(str_contains($svg, 'viewBox="0 0'));
        $this->assertTrue(str_contains($svg, '<path fill="#000000"'));
        $this->assertTrue(str_contains($svg, '</svg>'));
        $this->assertSame($svg, (string) $qr);
    }

    public function testPngGenerationAndDataUri(): void
    {
        $qr = qr_code('Bongo Framework')->size(150);

        if (extension_loaded('gd')) {
            $png = $qr->png();
            $this->assertTrue(str_starts_with($png, "\x89PNG\r\n\x1a\n"));

            $dataUri = $qr->dataUri('png');
            $this->assertTrue(str_starts_with($dataUri, 'data:image/png;base64,'));
        }

        $svgUri = $qr->dataUri('svg');
        $this->assertTrue(str_starts_with($svgUri, 'data:image/svg+xml;base64,'));
    }

    public function testAsciiOutput(): void
    {
        $ascii = qr_code('TEST')->ascii();
        $this->assertTrue(!empty($ascii));
        $this->assertTrue(str_contains($ascii, '██'));
    }

    public function testConveniencePresets(): void
    {
        $bkash = QrCode::bKash('01700000000', 500.0, 'REF123');
        $this->assertTrue(str_contains($bkash->svg(), '<svg'));

        $wifi = QrCode::wifi('MyWifi', 'secretPass');
        $this->assertTrue(str_contains($wifi->svg(), '<svg'));

        $email = QrCode::email('test@example.com', 'Subject');
        $this->assertTrue(str_contains($email->svg(), '<svg'));
    }

    public function testSaveToFile(): void
    {
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bongo_qr_' . uniqid() . '.svg';
        $this->tempFiles[] = $tmp;

        qr_code('https://github.com')->save($tmp);
        $this->assertTrue(file_exists($tmp));
        $this->assertTrue(filesize($tmp) > 50);
    }

    public function testExcessiveLengthThrowsException(): void
    {
        $this->assertThrows(QrCodeException::class, function () {
            // Generating 1000 characters exceeds Version 10 capacity
            QrMatrix::create(str_repeat('A', 1000), QrMatrix::ECC_H);
        });
    }
}
