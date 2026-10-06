<?php

declare(strict_types=1);

namespace Bongo\Packages\Courier\Tests;

use Bongo\Packages\Courier\Courier;
use Bongo\Packages\Courier\Commands\CourierTrackCommand;
use InvalidArgumentException;
use System\Testing\TestCase;

class CourierTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Courier::fake();
    }

    public function tearDown(): void
    {
        Courier::restore();
        parent::tearDown();
    }

    public function testSteadfastCreateOrderWithFake(): void
    {
        $res = Courier::driver('steadfast')->createOrder([
            'invoice'           => 'INV-5501',
            'recipient_name'    => 'Sadik Hasan',
            'recipient_phone'   => '01711223344',
            'recipient_address' => 'Mirpur-10, Dhaka',
            'cod_amount'        => 2200.0,
            'note'              => 'Deliver in afternoon',
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertSame('INV-5501', $res['consignment']['invoice']);
        $this->assertNotNull($res['consignment']['tracking_code']);
        $this->assertSame(2200.0, $res['consignment']['cod_amount']);

        $requests = Courier::getRequests();
        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0]['method']);
        $this->assertTrue(str_contains($requests[0]['url'], '/create_order'));
    }

    public function testSteadfastStatusAndBalance(): void
    {
        $status = Courier::driver('steadfast')->checkStatus('SF12345678');
        $this->assertSame('delivered', $status['delivery_status']);

        $balance = Courier::driver('steadfast')->checkBalance();
        $this->assertSame(12500.50, $balance['current_balance']);

        $requests = Courier::getRequests();
        $this->assertCount(2, $requests);
        $this->assertTrue(str_contains($requests[0]['url'], '/status_by_trackingcode/SF12345678'));
        $this->assertTrue(str_contains($requests[1]['url'], '/get_balance'));
    }

    public function testPathaoDriverCreateAndStatus(): void
    {
        $res = Courier::driver('pathao')->createOrder([
            'invoice'           => 'INV-8802',
            'recipient_name'    => 'Anika Tabassum',
            'recipient_phone'   => '01899887766',
            'recipient_address' => 'Gulshan-2, Dhaka',
            'cod_amount'        => 1200.0,
        ]);

        $this->assertSame('success', $res['type']);
        $this->assertNotNull($res['data']['consignment_id']);

        $status = Courier::driver('pathao')->checkStatus('PTH-12345');
        $this->assertSame('Delivered', $status['data']['order_status']);
    }

    public function testMissingPhoneThrowsException(): void
    {
        $caught = false;
        try {
            Courier::driver('steadfast')->createOrder([
                'recipient_address' => 'Dhaka',
                'recipient_phone'   => '',
            ]);
        } catch (InvalidArgumentException $e) {
            $caught = true;
        }

        $this->assertTrue($caught);
    }

    public function testWebhookNormalizer(): void
    {
        $steadfastWebhook = Courier::driver('steadfast')->handleWebhook([
            'consignment_id' => 998822,
            'invoice'        => 'INV-101',
            'status'         => 'DELIVERED',
        ]);

        $this->assertSame('steadfast', $steadfastWebhook['driver']);
        $this->assertSame('delivered', $steadfastWebhook['status']);
        $this->assertSame(998822, $steadfastWebhook['consignment_id']);
    }

    public function testGlobalHelperAndCommand(): void
    {
        require_once dirname(__DIR__) . '/src/helpers.php';

        $c = courier();
        $this->assertTrue($c instanceof Courier);

        $cmd = new CourierTrackCommand();
        $code = $cmd->handle(['SF998877'], ['driver' => 'steadfast']);
        $this->assertSame(0, $code);
    }
}
