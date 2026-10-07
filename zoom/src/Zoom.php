<?php

declare(strict_types=1);

namespace Bongo\Packages\Zoom;

use RuntimeException;
use InvalidArgumentException;

class Zoom
{
    protected const OAUTH_URL = 'https://zoom.us/oauth/token';
    protected const BASE_URL  = 'https://api.zoom.us/v2';

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
     * Get all intercepted requests recorded during fake mode.
     */
    public static function getRequests(): array
    {
        return static::$requests;
    }

    /**
     * Retrieve or generate Server-to-Server OAuth access token.
     */
    public function getAccessToken(): string
    {
        if (static::$faking) {
            return 'fake_zoom_access_token_' . time();
        }

        if ($this->cachedToken !== null && time() < ($this->tokenExpiresAt - 60)) {
            return $this->cachedToken;
        }

        $accountId    = config('zoom.account_id', '');
        $clientId     = config('zoom.client_id', '');
        $clientSecret = config('zoom.client_secret', '');

        if (empty($accountId) || empty($clientId) || empty($clientSecret)) {
            throw new InvalidArgumentException('Zoom credentials (account_id, client_id, client_secret) are not configured.');
        }

        $url = self::OAUTH_URL . '?grant_type=account_credentials&account_id=' . urlencode($accountId);
        $basicAuth = base64_encode($clientId . ':' . $clientSecret);

        $response = $this->sendHttpRequest('POST', $url, [], [
            'Authorization: Basic ' . $basicAuth,
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        if (empty($response['access_token'])) {
            throw new RuntimeException('Failed to obtain Zoom access token: ' . json_encode($response));
        }

        $this->cachedToken = $response['access_token'];
        $this->tokenExpiresAt = time() + (int) ($response['expires_in'] ?? 3600);

        return $this->cachedToken;
    }

    /**
     * Create a Zoom meeting.
     */
    public function createMeeting(array $data, ?string $userId = null): array
    {
        $user = $userId ?? config('zoom.default_user_id', 'me');
        $endpoint = self::BASE_URL . '/users/' . urlencode($user) . '/meetings';

        $payload = [
            'topic'      => $data['topic'] ?? 'New Meeting',
            'type'       => $data['type'] ?? 2, // 2 = Scheduled meeting
            'start_time' => $data['start_time'] ?? gmdate('Y-m-d\TH:i:s\Z', time() + 300),
            'duration'   => $data['duration'] ?? config('zoom.default_duration', 45),
            'timezone'   => $data['timezone'] ?? config('zoom.default_timezone', 'UTC'),
            'agenda'     => $data['agenda'] ?? '',
            'settings'   => array_merge(config('zoom.settings', []), $data['settings'] ?? []),
        ];

        if (isset($data['password'])) {
            $payload['password'] = $data['password'];
        }

        if (static::$faking) {
            static::$requests[] = ['method' => 'POST', 'url' => $endpoint, 'payload' => $payload];
            return static::$fakeResponses['create_meeting'] ?? [
                'id'         => (string) rand(10000000000, 99999999999),
                'topic'      => $payload['topic'],
                'type'       => $payload['type'],
                'start_time' => $payload['start_time'],
                'duration'   => $payload['duration'],
                'timezone'   => $payload['timezone'],
                'join_url'   => 'https://zoom.us/j/' . rand(1000000000, 9999999999),
                'start_url'  => 'https://zoom.us/s/' . rand(1000000000, 9999999999),
                'password'   => $payload['password'] ?? 'sec123',
                'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            ];
        }

        return $this->sendAuthorizedRequest('POST', $endpoint, $payload);
    }

    /**
     * Get meeting details by ID.
     */
    public function getMeeting(string|int $meetingId): array
    {
        $endpoint = self::BASE_URL . '/meetings/' . urlencode((string) $meetingId);

        if (static::$faking) {
            static::$requests[] = ['method' => 'GET', 'url' => $endpoint, 'payload' => null];
            return static::$fakeResponses['get_meeting'] ?? [
                'id'       => (string) $meetingId,
                'topic'    => 'Sample Zoom Meeting',
                'join_url' => 'https://zoom.us/j/' . $meetingId,
            ];
        }

        return $this->sendAuthorizedRequest('GET', $endpoint);
    }

    /**
     * Update an existing meeting.
     */
    public function updateMeeting(string|int $meetingId, array $data): array
    {
        $endpoint = self::BASE_URL . '/meetings/' . urlencode((string) $meetingId);

        if (static::$faking) {
            static::$requests[] = ['method' => 'PATCH', 'url' => $endpoint, 'payload' => $data];
            return ['success' => true];
        }

        return $this->sendAuthorizedRequest('PATCH', $endpoint, $data);
    }

    /**
     * Delete a meeting by ID.
     */
    public function deleteMeeting(string|int $meetingId): bool
    {
        $endpoint = self::BASE_URL . '/meetings/' . urlencode((string) $meetingId);

        if (static::$faking) {
            static::$requests[] = ['method' => 'DELETE', 'url' => $endpoint, 'payload' => null];
            return true;
        }

        $res = $this->sendAuthorizedRequest('DELETE', $endpoint);
        return isset($res['status_code']) ? $res['status_code'] === 204 : true;
    }

    /**
     * List user meetings.
     */
    public function listMeetings(?string $userId = null, array $params = []): array
    {
        $user = $userId ?? config('zoom.default_user_id', 'me');
        $queryString = !empty($params) ? '?' . http_build_query($params) : '';
        $endpoint = self::BASE_URL . '/users/' . urlencode($user) . '/meetings' . $queryString;

        if (static::$faking) {
            static::$requests[] = ['method' => 'GET', 'url' => $endpoint, 'payload' => null];
            return static::$fakeResponses['list_meetings'] ?? [
                'page_count'     => 1,
                'page_number'    => 1,
                'page_size'      => 30,
                'total_records'  => 1,
                'meetings'       => [
                    ['id' => '12345678901', 'topic' => 'Demo Zoom Meeting', 'type' => 2]
                ]
            ];
        }

        return $this->sendAuthorizedRequest('GET', $endpoint);
    }

    /**
     * Add a registrant to a scheduled meeting.
     */
    public function addRegistrant(string|int $meetingId, array $data): array
    {
        $endpoint = self::BASE_URL . '/meetings/' . urlencode((string) $meetingId) . '/registrants';

        if (static::$faking) {
            static::$requests[] = ['method' => 'POST', 'url' => $endpoint, 'payload' => $data];
            return static::$fakeResponses['add_registrant'] ?? [
                'id'       => (string) $meetingId,
                'registrant_id' => 'reg_' . bin2hex(random_bytes(4)),
                'join_url' => 'https://zoom.us/w/' . $meetingId . '?tk=xyz',
            ];
        }

        return $this->sendAuthorizedRequest('POST', $endpoint, $data);
    }

    /**
     * Verify incoming Zoom webhook signature.
     * Zoom signature format: v0=hash_hmac('sha256', "v0:$timestamp:$payload", $secretToken)
     */
    public function verifyWebhook(string $payload, string $timestamp, string $signature, ?string $secretToken = null): bool
    {
        $token = $secretToken ?? config('zoom.secret_token', '');
        if (empty($token) || empty($signature) || empty($timestamp)) {
            return false;
        }

        // Zoom sends the time in milliseconds; refuse requests older/newer than 5 minutes (replays)
        $seconds = strlen($timestamp) > 10 ? intdiv((int) $timestamp, 1000) : (int) $timestamp;
        $tolerance = (int) (function_exists('config') ? config('zoom.webhook_tolerance', 300) : 300);
        if ($tolerance > 0 && abs(time() - $seconds) > $tolerance) {
            return false;
        }

        $message = "v0:{$timestamp}:{$payload}";
        $expected = 'v0=' . hash_hmac('sha256', $message, $token);

        return hash_equals($expected, $signature);
    }

    /**
     * Handle Zoom webhook URL validation challenge (endpoint.url_validation).
     * Returns JSON array for HTTP response or null if not a validation event.
     */
    public function handleWebhookValidation(array $event, ?string $secretToken = null): ?array
    {
        if (($event['event'] ?? '') !== 'endpoint.url_validation') {
            return null;
        }

        $plainToken = $event['payload']['plainToken'] ?? null;
        if (!$plainToken) {
            return null;
        }

        $token = (string) ($secretToken ?? config('zoom.secret_token', ''));
        if ($token === '') {
            return null;   // without a secret token the endpoint cannot be validated
        }
        $encryptedToken = hash_hmac('sha256', (string) $plainToken, $token);

        return [
            'plainToken'     => $plainToken,
            'encryptedToken' => $encryptedToken,
        ];
    }

    /**
     * Helper to send an authenticated request with bearer token.
     */
    protected function sendAuthorizedRequest(string $method, string $url, ?array $payload = null): array
    {
        $token = $this->getAccessToken();
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        return $this->sendHttpRequest($method, $url, $payload, $headers);
    }

    /**
     * Pure-PHP HTTP request handler using cURL or stream wrapper.
     */
    protected function sendHttpRequest(string $method, string $url, ?array $payload = null, array $headers = []): array
    {
        $body = $payload !== null ? json_encode($payload) : '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
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
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ];
            $context = stream_context_create($opts);
            $raw = @file_get_contents($url, false, $context);
            $code = 200;
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : ['raw' => $raw, 'status_code' => $code ?? 200];
    }
}
