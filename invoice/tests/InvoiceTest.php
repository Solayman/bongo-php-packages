<?php

declare(strict_types=1);

namespace Bongo\Packages\Invoice\Tests;

use Bongo\Packages\Invoice\Invoice;
use Bongo\Packages\Invoice\InvoiceItem;
use System\Testing\TestCase;

class InvoiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        require_once __DIR__ . '/../src/helpers.php';
    }

    public function testInvoiceInstantiationAndDefaults(): void
    {
        $inv = Invoice::make('INV-2026-001');
        $this->assertEquals('INV-2026-001', $inv->getNumber());
        $this->assertEquals(0.0, $inv->getSubtotal());
        $this->assertEquals(0.0, $inv->getTotal());
    }

    public function testItemCalculations(): void
    {
        $item = new InvoiceItem('Wireless Mouse', 25.50, 2);
        $this->assertEquals('Wireless Mouse', $item->title);
        $this->assertEquals(25.50, $item->price);
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals(51.0, $item->subtotal());

        $item2 = InvoiceItem::make('Mechanical Keyboard', 100.0, 1, 'RGB backlit');
        $this->assertEquals(100.0, $item2->subtotal());
        $this->assertEquals('RGB backlit', $item2->description);
    }

    public function testTotalsCalculationsWithTaxDiscountAndShipping(): void
    {
        $inv = Invoice::make('INV-100')
            ->addItem('Product A', 100.0, 2) // 200
            ->addItem('Product B', 50.0, 1)  // 50
            ->tax(10.0)                      // 10% of 250 = 25
            ->discount(20.0)                 // -20
            ->shipping(15.0);                // +15

        $this->assertEquals(250.0, $inv->getSubtotal());
        $this->assertEquals(25.0, $inv->getTaxAmount());
        $this->assertEquals(20.0, $inv->getDiscountAmount());
        $this->assertEquals(15.0, $inv->getShippingAmount());
        // 250 + 25 - 20 + 15 = 270
        $this->assertEquals(270.0, $inv->getTotal());
    }

    public function testRenderA4ContainsHtmlAndEscapesXss(): void
    {
        $inv = Invoice::make('INV-XSS')
            ->company([
                'name' => 'Safe Corp & Co <script>alert("hack")</script>',
                'address' => 'Dhaka',
            ])
            ->customer([
                'name' => 'John Doe <img src=x onerror=alert(1)>',
                'email' => 'john@example.com',
            ])
            ->addItem('Sanitized Item <script>', 100.0, 1)
            ->notes('Safe note with <b>bold</b>');

        $html = $inv->render();

        $this->assertTrue(strpos($html, '<!DOCTYPE html>') !== false);
        $this->assertTrue(strpos($html, '@media print') !== false);
        $this->assertTrue(strpos($html, 'INV-XSS') !== false);
        // Ensure scripts are properly escaped
        $this->assertTrue(strpos($html, '<script>alert("hack")</script>') === false);
        $this->assertTrue(strpos($html, '&lt;script&gt;alert(&quot;hack&quot;)&lt;/script&gt;') !== false);
        $this->assertTrue(strpos($html, '&lt;img src=x onerror=alert(1)&gt;') !== false);
    }

    public function testRenderPosReceipt(): void
    {
        $inv = Invoice::make('POS-0099')
            ->company(['name' => 'Super Shop', 'phone' => '01700000000'])
            ->addItem('Milk 1L', 90.0, 2)
            ->addItem('Bread', 40.0, 1);

        $posHtml = $inv->renderPos(80);

        $this->assertTrue(strpos($posHtml, 'POS-0099') !== false);
        $this->assertTrue(strpos($posHtml, 'Super Shop') !== false);
        $this->assertTrue(strpos($posHtml, 'Milk 1L x2') !== false);
        $this->assertTrue(strpos($posHtml, '220.00') !== false);
        $this->assertTrue(strpos($posHtml, '80mm') !== false);
        $this->assertTrue(strpos($posHtml, 'window.print()') !== false);
    }

    public function testGlobalHelperReturnsInvoiceInstance(): void
    {
        $inv = invoice('INV-HELPER-1');
        $this->assertTrue($inv instanceof Invoice);
        $this->assertEquals('INV-HELPER-1', $inv->getNumber());
    }
}
