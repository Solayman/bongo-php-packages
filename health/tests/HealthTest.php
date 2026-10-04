<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Tests;

use Bongo\Packages\Health\Checks\CacheCheck;
use Bongo\Packages\Health\Checks\CheckResult;
use Bongo\Packages\Health\Checks\DatabaseCheck;
use Bongo\Packages\Health\Checks\DiskSpaceCheck;
use Bongo\Packages\Health\Checks\StorageCheck;
use Bongo\Packages\Health\Commands\HealthCheckCommand;
use Bongo\Packages\Health\Controllers\HealthController;
use Bongo\Packages\Health\Health;
use Bongo\Packages\Health\HealthManager;
use System\Http\Request;
use System\Testing\TestCase;

class HealthTest extends TestCase
{
    public function setUp(): void
    {
        HealthManager::setInstance(null);
    }

    public function tearDown(): void
    {
        HealthManager::setInstance(null);
    }

    public function testDefaultChecksRun(): void
    {
        $report = Health::run();

        $this->assertTrue(in_array($report['status'], ['healthy', 'warning'], true));
        $this->assertTrue($report['duration_ms'] >= 0.0);
        $this->assertArrayHasKey('database', $report['checks']);
        $this->assertArrayHasKey('storage', $report['checks']);
        $this->assertArrayHasKey('disk_space', $report['checks']);
        $this->assertArrayHasKey('cache', $report['checks']);
        $this->assertArrayHasKey('redis', $report['checks']);
    }

    public function testDatabaseCheck(): void
    {
        $check = new DatabaseCheck();
        $res = $check->run();

        $this->assertSame('database', $res->name);
        $this->assertTrue($res->isOk());
        $this->assertTrue($res->latencyMs >= 0.0);
    }

    public function testStorageCheck(): void
    {
        $check = new StorageCheck();
        $res = $check->run();

        $this->assertSame('storage', $res->name);
        $this->assertTrue($res->isOk());
    }

    public function testDiskSpaceCheck(): void
    {
        $check = new DiskSpaceCheck();
        $res = $check->run();

        $this->assertSame('disk_space', $res->name);
        $this->assertTrue(in_array($res->status, [CheckResult::STATUS_OK, CheckResult::STATUS_WARNING], true));
    }

    public function testCacheCheck(): void
    {
        $check = new CacheCheck();
        $res = $check->run();

        $this->assertSame('cache', $res->name);
        $this->assertTrue($res->isOk());
    }

    public function testCustomCheckRegistration(): void
    {
        Health::manager()->clearChecks();

        Health::check(fn() => true, 'custom_bool');
        Health::check(fn() => CheckResult::ok('custom_obj', 'Object check OK'), 'custom_obj');

        $report = Health::run();
        $this->assertSame('healthy', $report['status']);
        $this->assertArrayHasKey('custom_bool', $report['checks']);
        $this->assertArrayHasKey('custom_obj', $report['checks']);
    }

    public function testUnhealthyStatusPropagation(): void
    {
        Health::manager()->clearChecks();

        Health::check(fn() => false, 'failing_service');

        $report = Health::run();
        $this->assertSame('unhealthy', $report['status']);
        $this->assertSame(CheckResult::STATUS_FAILED, $report['checks']['failing_service']['status']);
    }

    public function testHealthController(): void
    {
        $controller = new HealthController();
        $req = new Request();

        $res = $controller->check($req);
        $this->assertTrue(is_array($res));
        $this->assertArrayHasKey('status', $res);
        $this->assertArrayHasKey('timestamp', $res);
    }

    public function testHealthCheckCommand(): void
    {
        $cmd = new HealthCheckCommand();

        // 1. Text table output
        ob_start();
        $exitCode = $cmd->handle([], []);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertContains('Application Health:', $output);
        $this->assertContains('database', $output);

        // 2. JSON output
        ob_start();
        $jsonCode = $cmd->handle([], ['json' => true]);
        $jsonOut = ob_get_clean();

        $this->assertSame(0, $jsonCode);
        $this->assertContains('"status":', $jsonOut);
    }
}
