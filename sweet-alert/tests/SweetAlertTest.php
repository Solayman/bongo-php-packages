<?php

declare(strict_types=1);

namespace Bongo\Packages\SweetAlert\Tests;

use Bongo\Packages\SweetAlert\SweetAlert;
use System\Testing\TestCase;

class SweetAlertTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        SweetAlert::clear();
    }

    public function testAlertFlashingAndRetrieval(): void
    {
        $this->assertFalse(SweetAlert::has());

        SweetAlert::success('Success Title', 'Your record has been saved.');
        $this->assertTrue(SweetAlert::has());

        $alerts = SweetAlert::all();
        $this->assertCount(1, $alerts);
        $this->assertSame('Success Title', $alerts[0]['title']);
        $this->assertSame('Your record has been saved.', $alerts[0]['text']);
        $this->assertSame('success', $alerts[0]['icon']);
        $this->assertFalse($alerts[0]['is_confirm']);

        // Clear after all()
        $this->assertFalse(SweetAlert::has());
    }

    public function testAlertTypes(): void
    {
        SweetAlert::error('Failed!', 'Something went wrong');
        SweetAlert::warning('Warning!', 'Check the details');
        SweetAlert::info('Info', 'Take note');

        $alerts = SweetAlert::all();
        $this->assertCount(3, $alerts);
        $this->assertSame('error', $alerts[0]['icon']);
        $this->assertSame('warning', $alerts[1]['icon']);
        $this->assertSame('info', $alerts[2]['icon']);
    }

    public function testConfirmAndDangerConfirm(): void
    {
        SweetAlert::confirm('Are you sure?', 'Proceed with update?', [
            'confirm_button_text' => 'Proceed',
        ]);
        SweetAlert::dangerConfirm('Delete User?', 'This cannot be undone!');

        $alerts = SweetAlert::all();
        $this->assertCount(2, $alerts);

        $this->assertTrue($alerts[0]['is_confirm']);
        $this->assertSame('Proceed', $alerts[0]['confirm_button_text']);

        $this->assertTrue($alerts[1]['is_confirm']);
        $this->assertSame('#dc2626', $alerts[1]['confirm_button_color']);
        $this->assertSame('Yes, delete it!', $alerts[1]['confirm_button_text']);
    }

    public function testRenderModalHtmlAndClientJs(): void
    {
        SweetAlert::success('Complete', 'Everything is set.');

        $html = SweetAlert::render();

        $this->assertTrue(str_contains($html, 'id="bongo-swal-backdrop"'));
        $this->assertTrue(str_contains($html, 'id="bongo-swal-modal"'));
        $this->assertTrue(str_contains($html, 'window.BongoAlert'));
        $this->assertTrue(str_contains($html, 'Complete'));
        $this->assertTrue(str_contains($html, 'Everything is set.'));
    }

    public function testHelpers(): void
    {
        require_once dirname(__DIR__) . '/src/helpers.php';

        sweet_alert('Helper Alert', 'Helper message', 'success');
        $this->assertTrue(SweetAlert::has());

        $alerts = SweetAlert::all();
        $this->assertCount(1, $alerts);
        $this->assertSame('Helper Alert', $alerts[0]['title']);

        confirm_dialog('Confirm Title', 'Confirm description');
        $this->assertTrue(SweetAlert::has());
        $alerts2 = SweetAlert::all();
        $this->assertCount(1, $alerts2);
        $this->assertTrue($alerts2[0]['is_confirm']);
    }
}
