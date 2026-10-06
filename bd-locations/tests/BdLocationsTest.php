<?php

namespace Bongo\Packages\BdLocations\Tests;

use Bongo\Packages\BdLocations\Location;
use System\Testing\TestCase;

class BdLocationsTest extends TestCase
{
    public function testThereAreExactlyEightDivisions(): void
    {
        $divisions = Location::divisions();
        $this->assertCount(8, $divisions);

        $dhaka = Location::division(1);
        $this->assertNotNull($dhaka);
        $this->assertSame('Dhaka', $dhaka['name']);
        $this->assertSame('ঢাকা', $dhaka['bn_name']);

        $byName = Location::division('Chattogram');
        $this->assertNotNull($byName);
        $this->assertSame(2, $byName['id']);

        $byBnName = Location::division('রাজশাহী');
        $this->assertNotNull($byBnName);
        $this->assertSame(3, $byBnName['id']);
    }

    public function testThereAreExactlySixtyFourDistricts(): void
    {
        $districts = Location::districts();
        $this->assertCount(64, $districts);

        $dhakaDistricts = Location::districts(1);
        $this->assertTrue(count($dhakaDistricts) >= 13);

        $bogura = Location::district('Bogura');
        $this->assertNotNull($bogura);
        $this->assertSame('বগুড়া', $bogura['bn_name']);
        $this->assertSame(3, $bogura['division_id']);
    }

    public function testShippingZonesAndInsideDhakaDetection(): void
    {
        $this->assertTrue(Location::isInsideDhaka('Dhaka'));
        $this->assertTrue(Location::isInsideDhaka(1));
        $this->assertSame('inside_dhaka', Location::shippingZone('Dhaka'));

        $this->assertTrue(Location::isDhakaSuburb('Gazipur'));
        $this->assertSame('dhaka_suburb', Location::shippingZone('Gazipur'));

        $this->assertFalse(Location::isInsideDhaka('Chattogram'));
        $this->assertSame('outside_dhaka', Location::shippingZone('Chattogram'));
    }

    public function testUpazilasLookup(): void
    {
        $dhakaUpazilas = Location::upazilas(1);
        $this->assertTrue(!empty($dhakaUpazilas));

        $dhanmondi = Location::upazila('Dhanmondi');
        $this->assertNotNull($dhanmondi);
        $this->assertSame('ধানমন্ডি', $dhanmondi['bn_name']);
        $this->assertSame('1205', $dhanmondi['post_code']);
    }

    public function testSearchFunctionality(): void
    {
        $results = Location::search('Sylhet');
        $this->assertTrue(!empty($results['divisions']));
        $this->assertTrue(!empty($results['districts']));

        $bnResults = Location::search('বরিশাল');
        $this->assertTrue(!empty($bnResults['divisions']));
        $this->assertTrue(!empty($bnResults['districts']));
    }

    public function testHtmlOptionsRendering(): void
    {
        $html = Location::divisionOptions(1, 'en');
        $this->assertTrue(str_contains($html, '<option value="1" selected>Dhaka</option>'));

        $bnHtml = Location::districtOptions(1, 1, 'bn');
        $this->assertTrue(str_contains($bnHtml, 'selected data-zone="inside_dhaka">ঢাকা</option>'));
    }

    public function testGlobalHelpers(): void
    {
        require_once dirname(__DIR__) . '/src/helpers.php';
        $this->assertCount(8, bd_divisions());
        $this->assertCount(64, bd_districts());
        $this->assertTrue(bd_is_inside_dhaka('Dhaka'));
    }
}
