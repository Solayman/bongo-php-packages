<?php

namespace Bongo\Packages\QueueMonitor\Tests;

use Bongo\Packages\QueueMonitor\QueueMonitor;
use System\Database\DB;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

class QueueMonitorTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->delete(true);
        }
    }

    public function testStatsRetrieval(): void
    {
        $stats = QueueMonitor::stats();
        $this->assertNotNull($stats);
        $this->assertSame('active', $stats['status']);
        $this->assertSame(0, $stats['failed']);
    }

    public function testFailedJobsListingAndDeletion(): void
    {
        DB::table('failed_jobs')->insert([
            'queue'     => 'emails',
            'payload'   => json_encode(['job' => 'SendWelcomeEmail', 'data' => ['email' => 'test@example.com']]),
            'exception' => 'Connection refused to smtp.example.com',
        ]);

        $stats = QueueMonitor::stats();
        $this->assertSame(1, $stats['failed']);

        $failed = QueueMonitor::failedJobs();
        $this->assertCount(1, $failed);
        $this->assertSame('emails', $failed[0]->queue);

        // Delete job
        $deleted = QueueMonitor::delete($failed[0]->id);
        $this->assertTrue($deleted);

        $statsAfter = QueueMonitor::stats();
        $this->assertSame(0, $statsAfter['failed']);
    }

    public function testRetryPutsFailedJobsBackOnTheQueue(): void
    {
        $GLOBALS['config']['queue']['driver'] = 'database';
        DB::table('jobs')->delete(true);
        foreach (['a', 'b', 'c'] as $name) {
            DB::table('failed_jobs')->insert(['queue' => 'default', 'payload' => "payload-$name", 'exception' => 'boom']);
        }
        $first = QueueMonitor::failedJobs()[0];

        $this->assertTrue(QueueMonitor::retry($first->id));
        $this->assertSame(1, DB::table('jobs')->count(), 'the job is queued again');
        $this->assertFalse(QueueMonitor::retry($first->id), 'a job is retried once');

        $this->assertSame(2, QueueMonitor::retryAll());
        $this->assertSame(3, DB::table('jobs')->count());
        $this->assertSame(0, QueueMonitor::stats()['failed']);
        $this->assertSame('database', QueueMonitor::stats()['driver']);
        DB::table('jobs')->delete(true);
    }

    public function testDashboardRoutesNeedASignedInUser(): void
    {
        $router = new \System\Routing\Router();
        $router->loadFile(dirname(__DIR__) . '/routes.php');
        foreach ($router->routes() as $route) {
            $this->assertTrue(in_array('auth', $route->middleware, true), "{$route->path} must require sign-in");
            $this->assertTrue(in_array('web', $route->middleware, true), "{$route->path} needs session + CSRF");
        }
    }

    public function testFlushClearsAllFailedJobs(): void
    {
        DB::table('failed_jobs')->insert([
            'queue'     => 'default',
            'payload'   => '{}',
            'exception' => 'Error 1',
        ]);
        DB::table('failed_jobs')->insert([
            'queue'     => 'default',
            'payload'   => '{}',
            'exception' => 'Error 2',
        ]);

        $this->assertSame(2, QueueMonitor::stats()['failed']);

        $flushed = QueueMonitor::flush();
        $this->assertSame(2, $flushed);
        $this->assertSame(0, QueueMonitor::stats()['failed']);
    }
}
