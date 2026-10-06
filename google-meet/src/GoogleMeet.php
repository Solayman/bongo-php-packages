<?php

declare(strict_types=1);

namespace Bongo\Packages\GoogleMeet;

use RuntimeException;
use InvalidArgumentException;

class GoogleMeet
{
    protected const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    protected const CALENDAR_BASE_URI = 'https://www.googleapis.com/calendar/v3/calendars';
    protected const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    protected static bool $faking = false;
    protected static array $fakeResponses = [];
    protected static array $requests = [];

    protected ?string $cachedToken = null;
    protected int $tokenExpiresAt = 0;

    /**
     * Enable fake mode for tests.
     */
    public static function fake(array $responses = []): void
    {
        static::$faking = true;
        static::$fakeResponses = $responses;
        static::$requests = [];
    }

    /**
     * Disable fake mode.
     */
    public static function restore(): void
    {
        static::$faking = false;
        static::$fakeResponses = [];
        static::$requests = [];
    }

    /**
     * Get recorded HTTP requests from fake mode.
     */
    public static function getRequests(): array
    {
        return static::$requests;
    }

    /**
     * Obtain access token using Google Service Account RSA-SHA256 JWT assertion.
     */
    public function getAccessToken(): string
    {
        if (static::$faking) {
            return 'fake_google_meet_access_token_' . time();
        }

        if ($this->cachedToken !== null && time() < ($this->tokenExpiresAt - 60)) {
            return $this->cachedToken;
        }

        $email = config('google-meet.client_email', '');
        $privateKey = config('google-meet.private_key', '');
        $keyFile = config('google-meet.key_file', '');

        if (!empty($keyFile) && is_file($keyFile)) {
            $keyData = json_decode(file_get_contents($keyFile), true);
            $email = $keyData['client_email'] ?? $email;
            $privateKey = $keyData['private_key'] ?? $privateKey;
        }

        if (empty($email) || empty($privateKey)) {
            throw new InvalidArgumentException('Google Service Account email or private key is not configured.');
        }

        $assertion = $this->createJwtAssertion($email, $privateKey, self::SCOPE);

        $response = $this->sendHttpRequest('POST', self::TOKEN_URI, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $assertion,
        ], [
            'Content-Type: application/x-www-form-urlencoded',
        ], true);

        if (empty($response['access_token'])) {
            throw new RuntimeException('Failed to obtain Google access token: ' . json_encode($response));
        }

        $this->cachedToken = $response['access_token'];
        $this->tokenExpiresAt = time() + (int) ($response['expires_in'] ?? 3600);

        return $this->cachedToken;
    }

    /**
     * Create an RS256 JWT assertion for Google OAuth2 token exchange.
     */
    public function createJwtAssertion(string $clientEmail, string $privateKey, string $scope): string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss'   => $clientEmail,
            'scope' => $scope,
            'aud'   => self::TOKEN_URI,
            'exp'   => $now + 3600,
            'iat'   => $now,
        ];

        $encodedHeader = $this->base64UrlEncode((string) json_encode($header));
        $encodedClaims = $this->base64UrlEncode((string) json_encode($claims));
        $dataToSign = $encodedHeader . '.' . $encodedClaims;

        $binarySignature = '';
        $keyResource = openssl_pkey_get_private($privateKey);
        if ($keyResource === false) {
            throw new RuntimeException('Invalid RSA private key provided for Google Service Account.');
        }

        $success = openssl_sign($dataToSign, $binarySignature, $keyResource, OPENSSL_ALGO_SHA256);
        if (!$success) {
            throw new RuntimeException('Failed to sign Google JWT assertion.');
        }

        return $dataToSign . '.' . $this->base64UrlEncode($binarySignature);
    }

    /**
     * Create a Google Calendar event with an attached Google Meet video conference link.
     */
    public function createMeeting(array $details, ?string $calendarId = null): array
    {
        $calendar = $calendarId ?? config('google-meet.calendar_id', 'primary');
        $endpoint = self::CALENDAR_BASE_URI . '/' . urlencode($calendar) . '/events?conferenceDataVersion=1';

        $durationMinutes = (int) ($details['duration'] ?? config('google-meet.default_duration', 45));
        $timeZone = $details['timezone'] ?? config('google-meet.default_timezone', 'UTC');

        $startIso = $details['start'] ?? gmdate('Y-m-d\TH:i:s\Z', time() + 300);
        $endIso = $details['end'] ?? gmdate('Y-m-d\TH:i:s\Z', strtotime($startIso) + ($durationMinutes * 60));

        $attendees = [];
        if (!empty($details['attendees']) && is_array($details['attendees'])) {
            foreach ($details['attendees'] as $att) {
                $attendees[] = is_string($att) ? ['email' => $att] : $att;
            }
        }

        $payload = [
            'summary'     => $details['summary'] ?? $details['topic'] ?? 'Google Meet Call',
            'description' => $details['description'] ?? '',
            'start'       => ['dateTime' => $startIso, 'timeZone' => $timeZone],
            'end'         => ['dateTime' => $endIso, 'timeZone' => $timeZone],
            'conferenceData' => [
                'createRequest' => [
                    'requestId' => 'meet_' . bin2hex(random_bytes(6)),
                    'conferenceSolutionKey' => [
                        'type' => 'hangoutsMeet',
                    ],
                ],
            ],
        ];

        if (!empty($attendees)) {
            $payload['attendees'] = $attendees;
        }

        if (static::$faking) {
            static::$requests[] = ['method' => 'POST', 'url' => $endpoint, 'payload' => $payload];
            $id = 'evt_' . bin2hex(random_bytes(6));
            $meetingCode = substr(bin2hex(random_bytes(5)), 0, 3) . '-' . substr(bin2hex(random_bytes(5)), 0, 4) . '-' . substr(bin2hex(random_bytes(5)), 0, 3);
            return static::$fakeResponses['create_meeting'] ?? [
                'id'            => $id,
                'summary'       => $payload['summary'],
                'hangoutLink'   => "https://meet.google.com/{$meetingCode}",
                'htmlLink'      => "https://www.google.com/calendar/event?eid={$id}",
                'start'         => $payload['start'],
                'end'           => $payload['end'],
                'conferenceData' => [
                    'conferenceId' => $meetingCode,
                    'entryPoints'  => [
                        [
                            'entryPointType' => 'video',
                            'uri'            => "https://meet.google.com/{$meetingCode}",
                            'label'          => "meet.google.com/{$meetingCode}",
                        ],
                    ],
                ],
            ];
        }

        return $this->sendAuthorizedRequest('POST', $endpoint, $payload);
    }

    /**
     * Get meeting / calendar event details by ID.
     */
    public function getMeeting(string $eventId, ?string $calendarId = null): array
    {
        $calendar = $calendarId ?? config('google-meet.calendar_id', 'primary');
        $endpoint = self::CALENDAR_BASE_URI . '/' . urlencode($calendar) . '/events/' . urlencode($eventId);

        if (static::$faking) {
            static::$requests[] = ['method' => 'GET', 'url' => $endpoint, 'payload' => null];
            return static::$fakeResponses['get_meeting'] ?? [
                'id'          => $eventId,
                'summary'     => 'Sample Google Meet',
                'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
            ];
        }

        return $this->sendAuthorizedRequest('GET', $endpoint);
    }

    /**
     * Update an existing event.
     */
    public function updateMeeting(string $eventId, array $details, ?string $calendarId = null): array
    {
        $calendar = $calendarId ?? config('google-meet.calendar_id', 'primary');
        $endpoint = self::CALENDAR_BASE_URI . '/' . urlencode($calendar) . '/events/' . urlencode($eventId);

        if (static::$faking) {
            static::$requests[] = ['method' => 'PATCH', 'url' => $endpoint, 'payload' => $details];
            return ['success' => true, 'id' => $eventId];
        }

        return $this->sendAuthorizedRequest('PATCH', $endpoint, $details);
    }

    /**
     * Delete an event by ID.
     */
    public function deleteMeeting(string $eventId, ?string $calendarId = null): bool
    {
        $calendar = $calendarId ?? config('google-meet.calendar_id', 'primary');
        $endpoint = self::CALENDAR_BASE_URI . '/' . urlencode($calendar) . '/events/' . urlencode($eventId);

        if (static::$faking) {
            static::$requests[] = ['method' => 'DELETE', 'url' => $endpoint, 'payload' => null];
            return true;
        }

        $res = $this->sendAuthorizedRequest('DELETE', $endpoint);
        return isset($res['status_code']) ? $res['status_code'] === 204 : true;
    }

    /**
     * List events / meetings on the calendar.
     */
    public function listMeetings(array $params = [], ?string $calendarId = null): array
    {
        $calendar = $calendarId ?? config('google-meet.calendar_id', 'primary');
        $queryString = !empty($params) ? '?' . http_build_query($params) : '';
        $endpoint = self::CALENDAR_BASE_URI . '/' . urlencode($calendar) . '/events' . $queryString;

        if (static::$faking) {
            static::$requests[] = ['method' => 'GET', 'url' => $endpoint, 'payload' => null];
            return static::$fakeResponses['list_meetings'] ?? [
                'items' => [
                    [
                        'id'          => 'evt_test_123',
                        'summary'     => 'Design Sprint Sync',
                        'hangoutLink' => 'https://meet.google.com/xyz-uvwx-rst',
                    ],
                ],
            ];
        }

        return $this->sendAuthorizedRequest('GET', $endpoint);
    }

    /**
     * Helper to send an authenticated JSON request.
     */
    protected function sendAuthorizedRequest(string $method, string $url, ?array $payload = null): array
    {
        $token = $this->getAccessToken();
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        return $this->sendHttpRequest($method, $url, $payload, $headers, false);
    }

    /**
     * Send HTTP request.
     */
    protected function sendHttpRequest(string $method, string $url, ?array $payload = null, array $headers = [], bool $isForm = false): array
    {
        $body = '';
        if ($payload !== null) {
            $body = $isForm ? http_build_query($payload) : json_encode($payload);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            if (!empty($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $raw = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        } else {
            $opts = [
                'http' => [
                    'method'  => $method,
                    'header'  => implode("\r\n", $headers) . "\r\n",
                    'content' => $body,
                    'timeout' => 15,
                    'ignore_errors' => true,
                ],
            ];
            $context = stream_context_create($opts);
            $raw = @file_get_contents($url, false, $context);
            $code = 200;
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : ['raw' => $raw, 'status_code' => $code ?? 200];
    }

    /**
     * Standard URL-safe Base64 encoder.
     */
    public function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
