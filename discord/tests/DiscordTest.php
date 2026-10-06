<?php

namespace Bongo\Packages\Discord\Tests;

use Bongo\Packages\Discord\Discord;
use Bongo\Packages\Discord\DiscordEmbed;
use System\Testing\TestCase;

class DiscordTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Discord::fake();
    }

    public function testSendMessageWithFake(): void
    {
        $res = Discord::send('Hello from Bongo!', 'https://discord.com/api/webhooks/123/abc');
        $this->assertTrue($res['success']);
        $this->assertSame(204, $res['status_code']);

        $sent = Discord::getSent();
        $this->assertCount(1, $sent);
        $this->assertSame('Hello from Bongo!', $sent[0]['payload']['content']);
        $this->assertSame('https://discord.com/api/webhooks/123/abc', $sent[0]['url']);
    }

    public function testAlertEmbedFormatting(): void
    {
        $res = Discord::alert('Deployment Succeeded', 'Version 1.0.0 deployed.', 'success', [
            'Environment' => 'Production',
            'Commit'      => 'abc1234',
        ], 'https://discord.com/api/webhooks/123/abc');

        $this->assertTrue($res['success']);

        $sent = Discord::getSent();
        $this->assertCount(1, $sent);
        $embeds = $sent[0]['payload']['embeds'];
        $this->assertCount(1, $embeds);
        $this->assertTrue(str_contains($embeds[0]['title'], 'Deployment Succeeded'));
        $this->assertSame(0x22c55e, $embeds[0]['color']);
        $this->assertCount(2, $embeds[0]['fields']);
        $this->assertSame('Environment', $embeds[0]['fields'][0]['name']);
        $this->assertSame('Production', $embeds[0]['fields'][0]['value']);
    }

    public function testDiscordEmbedBuilder(): void
    {
        $embed = DiscordEmbed::make('Custom Title', 'Custom Desc', 0xef4444)
            ->url('https://example.com')
            ->addField('Field 1', 'Value 1')
            ->footer('Footer Note')
            ->toArray();

        $this->assertSame('Custom Title', $embed['title']);
        $this->assertSame('Custom Desc', $embed['description']);
        $this->assertSame(0xef4444, $embed['color']);
        $this->assertSame('https://example.com', $embed['url']);
        $this->assertSame('Footer Note', $embed['footer']['text']);
    }

    public function testGlobalHelpers(): void
    {
        require_once dirname(__DIR__) . '/src/helpers.php';

        $res1 = discord_notify('Global ping', 'https://discord.com/api/webhooks/123/abc');
        $this->assertTrue($res1['success']);

        $res2 = discord_alert('Alert', 'Details', 'warning', [], 'https://discord.com/api/webhooks/123/abc');
        $this->assertTrue($res2['success']);

        $this->assertCount(2, Discord::getSent());
    }
}
