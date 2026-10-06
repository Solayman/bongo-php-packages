<?php

namespace Bongo\Packages\Toast\Tests;

use Bongo\Packages\Toast\Toast;
use System\Testing\TestCase;

class ToastTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Toast::clear();
    }

    public function testToastFlashingAndRetrieval(): void
    {
        $this->assertFalse(Toast::has());

        Toast::success('Profile updated successfully!', 'Success');
        $this->assertTrue(Toast::has());

        $all = Toast::all();
        $this->assertCount(1, $all);
        $this->assertSame('success', $all[0]['type']);
        $this->assertSame('Profile updated successfully!', $all[0]['message']);
        $this->assertSame('Success', $all[0]['title']);

        // Retrieving clears the toasts
        $this->assertFalse(Toast::has());
    }

    public function testToastTypesAndOptions(): void
    {
        Toast::error('Something went wrong', 'Error', ['duration' => 6000]);
        Toast::warning('Please be careful');
        Toast::info('Here is some news');

        $toasts = Toast::all();
        $this->assertCount(3, $toasts);

        $this->assertSame('error', $toasts[0]['type']);
        $this->assertSame(6000, $toasts[0]['duration']);
        $this->assertSame('warning', $toasts[1]['type']);
        $this->assertSame('info', $toasts[2]['type']);
    }

    public function testToastRenderContainsContainerAndEscapesXss(): void
    {
        Toast::success('<script>alert("xss")</script>', '<b>Bold</b>');

        $html = Toast::render('top-left');

        $this->assertTrue(str_contains($html, 'id="bongo-toast-container"'));
        $this->assertTrue(str_contains($html, 'top: 20px; left: 20px;'));
        $this->assertTrue(str_contains($html, 'window.bongoToast'));
        $this->assertTrue(str_contains($html, '\u003Cscript\u003Ealert(\u0022xss\u0022)\u003C\/script\u003E'));
    }

    public function testToastHelperFunction(): void
    {
        require_once dirname(__DIR__) . '/src/helpers.php';

        toast('Message via helper', 'success', 'Done');
        $this->assertTrue(Toast::has());

        $all = Toast::all();
        $this->assertCount(1, $all);
        $this->assertSame('Message via helper', $all[0]['message']);
    }
}
