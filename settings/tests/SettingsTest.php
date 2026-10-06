<?php

declare(strict_types=1);

namespace Bongo\Packages\Settings\Tests;

use Bongo\Packages\Settings\Commands\SettingClearCommand;
use Bongo\Packages\Settings\Commands\SettingGetCommand;
use Bongo\Packages\Settings\Commands\SettingListCommand;
use Bongo\Packages\Settings\Commands\SettingSetCommand;
use Bongo\Packages\Settings\Setting;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

require_once __DIR__ . '/../src/Helpers.php';

class SettingsTest extends TestCase
{
    public function setUp(): void
    {
        Schema::dropIfExists('settings');

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('setting_group')->default('general');
            $table->timestamps();
        });

        Setting::clearCache();
    }

    public function tearDown(): void
    {
        Setting::clearCache();
        Schema::dropIfExists('settings');
    }

    public function testSetAndGetBasicString(): void
    {
        Setting::set('site.name', 'Bongo Framework');

        $this->assertTrue(Setting::has('site.name'));
        $this->assertSame('Bongo Framework', Setting::get('site.name'));
        $this->assertSame('Bongo Framework', setting('site.name'));
    }

    public function testTypeCastingIntegerAndFloat(): void
    {
        Setting::set('shop.items_per_page', 25);
        Setting::set('shop.tax_rate', 15.75);

        $this->assertSame(25, Setting::get('shop.items_per_page'));
        $this->assertSame(15.75, Setting::get('shop.tax_rate'));
    }

    public function testTypeCastingBoolean(): void
    {
        Setting::set('app.maintenance', true);
        Setting::set('app.debug_mode', false);

        $this->assertTrue(Setting::get('app.maintenance'));
        $this->assertFalse(Setting::get('app.debug_mode'));
    }

    public function testTypeCastingJsonAndArray(): void
    {
        $features = ['auth', 'payments', 'sms', 'rbac'];
        Setting::set('app.features', $features);

        $loaded = Setting::get('app.features');
        $this->assertTrue(is_array($loaded));
        $this->assertCount(4, $loaded);
        $this->assertSame('sms', $loaded[2]);
    }

    public function testSettingDefaultValue(): void
    {
        $this->assertFalse(Setting::has('non.existent'));
        $this->assertSame('Default Value', Setting::get('non.existent', 'Default Value'));
        $this->assertSame(999, setting('non.existent', 999));
    }

    public function testBatchSetting(): void
    {
        setting([
            'brand.color' => '#ff0000',
            'brand.tagline' => 'Speed & Simplicity',
            'brand.year' => 2026,
        ]);

        $this->assertSame('#ff0000', setting('brand.color'));
        $this->assertSame('Speed & Simplicity', setting('brand.tagline'));
        $this->assertSame(2026, setting('brand.year'));
    }

    public function testHasAndForget(): void
    {
        Setting::set('temporary.token', 'abc-123');
        $this->assertTrue(Setting::has('temporary.token'));

        $deleted = Setting::forget('temporary.token');
        $this->assertTrue($deleted);
        $this->assertFalse(Setting::has('temporary.token'));
        $this->assertNull(Setting::get('temporary.token'));
    }

    public function testAllWithGroupFiltering(): void
    {
        Setting::set('site.title', 'My Store', group: 'site');
        Setting::set('site.email', 'admin@example.com', group: 'site');
        Setting::set('mail.host', 'smtp.example.com', group: 'mail');

        $all = Setting::all();
        $this->assertArrayHasKey('site.title', $all);
        $this->assertArrayHasKey('mail.host', $all);

        $siteGroup = Setting::all('site');
        $this->assertArrayHasKey('site.title', $siteGroup);
        $this->assertArrayHasKey('site.email', $siteGroup);
        $this->assertFalse(array_key_exists('mail.host', $siteGroup));
    }

    public function testCommandsExecution(): void
    {
        // 1. SettingSetCommand
        $setCmd = new SettingSetCommand();
        ob_start();
        $resSet = $setCmd->handle(['test.cli', 'hello-world'], []);
        ob_end_clean();
        $this->assertSame(0, $resSet);
        $this->assertSame('hello-world', Setting::get('test.cli'));

        // 2. SettingGetCommand
        $getCmd = new SettingGetCommand();
        ob_start();
        $resGet = $getCmd->handle(['test.cli'], []);
        $getOut = ob_get_clean();
        $this->assertSame(0, $resGet);
        $this->assertContains('hello-world', $getOut);

        // 3. SettingListCommand
        $listCmd = new SettingListCommand();
        ob_start();
        $resList = $listCmd->handle([], []);
        $listOut = ob_get_clean();
        $this->assertSame(0, $resList);
        $this->assertContains('test.cli', $listOut);

        // 4. SettingClearCommand
        $clearCmd = new SettingClearCommand();
        ob_start();
        $resClear = $clearCmd->handle([], []);
        ob_end_clean();
        $this->assertSame(0, $resClear);
    }

    public function testBatchLoadSettings(): void
    {
        Setting::set([
            'batch.key1' => 'alpha',
            'batch.key2' => 'beta',
            'batch.key3' => 'gamma',
        ]);

        // Reset memory cache to test single-query batch loading from DB
        \Bongo\Packages\Settings\SettingsManager::setInstance(null);

        $loaded = \setting()->load(['batch.key1', 'batch.key2', 'batch.key3', 'batch.nonexistent']);
        $this->assertSame('alpha', $loaded['batch.key1']);
        $this->assertSame('beta', $loaded['batch.key2']);
        $this->assertSame('gamma', $loaded['batch.key3']);
        $this->assertNull($loaded['batch.nonexistent']);
    }

    public function testTemplateDirectivesAreRegistered(): void
    {
        require_once dirname(__DIR__) . '/bootstrap.php';
        $php = (new \System\View\ViewCompiler())->compile("@setting('site_name')");
        $this->assertContains('setting(\'site_name\')', $php);
    }

    public function testSettingManyKeysUsesOneWrite(): void
    {
        Setting::set(['bulk_a' => 'x']);   // one existing key
        \System\Database\DB::enableQueryLog();
        Setting::set(['bulk_a' => 'y', 'bulk_b' => 2, 'bulk_c' => true, 'bulk_d' => ['k' => 'v']]);
        $writes = array_filter(\System\Database\DB::getQueryLog(), fn($q) => !str_starts_with(strtoupper(ltrim($q['sql'])), 'SELECT'));
        \System\Database\DB::disableQueryLog();

        $this->assertCount(1, $writes, 'four keys, one INSERT ... ON CONFLICT/DUPLICATE KEY');
        $this->assertSame('y', Setting::get('bulk_a'));
        $this->assertSame(['k' => 'v'], Setting::get('bulk_d'));
    }
}
