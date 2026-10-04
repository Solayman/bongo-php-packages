<?php

declare(strict_types=1);

namespace Bongo\Packages\Sms\Drivers;

use Bongo\Packages\Sms\Contracts\SMSDriver;
use RuntimeException;

class BulkSMSBDDriver implements SMSDriver
{
    protected string $apiKey;
    protected string $senderId;
    protected string $url;

    /** @var callable|null Test mock transport */
    protected static $mockTransport = null;

    public function __construct(array $config = [])
    {
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $this->senderId = (string) ($config['sender_id'] ?? '');
        $this->url = (string) ($config['url'] ?? 'http://bulksmsbd.net/api/smsapi');
    }

    public static function setMockTransport(?callable $transport): void
    {
        self::$mockTransport = $transport;
    }

    public function send(string $to, string $message): array
    {
        if ($this->apiKey === '' || $this->senderId === '') {
            throw new RuntimeException("BulkSMSBD credentials missing. Set BULKSMSBD_API_KEY and BULKSMSBD_SENDER_ID in .env");
        }

        $params = [
            'api_key'  => $this->apiKey,
            'type'     => 'text',
            'number'   => $this->formatNumber($to),
            'senderid' => $this->senderId,
            'message'  => $message,
        ];

        if (self::$mockTransport !== null) {
            $response = (array) call_user_func(self::$mockTransport, $this->url, $params);
        } else {
            $endpoint = $this->url . '?' . http_build_query($params);
            $context = stream_context_create([
                'http' => ['method' => 'GET', 'timeout' => 15, 'ignore_errors' => true],
            ]);
            $raw = @file_get_contents($endpoint, false, $context);
            if ($raw === false) {
                $err = error_get_last()['message'] ?? 'Network error';
                return ['success' => false, 'id' => null, 'raw' => null, 'error' => $err];
            }
            $response = json_decode($raw, true) ?: ['raw' => $raw];
        }

        $code = (int) ($response['response_code'] ?? 0);
        $success = ($code === 202);

        return [
            'success' => $success,
            'id'      => isset($response['message_id']) ? (string) $response['message_id'] : null,
            'raw'     => $response,
            'error'   => $success ? null : ($response['error_message'] ?? 'BulkSMSBD API error (code ' . $code . ')'),
        ];
    }

    protected function formatNumber(string $number): string
    {
        $clean = preg_replace('/[^0-9]/', '', $number);
        if (str_starts_with($clean, '880')) {
            return $clean;
        }
        if (str_starts_with($clean, '01')) {
            return '88' . $clean;
        }
        return $clean;
    }
}
