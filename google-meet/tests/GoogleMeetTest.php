<?php

declare(strict_types=1);

namespace Bongo\Packages\GoogleMeet\Tests;

use Bongo\Packages\GoogleMeet\GoogleMeet;
use Bongo\Packages\GoogleMeet\Commands\GoogleMeetCommand;
use System\Testing\TestCase;

class GoogleMeetTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        GoogleMeet::fake();
    }

    public function tearDown(): void
    {
        GoogleMeet::restore();
        parent::tearDown();
    }

    public function testCreateMeetingWithFakeAndExtractMeetLink(): void
    {
        $client = new GoogleMeet();
        $meeting = $client->createMeeting([
            'summary'     => 'Architecture Review',
            'duration'    => 60,
            'attendees'   => ['dev1@example.com', 'dev2@example.com'],
            'description' => 'Review microservices design',
        ]);

        $this->assertNotNull($meeting);
        $this->assertSame('Architecture Review', $meeting['summary']);
        $this->assertTrue(str_contains($meeting['hangoutLink'], 'meet.google.com/'));

        $requests = GoogleMeet::getRequests();
        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0]['method']);
        $this->assertTrue(str_contains($requests[0]['url'], 'conferenceDataVersion=1'));
        $payload = $requests[0]['payload'];
        $this->assertSame('Architecture Review', $payload['summary']);
        $this->assertSame('hangoutsMeet', $payload['conferenceData']['createRequest']['conferenceSolutionKey']['type']);
        $this->assertCount(2, $payload['attendees']);
    }

    public function testGetAndUpdateMeeting(): void
    {
        $client = new GoogleMeet();
        $meeting = $client->getMeeting('evt_12345');

        $this->assertSame('evt_12345', $meeting['id']);
        $this->assertTrue(str_contains($meeting['hangoutLink'], 'meet.google.com/'));

        $res = $client->updateMeeting('evt_12345', ['summary' => 'Updated Review']);
        $this->assertTrue($res['success']);

        $requests = GoogleMeet::getRequests();
        $this->assertCount(2, $requests);
        $this->assertSame('GET', $requests[0]['method']);
        $this->assertSame('PATCH', $requests[1]['method']);
    }

    public function testDeleteMeeting(): void
    {
        $client = new GoogleMeet();
        $del = $client->deleteMeeting('evt_12345');
        $this->assertTrue($del);

        $requests = GoogleMeet::getRequests();
        $this->assertCount(1, $requests);
        $this->assertSame('DELETE', $requests[0]['method']);
    }

    public function testListMeetings(): void
    {
        $client = new GoogleMeet();
        $list = $client->listMeetings();

        $this->assertCount(1, $list['items']);
        $this->assertSame('Design Sprint Sync', $list['items'][0]['summary']);
        $this->assertTrue(str_contains($list['items'][0]['hangoutLink'], 'meet.google.com/'));
    }

    public function testJwtAssertionGenerationWithMockKey(): void
    {
        $client = new GoogleMeet();

        $privateKey = <<<EOD
-----BEGIN PRIVATE KEY-----
MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAKfDd2M/Txg3vhph
uCKXeJtr6bbgY1VBHMmwnrwuxIcFO0uhRCfcFvw9Jrd2wO4SXNb/lattxqD7EVJc
4s/VD6RBZTsQM9W6v0EfjYgBtCKlSxQCpIDln3h8fNn2SYGTJS/THbJ+DIMNzZ/E
mIIjvlh5LZlKbJgU2kAvejW9q5AfAgMBAAECgYBc/QVh56eJvuwKZ3vPALKDQ49q
JHoquUqhqNpzaGDajlXNl/fVODMtYR/itVo/G8KGEWj+enyMol15j67QkEJ4XUjx
8dUp4zEb2KZqQGZOuq2UMbiQD3lVl2jHC4oZbVojTszN8bO06l6NXM5Qd+b6y1to
Ic+GCAV4UvlJwE9p8QJBANVXpqyKYWS6fJdpr1KkX7+fEA4WRgUxkIhrxeYXF/2E
e+gEO6TbftlGBY/5vnXtMTFT3RIl+tLiaF/VWbxBTFUCQQDJTsXFcT9IDtaIQ9lP
9iEgan9mj7YiMu1DBaFflWuMKMRuPBbjja8Z/kt2rwx52qmRk/FDZh3m9oBoTiFa
jh6jAkAym1okL6cut7X6TM2T8v3rfJXYYZw2ey1nzvjgvbWpI5Yo0Wg8kPykGaIU
Jw/w9VTn+JbGSVAYWjCcGwXwTCUJAkEAvdp8yukzNVJJIdpbaP5H37qQzLlcnIRL
H5p/xxW0iEsqdoxVSeal7W3W3efZbFK8+X79WwND0D9KexS0gCLo4QJAH3d9u2P4
lLT/IhaubfAGomapnCX6onbDVGXavpkTwCTemlelj2KRj25wXWdVoMS8W/2gcqus
bMu+0zQxqVN8mg==
-----END PRIVATE KEY-----
EOD;

        $assertion = $client->createJwtAssertion(
            'service-account@test-project.iam.gserviceaccount.com',
            $privateKey,
            'https://www.googleapis.com/auth/calendar.events'
        );

        $parts = explode('.', $assertion);
        $this->assertCount(3, $parts);

        $header = json_decode(base64_decode($parts[0]), true);
        $claims = json_decode(base64_decode($parts[1]), true);

        $this->assertSame('RS256', $header['alg']);
        $this->assertSame('service-account@test-project.iam.gserviceaccount.com', $claims['iss']);
        $this->assertSame('https://oauth2.googleapis.com/token', $claims['aud']);
    }

    public function testGlobalHelperAndCommand(): void
    {
        require_once dirname(__DIR__) . '/src/helpers.php';

        $client = google_meet();
        $this->assertTrue($client instanceof GoogleMeet);

        $cmd = new GoogleMeetCommand();
        $code = $cmd->handle(['CLI Standup'], ['duration' => '30']);
        $this->assertSame(0, $code);
    }
}
