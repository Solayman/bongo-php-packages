<?php

declare(strict_types=1);

namespace Bongo\Packages\Currency\Tests;

use Bongo\Packages\Currency\Currency;
use Bongo\Packages\Currency\CurrencyConverter;
use System\Testing\TestCase;

class CurrencyTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../src/helpers.php';
        Currency::clearFake();
        Currency::setRate('USD', 'BDT', 120.0);
        Currency::setRate('EUR', 'BDT', 130.0);
    }

    public function tearDown(): void
    {
        Currency::clearFake();
        parent::tearDown();
    }

    public function testDefaultCurrencyAndSymbols(): void
    {
        $this->assertEquals('BDT', Currency::getDefault());
        $this->assertEquals('৳', Currency::symbol('BDT'));
        $this->assertEquals('$', Currency::symbol('USD'));
        $this->assertEquals('€', Currency::symbol('EUR'));
        $this->assertTrue(Currency::has('BDT'));
        $this->assertTrue(Currency::has('USD'));
    }

    public function testDirectAndInverseConversions(): void
    {
        // 10 USD * 120 = 1200 BDT
        $bdt = Currency::convert(10.0, 'USD', 'BDT');
        $this->assertEquals(1200.0, $bdt);

        // 1200 BDT -> 10 USD
        $usd = Currency::convert(1200.0, 'BDT', 'USD');
        $this->assertEquals(10.0, $usd);
    }

    public function testBridgeConversionsBetweenNonBaseCurrencies(): void
    {
        // 1 USD = 120 BDT, 1 EUR = 130 BDT => 130 USD = 120 EUR
        $eur = Currency::convert(130.0, 'USD', 'EUR');
        $this->assertEquals(120.0, $eur);
    }

    public function testFormattingWithAndWithoutSymbols(): void
    {
        $formattedBdt = Currency::format(1500.50, 'BDT');
        $this->assertEquals('৳1,500.50', $formattedBdt);

        $formattedUsd = Currency::format(99.99, 'USD');
        $this->assertEquals('$99.99', $formattedUsd);

        $plain = Currency::format(99.99, 'USD', false);
        $this->assertEquals('99.99', $plain);
    }

    public function testFluentCurrencyConverter(): void
    {
        $conv = currency(15.0)->from('USD');
        $this->assertTrue($conv instanceof CurrencyConverter);
        $this->assertEquals(1800.0, $conv->to('BDT'));
        $this->assertEquals('৳1,800.00', $conv->format('BDT'));
    }

    public function testGlobalHelpers(): void
    {
        $default = currency();
        $this->assertEquals('BDT', $default);

        $money = money_format_bongo(250.0, 'USD');
        $this->assertEquals('$250.00', $money);
    }
}
