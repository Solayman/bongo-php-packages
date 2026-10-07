<?php

declare(strict_types=1);

namespace Bongo\Packages\GeoIP\Tests;

use Bongo\Packages\GeoIP\GeoIP;
use Bongo\Packages\GeoIP\Location;
use System\Testing\TestCase;

class GeoIPTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../src/helpers.php';
        GeoIP::clearFake();
    }

    public function tearDown(): void
    {
        GeoIP::clearFake();
        parent::tearDown();
    }

    public function testLocalhostReturnsDefaultConfigLocation(): void
    {
        $loc = GeoIP::lookup('127.0.0.1');

        $this->assertEquals('127.0.0.1', $loc->ip);
        $this->assertEquals('Bangladesh', $loc->country);
        $this->assertEquals('BD', $loc->countryCode);
        $this->assertEquals('BDT', $loc->currency);
        $this->assertEquals('Asia/Dhaka', $loc->timezone);
        $this->assertTrue($loc->isLocal());
        $this->assertTrue($loc->is('BD'));
        $this->assertFalse($loc->is('US'));
    }

    public function testPrivateIpRangesDetectedAsLocal(): void
    {
        $this->assertTrue(GeoIP::isPrivateIp('10.0.0.5'));
        $this->assertTrue(GeoIP::isPrivateIp('192.168.0.100'));
        $this->assertTrue(GeoIP::isPrivateIp('172.16.5.1'));
        $this->assertFalse(GeoIP::isPrivateIp('8.8.8.8'));
        $this->assertFalse(GeoIP::isPrivateIp('103.230.104.1'));
    }

    public function testFakeLookupResolution(): void
    {
        GeoIP::fake([
            '8.8.8.8' => [
                'country'      => 'United States',
                'country_code' => 'US',
                'city'         => 'Mountain View',
                'region'       => 'California',
                'timezone'     => 'America/Los_Angeles',
                'currency'     => 'USD',
                'latitude'     => 37.386,
                'longitude'    => -122.0838,
                'is_local'     => false,
            ],
            '185.199.108.153' => [
                'country'      => 'United Kingdom',
                'country_code' => 'GB',
                'city'         => 'London',
                'currency'     => 'GBP',
                'timezone'     => 'Europe/London',
            ],
        ]);

        $usLoc = GeoIP::lookup('8.8.8.8');
        $this->assertEquals('United States', $usLoc->country);
        $this->assertEquals('US', $usLoc->countryCode);
        $this->assertEquals('USD', $usLoc->currency);
        $this->assertFalse($usLoc->isLocal());
        $this->assertTrue($usLoc->is('US'));

        $ukLoc = GeoIP::lookup('185.199.108.153');
        $this->assertEquals('GB', $ukLoc->countryCode);
        $this->assertEquals('GBP', $ukLoc->currency);
    }

    public function testLocationToArray(): void
    {
        $loc = new Location(
            ip: '1.2.3.4',
            country: 'Japan',
            countryCode: 'JP',
            city: 'Tokyo',
            region: 'Kanto',
            timezone: 'Asia/Tokyo',
            currency: 'JPY',
            latitude: 35.6762,
            longitude: 139.6503,
            isLocal: false
        );

        $arr = $loc->toArray();
        $this->assertEquals('JP', $arr['country_code']);
        $this->assertEquals('JPY', $arr['currency']);
        $this->assertEquals('Tokyo', $arr['city']);
    }

    public function testClientIpResolutionFromProxyHeaders(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '103.230.104.22';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        try {
            // A visitor cannot pick their country by sending forwarding headers
            $this->assertEquals('203.0.113.7', GeoIP::resolveClientIp());

            // Behind a trusted proxy the forwarded client address is used
            $GLOBALS['config']['security']['trusted_proxies'] = ['203.0.113.7'];
            $this->assertEquals('198.51.100.1', GeoIP::resolveClientIp());
        } finally {
            unset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $GLOBALS['config']['security']['trusted_proxies']);
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
    }

    public function testGlobalHelper(): void
    {
        $loc = geoip('127.0.0.1');
        $this->assertTrue($loc instanceof Location);
        $this->assertEquals('BD', $loc->countryCode);
    }
}
