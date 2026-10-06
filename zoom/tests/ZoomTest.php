<?php

declare(strict_types=1);

namespace Bongo\Packages\Zoom\Tests;

use Bongo\Packages\Zoom\Zoom;
use Bongo\Packages\Zoom\Commands\ZoomMeetingCommand;
use System\Testing\TestCase;

class ZoomTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Zoom::fake();
    }

    public function tearDown(): void
    {
        Zoom::restore();
        parent::tearDown();
    }

    public function testCreateMeetingWithFake(): void
    {
        $zoom = new Zoom();
        $meeting = $zoom->createMeeting([
            'topic'    => 'Sprint Planning',
            'duration' => 60,
        ]);

        $this->assertNotNull($meeting);
        $this->assertSame('Sprint Planning', $meeting['topic']);
        $this->assertSame(60, $meeting['duration']);
        $this->assertTrue(str_contains($meeting['join_url'], 'zoom.us/j/'));

        $requests = Zoom::getRequests();
        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0]['method']);
        $this->assertTrue(str_contains($requests[0]['url'], '/users/me/meetings'));
    }

    public function testGetAndListMeetings(): void
    {
        $zoom = new Zoom();
        $meeting = $zoom->getMeeting('12345678901');

        $this->assertSame('12345678901', $meeting['id']);
        $this->assertTrue(str_contains($meeting['join_url'], '12345678901'));

        $list = $zoom->listMeetings();
        $this->assertCount(1, $list['meetings']);

        $requests = Zoom::getRequests();
        $this->assertCount(2, $requests);
    }

    public function testUpdateAndDeleteMeeting(): void
    {
        $zoom = new Zoom();
        $res = $zoom->updateMeeting('12345678901', ['topic' => 'Updated Sprint Topic']);
        $this->assertTrue($res['success']);

        $del = $zoom->deleteMeeting('12345678901');
        $this->assertTrue($del);

        $requests = Zoom::getRequests();
        $this->assertCount(2, $requests);
        $this->assertSame('PATCH', $requests[0]['method']);
        $this->assertSame('DELETE', $requests[1]['method']);
    }

    public function testAddRegistrant(): void
    {
        $zoom = new Zoom();
        $reg = $zoom->addRegistrant('12345678901', [
            'email'      => 'john@example.com',
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        $this->assertSame('12345678901', $reg['id']);
        $this->assertNotNull($reg['registrant_id']);
        $this->assertTrue(str_contains($reg['join_url'], 'zoom.us/w/'));
    }

    public function testWebhookUrlValidationCrc(): void
    {
        $zoom = new Zoom();
        $secret = 'secret_test_token_123';

        $event = [
            'event'   => 'endpoint.url_validation',
            'payload' => [
                'plainToken' => 'test_plain_token_xyz',
            ],
        ];

        $res = $zoom->handleWebhookValidation($event, $secret);

        $this->assertNotNull($res);
        $this->assertSame('test_plain_token_xyz', $res['plainToken']);
        $expectedHash = hash_hmac('sha256', 'test_plain_token_xyz', $secret);
        $this->assertSame($expectedHash, $res['encryptedToken']);

        // Non-validation event returns null
        $otherEvent = ['event' => 'meeting.ended'];
        $this->assertNull($zoom->handleWebhookValidation($otherEvent, $secret));
    }

    public function testWebhookSignatureVerification(): void
    {
        $zoom = new Zoom();
        $secret = 'my_zoom_webhook_secret';
        $payload = json_encode(['event' => 'meeting.started', 'payload' => ['id' => '123']]);
        $timestamp = (string) time();

        $message = "v0:{$timestamp}:{$payload}";
        $signature = 'v0=' . hash_hmac('sha256', $message, $secret);

        $this->assertTrue($zoom->verifyWebhook($payload, $timestamp, $signature, $secret));
        $this->assertFalse($zoom->verifyWebhook($payload, $timestamp, 'v0=invalid_signature', $secret));
    }

    public function testGlobalHelperAndCommand(): void
    {
        require_once dirname(__DIR__) . '/src/helpers.php';

        $client = zoom();
        $this->assertTrue($client instanceof Zoom);

        $cmd = new ZoomMeetingCommand();
        $code = $cmd->handle(['CLI Standup'], ['duration' => '30']);
        $this->assertSame(0, $code);
    }
}
