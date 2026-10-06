<?php

declare(strict_types=1);

namespace Bongo\Packages\Newsletter\Tests;

use Bongo\Packages\Newsletter\Models\Subscriber;
use Bongo\Packages\Newsletter\Newsletter;
use InvalidArgumentException;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Testing\TestCase;

require_once dirname(__DIR__) . '/src/helpers.php';

class NewsletterTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Newsletter::stopFaking();

        Schema::dropIfExists('newsletter_subscribers');

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('name', 100)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('token', 64)->nullable();
            $table->string('unsubscribe_token', 64)->nullable();
            $table->string('source', 50)->nullable()->default('website');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function tearDown(): void
    {
        Newsletter::stopFaking();
        Schema::dropIfExists('newsletter_subscribers');
        parent::tearDown();
    }

    public function testDoubleOptInSubscriptionFlow(): void
    {
        $newsletter = new Newsletter();

        $subscriber = $newsletter->subscribe('user@example.com', 'Jane Doe');
        $this->assertEquals('user@example.com', $subscriber->email);
        $this->assertEquals('Jane Doe', $subscriber->name);
        $this->assertEquals('pending', $subscriber->status);
        $this->assertTrue(!empty($subscriber->token));
        $this->assertTrue(!empty($subscriber->unsubscribe_token));
        $this->assertFalse($newsletter->isSubscribed('user@example.com'));

        // Confirm subscription
        $confirmed = $newsletter->confirm($subscriber->token);
        $this->assertNotNull($confirmed);
        $this->assertEquals('subscribed', $confirmed->status);
        $this->assertTrue(!empty($confirmed->verified_at));
        $this->assertNull($confirmed->token);
        $this->assertTrue($newsletter->isSubscribed('user@example.com'));
    }

    public function testUnsubscribeViaTokenAndEmail(): void
    {
        $newsletter = new Newsletter();
        $subscriber = $newsletter->subscribe('john@example.com', 'John Smith');
        $newsletter->confirm($subscriber->token);
        $this->assertTrue($newsletter->isSubscribed('john@example.com'));

        // Refresh subscriber to get unsubscribe token
        $subscriber = Subscriber::query()->where('email', 'john@example.com')->first();
        $this->assertNotNull($subscriber);

        // Unsubscribe with token
        $success = $newsletter->unsubscribe($subscriber->unsubscribe_token);
        $this->assertTrue($success);
        $this->assertFalse($newsletter->isSubscribed('john@example.com'));

        // Unsubscribe second user via email
        $sub2 = $newsletter->subscribe('jane@example.com');
        $newsletter->confirm($sub2->token);
        $this->assertTrue($newsletter->isSubscribed('jane@example.com'));

        $success2 = $newsletter->unsubscribe('jane@example.com');
        $this->assertTrue($success2);
        $this->assertFalse($newsletter->isSubscribed('jane@example.com'));
    }

    public function testInvalidEmailThrowsException(): void
    {
        $newsletter = new Newsletter();

        $this->assertThrows(InvalidArgumentException::class, function () use ($newsletter) {
            $newsletter->subscribe('not-a-valid-email');
        });
    }

    public function testFakeMode(): void
    {
        Newsletter::fake();
        $this->assertTrue(Newsletter::isFake());

        $newsletter = new Newsletter();
        $sub = $newsletter->subscribe('fake@example.com', 'Fake User');
        $this->assertEquals('pending', $sub->status);
        $this->assertEquals(0, $newsletter->count('subscribed'));
        $this->assertEquals(1, $newsletter->count('pending'));

        $confirmed = $newsletter->confirm($sub->token);
        $this->assertNotNull($confirmed);
        $this->assertEquals('subscribed', $confirmed->status);
        $this->assertEquals(1, $newsletter->count('subscribed'));
        $this->assertTrue($newsletter->isSubscribed('fake@example.com'));

        $unsub = $newsletter->unsubscribe('fake@example.com');
        $this->assertTrue($unsub);
        $this->assertFalse($newsletter->isSubscribed('fake@example.com'));

        Newsletter::stopFaking();
        $this->assertFalse(Newsletter::isFake());
    }

    public function testExportCsvWithFormulaSanitization(): void
    {
        $newsletter = new Newsletter();
        $s1 = $newsletter->subscribe('alice@example.com', 'Alice');
        $newsletter->confirm($s1->token);

        // Test DDE formula injection sanitization in name
        $s2 = $newsletter->subscribe('bob@example.com', '=CMD("calc")');
        $newsletter->confirm($s2->token);

        $csv = $newsletter->exportCsv('subscribed');

        // Check BOM
        $this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"));
        $this->assertContains('ID,Email,Name,Status,Source', $csv);
        $this->assertContains('alice@example.com', $csv);
        // Formula prefixed with single quote
        $this->assertContains("'=CMD", $csv);
    }

    public function testXssSanitizationAndHelpers(): void
    {
        $dirtyName = '<script>alert(1)</script>John Doe';
        $sub = newsletter()->subscribe('safe@example.com', $dirtyName);

        $this->assertEquals('John Doe', $sub->name);
        $this->assertNotContains('<script>', $sub->name);

        $vUrl = newsletter()->verificationUrl($sub);
        $this->assertContains('/newsletter/verify/', $vUrl);

        $uUrl = newsletter()->unsubscribeUrl($sub);
        $this->assertContains('/newsletter/unsubscribe/', $uUrl);
    }

    public function testStatusMemoizationAndFlushCache(): void
    {
        $newsletter = new Newsletter();
        $this->assertFalse($newsletter->isSubscribed('memo@example.com'));

        $sub = $newsletter->subscribe('memo@example.com', 'Memo User');
        $newsletter->confirm($sub->token);
        $this->assertTrue($newsletter->isSubscribed('memo@example.com'));

        Newsletter::flushCache();
        $this->assertTrue($newsletter->isSubscribed('memo@example.com'));

        $newsletter->unsubscribe('memo@example.com');
        $this->assertFalse($newsletter->isSubscribed('memo@example.com'));
    }
}
