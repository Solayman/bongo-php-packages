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
