<?php

declare(strict_types=1);

namespace Bongo\Packages\FeatureFlags\Tests;

use Bongo\Packages\FeatureFlags\Feature;
use System\Database\Model;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

require_once dirname(__DIR__) . '/src/helpers.php';

class TestFeatureUser extends Model
{
    protected static $table = 'test_feature_users';
    protected array $fillable = ['id', 'name', 'email', 'tier'];
}

class FeatureFlagsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Feature::stopFaking();

        Schema::dropIfExists('features');
        Schema::dropIfExists('test_feature_users');

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('scope', 100)->default('__global__');
            $table->text('value');
            $table->timestamps();
        });

        Schema::create('test_feature_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('tier')->default('free');
            $table->timestamps();
        });
    }

    public function testDefineAndCheckActive(): void
    {
        Feature::define('premium-tools', function ($user) {
            return $user instanceof TestFeatureUser && $user->tier === 'vip';
        });

        $vip = TestFeatureUser::create(['name' => 'Sara', 'email' => 'sara@vip.com', 'tier' => 'vip']);
        $free = TestFeatureUser::create(['name' => 'John', 'email' => 'john@free.com', 'tier' => 'free']);

        $this->assertTrue(Feature::active('premium-tools', $vip));
        $this->assertFalse(Feature::active('premium-tools', $free));
        $this->assertTrue(Feature::inactive('premium-tools', $free));
    }

    public function testDatabaseActivationAndDeactivation(): void
    {
        $this->assertFalse(Feature::active('dark-mode'));

        Feature::activate('dark-mode');
        $this->assertTrue(Feature::active('dark-mode'));

        Feature::deactivate('dark-mode');
        $this->assertFalse(Feature::active('dark-mode'));

        Feature::forget('dark-mode');
        $this->assertFalse(Feature::active('dark-mode'));
    }

    public function testScopeSpecificOverrides(): void
    {
        // Globally inactive
        Feature::deactivate('beta-feature');

        $user1 = TestFeatureUser::create(['name' => 'Alice', 'email' => 'alice@test.com']);
        $user2 = TestFeatureUser::create(['name' => 'Bob', 'email' => 'bob@test.com']);

        // Explicitly activate for User 1
        Feature::activate('beta-feature', $user1);

        $this->assertTrue(Feature::active('beta-feature', $user1));
        $this->assertFalse(Feature::active('beta-feature', $user2));
        $this->assertFalse(Feature::active('beta-feature')); // Global remains false
    }

    public function testPercentageRolloutDeterminism(): void
    {
        Feature::rollout('canary-ui', 30);

        // Deterministic: multiple checks for same user must yield identical result
        $val1 = Feature::active('canary-ui', 'user:42');
        $val2 = Feature::active('canary-ui', 'user:42');
        $this->assertSame($val1, $val2);

        // 0% rollout must be completely false
        Feature::rollout('always-off', 0);
        $this->assertFalse(Feature::active('always-off', 'user:1'));
        $this->assertFalse(Feature::active('always-off', 'user:99'));

        // 100% rollout must be completely true
        Feature::rollout('always-on', 100);
        $this->assertTrue(Feature::active('always-on', 'user:1'));
        $this->assertTrue(Feature::active('always-on', 'user:99'));
    }

    public function testFeatureWhenHelper(): void
    {
        Feature::activate('new-pricing');

        $result = Feature::when('new-pricing',
            active: fn() => 'discount_applied',
            inactive: fn() => 'regular_price'
        );
        $this->assertSame('discount_applied', $result);

        Feature::deactivate('new-pricing');

        $resultInactive = Feature::when('new-pricing',
            active: fn() => 'discount_applied',
            inactive: fn() => 'regular_price'
        );
        $this->assertSame('regular_price', $resultInactive);
    }

    public function testFakeMode(): void
    {
        Feature::fake([
            'experimental-ai' => true,
            'legacy-checkout' => false,
        ]);

        $this->assertTrue(Feature::active('experimental-ai'));
        $this->assertFalse(Feature::active('legacy-checkout'));
        $this->assertFalse(Feature::active('non-existent'));

        Feature::stopFaking();
        $this->assertFalse(Feature::active('experimental-ai'));
    }

    public function testAllFeaturesList(): void
    {
        Feature::define('defined-feature', true);
        Feature::activate('db-feature');

        $all = Feature::all();
        $this->assertArrayHasKey('defined-feature', $all);
        $this->assertArrayHasKey('db-feature', $all);
        $this->assertTrue($all['defined-feature']);
        $this->assertTrue($all['db-feature']);
    }

    public function testGlobalHelpers(): void
    {
        Feature::activate('fast-cdn');

        $this->assertTrue(feature('fast-cdn'));
        $this->assertFalse(feature('slow-cdn'));

        $val = feature_when('fast-cdn', fn() => 'fast', fn() => 'slow');
        $this->assertSame('fast', $val);
    }

    public function testRuntimeCacheAndFlush(): void
    {
        Feature::activate('cache-flag');
        $this->assertTrue(Feature::active('cache-flag'));

        // Changing via deactivation flushes cache
        Feature::deactivate('cache-flag');
        $this->assertFalse(Feature::active('cache-flag'));

        // Direct flushCache() works
        Feature::flushCache();
        $this->assertFalse(Feature::active('cache-flag'));
    }
}
