<?php

declare(strict_types=1);

namespace Bongo\Packages\DeviceDetector\Tests;

use Bongo\Packages\DeviceDetector\Device;
use System\Testing\TestCase;

class DeviceDetectorTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__) . '/src/helpers.php';
    }

    public function testDetectDesktopWindowsChrome(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $d = device($ua);

        $this->assertTrue($d->isDesktop());
        $this->assertFalse($d->isMobile());
        $this->assertFalse($d->isTablet());
        $this->assertFalse($d->isBot());

        $this->assertTrue($d->isWindows());
        $this->assertSame('Windows', $d->os());
        $this->assertSame('Chrome', $d->browser());
        $this->assertSame('120.0.0.0', $d->browserVersion());
    }

    public function testDetectIPhoneSafari(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1';
        $d = device($ua);

        $this->assertTrue($d->isMobile());
        $this->assertTrue($d->isIos());
        $this->assertFalse($d->isDesktop());
        $this->assertSame('iOS', $d->os());
        $this->assertSame('17.1.1', $d->osVersion());
        $this->assertSame('Safari', $d->browser());
        $this->assertSame('17.1', $d->browserVersion());
    }

    public function testDetectIPadTablet(): void
    {
        $ua = 'Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1';
        $d = device($ua);

        $this->assertTrue($d->isTablet());
        $this->assertFalse($d->isDesktop());
        $this->assertSame('tablet', $d->deviceType());
    }

    public function testDetectAndroidPhone(): void
    {
        $ua = 'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36';
        $d = device($ua);

        $this->assertTrue($d->isMobile());
        $this->assertTrue($d->isAndroid());
        $this->assertSame('Android', $d->os());
        $this->assertSame('14', $d->osVersion());
        $this->assertSame('Chrome', $d->browser());
    }

    public function testDetectGooglebotAndAiCrawlers(): void
    {
        $googlebot = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        $d1 = device($googlebot);
        $this->assertTrue($d1->isBot());
        $this->assertSame('Googlebot', $d1->botName());

        $gptBot = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.0; +https://openai.com/gptbot)';
        $d2 = device($gptBot);
        $this->assertTrue($d2->isBot());
        $this->assertSame('GPTBot', $d2->botName());
    }

    public function testDetectEdgeAndFirefox(): void
    {
        $edgeUa = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.2210.91';
        $dEdge = device($edgeUa);
        $this->assertTrue($dEdge->isEdge());
        $this->assertSame('Edge', $dEdge->browser());

        $ffUa = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0';
        $dFf = device($ffUa);
        $this->assertTrue($dFf->isFirefox());
        $this->assertTrue($dFf->isMac());
    }

    public function testEmptyOrUnknownUserAgent(): void
    {
        $d = device('');
        $this->assertSame('desktop', $d->deviceType());
        $this->assertFalse($d->isBot());
        $this->assertSame('Unknown', $d->browser());

        $arr = $d->toArray();
        $this->assertArrayHasKey('device', $arr);
        $this->assertArrayHasKey('browser', $arr);
    }
}
