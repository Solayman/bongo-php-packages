<?php

declare(strict_types=1);

namespace Bongo\Packages\Coupon\Tests;

use Bongo\Packages\Coupon\Coupon;
use Bongo\Packages\Coupon\CouponResult;
use System\Testing\TestCase;

class CouponTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../src/helpers.php';
        Coupon::clearFake();
    }

    public function tearDown(): void
    {
        Coupon::clearFake();
        parent::tearDown();
    }

    public function testFixedCouponDiscount(): void
    {
        $c = Coupon::fixed('FLAT50', 50.0)->minOrder(200.0);
        Coupon::register($c);

        $res = Coupon::check('FLAT50', 500.0, 30.0);
        $this->assertTrue($res->isValid());
        $this->assertEquals(50.0, $res->getDiscount());
        $this->assertEquals(0.0, $res->getShippingDiscount());
        // 500 - 50 + 30 = 480
        $this->assertEquals(480.0, $res->getNewTotal());
    }

    public function testPercentageCouponWithMaxDiscountCap(): void
    {
        // 20% off on 1000 = 200, but capped at 150
        $c = Coupon::percentage('SAVE20', 20.0)->maxDiscount(150.0);
        Coupon::register($c);

        $res = Coupon::check('SAVE20', 1000.0);
        $this->assertTrue($res->isValid());
        $this->assertEquals(150.0, $res->getDiscount());
        $this->assertEquals(850.0, $res->getNewTotal());
    }

    public function testFreeShippingCoupon(): void
    {
        $c = Coupon::freeShipping('FREEDELIVERY')->minOrder(500.0);
        Coupon::register($c);

        $res = Coupon::check('FREEDELIVERY', 600.0, 70.0);
        $this->assertTrue($res->isValid());
        $this->assertEquals(0.0, $res->getDiscount());
        $this->assertEquals(70.0, $res->getShippingDiscount());
        $this->assertEquals(600.0, $res->getNewTotal());
    }

    public function testMinOrderAmountFailsWhenThresholdNotMet(): void
    {
        $c = Coupon::fixed('BIGORDER', 100.0)->minOrder(1000.0);
        Coupon::register($c);

        $res = Coupon::check('BIGORDER', 500.0);
        $this->assertFalse($res->isValid());
        $this->assertEquals(0.0, $res->getDiscount());
        $this->assertTrue(strpos($res->getMessage(), 'Minimum cart order amount') !== false);
    }

    public function testExpiredAndInactiveCoupons(): void
    {
        $expired = Coupon::fixed('EXPIRED', 10.0)->expiresAt('2020-01-01');
        Coupon::register($expired);

        $res = Coupon::check('EXPIRED', 500.0);
        $this->assertFalse($res->isValid());
        $this->assertTrue(strpos($res->getMessage(), 'expired') !== false);

        $inactive = Coupon::fixed('DISABLED', 10.0)->active(false);
        Coupon::register($inactive);

        $res2 = Coupon::check('DISABLED', 500.0);
        $this->assertFalse($res2->isValid());
        $this->assertTrue(strpos($res2->getMessage(), 'inactive') !== false);
    }

    public function testUsageLimitEnforcement(): void
    {
        $limited = Coupon::fixed('LIMITED', 25.0)->usageLimit(5)->usageCount(5);
        Coupon::register($limited);

        $res = Coupon::check('LIMITED', 100.0);
        $this->assertFalse($res->isValid());
        $this->assertTrue(strpos($res->getMessage(), 'maximum usage limit') !== false);
    }

    public function testGlobalHelper(): void
    {
        $c = Coupon::fixed('HELPER10', 10.0);
        Coupon::register($c);

        $fetched = coupon('HELPER10');
        $this->assertTrue($fetched instanceof Coupon);

        $result = coupon('HELPER10', 100.0, 15.0);
        $this->assertTrue($result instanceof CouponResult);
        $this->assertTrue($result->isValid());
        $this->assertEquals(105.0, $result->getNewTotal());
    }
}
