<?php

declare(strict_types=1);

namespace Bongo\Packages\ActivityLog\Tests;

use Bongo\Packages\ActivityLog\ActivityLog;
use Bongo\Packages\ActivityLog\Commands\ActivityCleanCommand;
use Bongo\Packages\ActivityLog\Models\Activity;
use Bongo\Packages\ActivityLog\Traits\LogsActivity;
use System\Database\Model;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

require_once __DIR__ . '/../src/Helpers.php';

class TestArticle extends Model
{
    use LogsActivity;

    protected static $table = 'test_activity_articles';
    protected array $fillable = ['id', 'title', 'content', 'status', 'password'];
}

class TestUser extends Model
{
    protected static $table = 'test_activity_users';
    protected array $fillable = ['id', 'name', 'email'];
}

class ActivityLogTest extends TestCase
{
    public function setUp(): void
    {
        Schema::dropIfExists('test_activity_articles');
        Schema::dropIfExists('test_activity_users');
        Schema::dropIfExists('activity_log');

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->default('default');
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->string('causer_id')->nullable();
            $table->text('properties')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('test_activity_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('status')->default('draft');
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('test_activity_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
        $_SERVER['HTTP_USER_AGENT'] = 'BongoTestBrowser/1.0';
    }

    public function tearDown(): void
    {
        Schema::dropIfExists('test_activity_articles');
        Schema::dropIfExists('test_activity_users');
        Schema::dropIfExists('activity_log');
    }

    public function testBasicActivityLogging(): void
    {
        $log = activity()->log('User logged in');

        $this->assertTrue($log instanceof Activity);
        $this->assertSame('User logged in', $log->description);
        $this->assertSame('default', $log->log_name);
        $this->assertSame('192.168.1.50', $log->ip_address);
        $this->assertSame('BongoTestBrowser/1.0', $log->user_agent);
    }

    public function testSubjectAndCauserAssociation(): void
    {
        $user = new TestUser(['name' => 'Alice', 'email' => 'alice@example.com']);
        $user->save();

        $article = new TestArticle(['title' => 'First Post']);
        $article->save();

        $log = activity('editorial')
            ->performedOn($article)
            ->causedBy($user)
            ->log('Article published');

        $this->assertSame('editorial', $log->log_name);
        $this->assertSame(TestArticle::class, $log->subject_type);
        $this->assertSame((string) $article->id, $log->subject_id);
        $this->assertSame(TestUser::class, $log->causer_type);
        $this->assertSame((string) $user->id, $log->causer_id);
    }

    public function testPropertiesJsonSerializationAndExtraction(): void
    {
        $log = activity()
            ->withProperties(['browser' => 'Chrome', 'screen' => '1920x1080', 'count' => 5])
            ->withProperty('tags', ['php', 'mvc'])
            ->log('Dashboard viewed');

        $props = $log->getProperties();
        $this->assertTrue(is_array($props));
        $this->assertSame('Chrome', $log->getExtraProperty('browser'));
        $this->assertSame(5, $log->getExtraProperty('count'));
        $this->assertSame(['php', 'mvc'], $log->getExtraProperty('tags'));
        $this->assertSame('default_val', $log->getExtraProperty('missing_key', 'default_val'));
    }

    public function testQueryScopes(): void
    {
        $user = new TestUser(['name' => 'Bob', 'email' => 'bob@example.com']);
        $user->save();

        $article = new TestArticle(['title' => 'Scoped Post']);
        $article->save();

        activity('security')->causedBy($user)->log('Password changed');
        activity('editorial')->performedOn($article)->log('Article viewed');

        $securityLogs = Activity::query()->where('log_name', 'security')->get();
        $this->assertCount(1, $securityLogs);
        $this->assertSame('Password changed', $securityLogs[0]->description);

        $articleLogs = Activity::query()
            ->where('subject_type', TestArticle::class)
            ->where('subject_id', (string) $article->id)
            ->get();
        $this->assertCount(1, $articleLogs);
        $this->assertSame('Article viewed', $articleLogs[0]->description);
    }

    public function testLogsActivityTrait(): void
    {
        $article = new TestArticle(['title' => 'Trait Article', 'content' => 'Lorem ipsum']);
        $article->save();

        // 1. Manual logActivity from model
        $log = $article->logActivity('Article reviewed');
        $this->assertSame('Article reviewed', $log->description);
        $this->assertSame((string) $article->id, $log->subject_id);

        // 2. Automated recordActivity on update with diff tracking
        $article->title = 'Updated Title';
        $article->status = 'published';
        $recordLog = $article->recordActivity('updated');

        $this->assertContains('TestArticle updated', $recordLog->description);
        $props = $recordLog->getProperties();
        $this->assertArrayHasKey('attributes', $props);
        $this->assertSame('Updated Title', $props['attributes']['title']);
        $this->assertSame('published', $props['attributes']['status']);
        // Password should be filtered out
        $this->assertFalse(array_key_exists('password', $props['attributes']));
    }

    public function testActivityCleanCommand(): void
    {
        activity()->log('Recent log 1');
        activity()->log('Recent log 2');

        $cmd = new ActivityCleanCommand();

        ob_start();
        $status = $cmd->handle([], ['days' => 0]);
        $output = ob_get_clean();

        $this->assertSame(0, $status);
        $this->assertContains('Cleaned', $output);
    }

    public function testUntrustedProxyXForwardedForIgnored(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.195';

        $log = activity()->log('Untrusted proxy test');
        $this->assertSame('192.168.1.50', $log->ip_address);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    public function testActivitySubjectAndCauserLoadMorphsNoNPlusOne(): void
    {
        $user1 = new TestUser(['name' => 'User One', 'email' => 'one@example.com']);
        $user1->save();
        $user2 = new TestUser(['name' => 'User Two', 'email' => 'two@example.com']);
        $user2->save();

        $article1 = new TestArticle(['title' => 'Article One']);
        $article1->save();
        $article2 = new TestArticle(['title' => 'Article Two']);
        $article2->save();

        $act1 = activity()->performedOn($article1)->causedBy($user1)->log('Published article 1');
        $act2 = activity()->performedOn($article2)->causedBy($user2)->log('Published article 2');

        // Test individual subject and causer resolution
        $this->assertSame('Article One', $act1->subject()->title);
        $this->assertSame('User One', $act1->causer()->name);

        // Test batched polymorphic eager loading (0 N+1 queries)
        $activities = Activity::whereIn('id', [$act1->id, $act2->id])->get();
        Activity::loadMorphs($activities);

        $this->assertTrue($activities[0]->relationLoaded('subject'));
        $this->assertTrue($activities[0]->relationLoaded('causer'));
        $this->assertSame('Article One', $activities[0]->subject()->title);
        $this->assertSame('User One', $activities[0]->causer()->name);
        $this->assertSame('Article Two', $activities[1]->subject()->title);
        $this->assertSame('User Two', $activities[1]->causer()->name);

        // Also verify eagerLoadMorph alias works identically
        $activities2 = Activity::whereIn('id', [$act1->id, $act2->id])->get();
        Activity::eagerLoadMorph($activities2);
        $this->assertTrue($activities2[0]->relationLoaded('subject'));
        $this->assertTrue($activities2[0]->relationLoaded('causer'));
        $this->assertSame('Article One', $activities2[0]->subject()->title);
    }

    public function testCleanRemovesOnlyOldRecordsInBulk(): void
    {
        $old = date('Y-m-d H:i:s', strtotime('-90 days'));
        $new = date('Y-m-d H:i:s');
        foreach ([$old, $old, $old, $new] as $i => $at) {
            \System\Database\DB::table('activity_log')->insert(['description' => "entry $i", 'created_at' => $at, 'updated_at' => $at]);
        }

        \System\Database\DB::enableQueryLog();
        $removed = \Bongo\Packages\ActivityLog\ActivityLog::clean(30);
        $queries = count(\System\Database\DB::getQueryLog());
        \System\Database\DB::disableQueryLog();

        $this->assertSame(3, $removed);
        $this->assertSame(1, \System\Database\DB::table('activity_log')->count());
        $this->assertTrue($queries <= 2, "clean() uses at most 2 queries, used $queries");
    }
}
