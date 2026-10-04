<?php

namespace Bongo\Packages\Sms\Drivers;

use Bongo\Packages\Sms\Contracts\SMSDriver;
use RuntimeException;

class AlphaSMSDriver implements SMSDriver
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
        $this->url = (string) ($config['url'] ?? 'https://api.sms.net.bd/sendsms');
    }

    public static function setMockTransport(?callable $transport): void
    {
        self::$mockTransport = $transport;
    }

    public function send(string $to, string $message): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException("Alpha SMS API key missing. Set ALPHASMS_API_KEY in .env");
        }

        $payload = [
            'api_key'   => $this->apiKey,
            'msg'       => $message,
            'to'        => $this->formatNumber($to),
        ];

        if ($this->senderId !== '') {
            $payload['sender_id'] = $this->senderId;
        }

        if (self::$mockTransport !== null) {
            $response = (array) call_user_func(self::$mockTransport, $this->url, $payload);
        } else {
            $body = http_build_query($payload);
            $context = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content'       => $body,
                    'timeout'       => 15,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $raw = @file_get_contents($this->url, false, $context);
            if ($raw === false) {
                $err = error_get_last()['message'] ?? 'Network error';
                return ['success' => false, 'id' => null, 'raw' => null, 'error' => $err];
            }
            $response = json_decode($raw, true) ?: ['raw' => $raw];
        }

        $error = (int) ($response['error'] ?? 1);
        $success = ($error === 0);

        return [
            'success' => $success,
            'id'      => isset($response['data']['request_id'])
                ? (string) $response['data']['request_id']
                : ($response['msg_id'] ?? null),
            'raw'     => $response,
            'error'   => $success ? null : ($response['msg'] ?? 'Alpha SMS error (code ' . $error . ')'),
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
