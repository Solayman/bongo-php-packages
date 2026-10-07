<?php

declare(strict_types=1);

namespace Bongo\Packages\Image\Tests;

use Bongo\Packages\Image\Exceptions\ImageException;
use Bongo\Packages\Image\Image;
use System\Testing\TestCase;

class ImageTest extends TestCase
{
    protected array $tempFiles = [];

    public function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    public function testCreateBlankCanvasAndDimensions(): void
    {
        $img = Image::create(300, 150, '#ff0000');
        $this->assertSame(300, $img->width());
        $this->assertSame(150, $img->height());
        $this->assertTrue($img->isLandscape());
        $this->assertFalse($img->isPortrait());
        $this->assertFalse($img->isSquare());
        $this->assertEquals(2.0, $img->aspectRatio());
    }

    public function testSquareAndPortraitDimensions(): void
    {
        $square = Image::create(100, 100);
        $this->assertTrue($square->isSquare());

        $portrait = Image::create(100, 250);
        $this->assertTrue($portrait->isPortrait());
        $this->assertFalse($portrait->isLandscape());
    }

    public function testColorAllocation(): void
    {
        $img = Image::create(50, 50);
        $res = $img->resource();

        $c1 = Image::allocateColor($res, '#ff0000');
        $this->assertTrue(is_int($c1));

        $c2 = Image::allocateColor($res, '#fff');
        $this->assertTrue(is_int($c2));

        $c3 = Image::allocateColor($res, '#ff000080');
        $this->assertTrue(is_int($c3));

        $c4 = Image::allocateColor($res, 'transparent');
        $this->assertTrue(is_int($c4));

        $c5 = Image::allocateColor($res, [100, 150, 200, 50]);
        $this->assertTrue(is_int($c5));

        $c6 = Image::allocateColor($res, 'blue');
        $this->assertTrue(is_int($c6));
    }

    public function testResizeExactAndPreserveAspect(): void
    {
        $img = Image::create(400, 200);

        // Exact resize
        $img->resizeExact(100, 50);
        $this->assertSame(100, $img->width());
        $this->assertSame(50, $img->height());

        // Preserve aspect ratio
        $img2 = Image::create(400, 200);
        $img2->resize(200, 200, true);
        $this->assertSame(200, $img2->width());
        $this->assertSame(100, $img2->height());
    }

    public function testResizeToWidthAndHeight(): void
    {
        $img = Image::create(400, 200);
        $img->resizeToWidth(200);
        $this->assertSame(200, $img->width());
        $this->assertSame(100, $img->height());

        $img->resizeToHeight(25);
        $this->assertSame(50, $img->width());
        $this->assertSame(25, $img->height());
    }

    public function testScale(): void
    {
        $img = Image::create(200, 100);
        $img->scale(0.5);
        $this->assertSame(100, $img->width());
        $this->assertSame(50, $img->height());

        $img->scale(2.0);
        $this->assertSame(200, $img->width());
        $this->assertSame(100, $img->height());
    }

    public function testFitAndCover(): void
    {
        // Fit within bounding box
        $img = Image::create(400, 200);
        $img->fit(150, 150);
        $this->assertSame(150, $img->width());
        $this->assertSame(75, $img->height());

        // Cover box
        $img2 = Image::create(400, 200);
        $img2->cover(120, 120);
        $this->assertSame(120, $img2->width());
        $this->assertSame(120, $img2->height());

        // Thumbnail shortcut
        $img3 = Image::create(300, 150);
        $img3->thumbnail(80);
        $this->assertSame(80, $img3->width());
        $this->assertSame(80, $img3->height());
    }

    public function testCrop(): void
    {
        $img = Image::create(200, 200);
        $img->crop(80, 60, 10, 20);
        $this->assertSame(80, $img->width());
        $this->assertSame(60, $img->height());

        $img2 = Image::create(200, 200);
        $img2->crop(50, 50); // center crop
        $this->assertSame(50, $img2->width());
        $this->assertSame(50, $img2->height());
    }

    public function testRotateAndFlip(): void
    {
        $img = Image::create(200, 100);
        $img->rotate(90);
        $this->assertSame(100, $img->width());
        $this->assertSame(200, $img->height());

        $img->flipHorizontal();
        $this->assertSame(100, $img->width());

        $img->flipVertical();
        $this->assertSame(200, $img->height());

        $img->flipBoth();
        $this->assertSame(100, $img->width());
    }

    public function testFilters(): void
    {
        $img = Image::create(100, 100, '#4488cc');
        $img->greyscale()
            ->sepia()
            ->invert()
            ->brightness(30)
            ->contrast(10)
            ->blur(1)
            ->sharpen();

        $this->assertSame(100, $img->width());
        $this->assertSame(100, $img->height());
    }

    public function testWatermarkAndOverlay(): void
    {
        $main = Image::create(300, 300, '#ffffff');
        $wm = Image::create(50, 50, '#ff0000');

        $main->watermark($wm, 'bottom-right', 80, 10, 10);
        $main->watermark($wm, 'top-left', 50, 5, 5);
        $main->watermark($wm, 'center', 90);

        $this->assertSame(300, $main->width());
        $this->assertSame(300, $main->height());
    }

    public function testTextDrawing(): void
    {
        $img = Image::create(200, 60, '#222222');
        $img->text('Bongo Image', 10, 20, ['color' => '#ffffff', 'size' => 4]);

        $this->assertSame(200, $img->width());
    }

    public function testEncodeFormats(): void
    {
        $img = Image::create(100, 50, '#00ff00');

        $webp = $img->toWebp(80);
        $this->assertTrue(strlen($webp) > 0);
        $this->assertTrue(str_starts_with($webp, 'RIFF'));
        $this->assertTrue(str_contains(substr($webp, 8, 8), 'WEBP'));

        $png = $img->toPng();
        $this->assertTrue(strlen($png) > 0);
        $this->assertTrue(str_starts_with($png, "\x89PNG\r\n\x1a\n"));

        $jpeg = $img->toJpeg(85);
        $this->assertTrue(strlen($jpeg) > 0);
        $this->assertTrue(str_starts_with($jpeg, "\xFF\xD8\xFF"));

        $uri = $img->dataUri('webp');
        $this->assertTrue(str_starts_with($uri, 'data:image/webp;base64,'));
    }

    public function testLoadSaveAndFromBinary(): void
    {
        $tmpDir = sys_get_temp_dir();
        $savePath = $tmpDir . DIRECTORY_SEPARATOR . 'bongo_test_' . uniqid() . '.webp';
        $this->tempFiles[] = $savePath;

        $canvas = Image::create(160, 90, '#336699');
        $canvas->save($savePath, 90);

        $this->assertTrue(file_exists($savePath));
        $this->assertTrue(filesize($savePath) > 0);

        // Load saved file
        $loaded = Image::load($savePath);
        $this->assertSame(160, $loaded->width());
        $this->assertSame(90, $loaded->height());

        // Load from binary
        $binary = file_get_contents($savePath);
        $fromBin = Image::fromBinary($binary);
        $this->assertSame(160, $fromBin->width());
        $this->assertSame(90, $fromBin->height());
    }

    public function testExceptionsOnInvalidOperations(): void
    {
        $this->assertThrows(ImageException::class, function () {
            Image::create(-10, 50);
        });
    }

    public function testExceptionsOnMissingFile(): void
    {
        $this->assertThrows(ImageException::class, function () {
            Image::load('/path/that/does/not/exist/999.png');
        });
    }

    public function testExceptionsOnEmptyBinary(): void
    {
        $this->assertThrows(ImageException::class, function () {
            Image::fromBinary('');
        });
    }

    public function testInterventionImageReadAndMake(): void
    {
        $canvas = Image::create(120, 80, '#ff0000');
        $png = $canvas->toPng();

        // 1. Read from binary
        $img1 = Image::read($png);
        $this->assertSame(120, $img1->width());
        $this->assertSame(80, $img1->height());

        // 2. Make from Data URI
        $dataUri = $canvas->dataUri('png');
        $img2 = Image::make($dataUri);
        $this->assertSame(120, $img2->width());
        $this->assertSame(80, $img2->height());

        // 3. Make from GdImage
        $gd = imagecreatetruecolor(50, 50);
        $img3 = Image::read($gd);
        $this->assertSame(50, $img3->width());
        $this->assertSame(50, $img3->height());

        // 4. Clone from existing Image
        $img4 = Image::read($img1);
        $this->assertSame(120, $img4->width());
    }

    public function testImageManagerAndHelperFunction(): void
    {
        require_once dirname(__DIR__) . '/src/helpers.php';

        $manager = image();
        $this->assertTrue($manager instanceof \Bongo\Packages\Image\ImageManager);

        $created = $manager->create(60, 40, '#0000ff');
        $this->assertSame(60, $created->width());

        $helperImg = image($created->toPng());
        $this->assertTrue($helperImg instanceof Image);
        $this->assertSame(60, $helperImg->width());
        $this->assertSame(40, $helperImg->height());
    }

    public function testInterventionManipulationsAndColorTools(): void
    {
        $img = Image::create(100, 100, '#ffffff');

        // Circle
        $img->circle(40, 50, 50, '#ff0000');

        // Pixelate, colorize, gamma
        $img->pixelate(5);
        $img->colorize(10, -10, 0);
        $img->gamma(1.2);

        // Fill & Pick color
        $img->fill('#00ff00', 5, 5);
        $color = $img->pickColor(5, 5, 'hex');
        $this->assertSame('#00ff00', strtolower($color));

        // Insert / Place
        $watermark = Image::create(20, 20, '#000000');
        $img->insert($watermark, 'top-left');
        $img->place($watermark, 'bottom-right', opacity: 50);

        // Orientate
        $img->orientate();

        // Response format
        $resp = $img->response('png');
        $this->assertArrayHasKey('content', $resp);
        $this->assertSame('image/png', $resp['headers']['Content-Type']);
    }

    public function testDecompressionBombIsRefusedBeforeDecoding(): void
    {
        // A few bytes of PNG header claiming 30000 x 30000 pixels (would need ~4 GB to decode)
        $ihdr = 'IHDR' . pack('NN', 30000, 30000) . "\x08\x02\x00\x00\x00";
        $png = "\x89PNG\r\n\x1a\n" . pack('N', 13) . $ihdr . pack('N', crc32($ihdr));
        $this->assertThrows(\Bongo\Packages\Image\Exceptions\ImageException::class, fn() => \Bongo\Packages\Image\Image::fromBinary($png));
    }

    public function testInternalUrlsAreNotFetched(): void
    {
        foreach (['http://127.0.0.1/a.png', 'http://169.254.169.254/latest/meta-data', 'http://10.1.2.3/x.png', 'ftp://example.com/a.png'] as $url) {
            $this->assertThrows(\Bongo\Packages\Image\Exceptions\ImageException::class, fn() => \Bongo\Packages\Image\Image::read($url), $url);
        }
    }
}
