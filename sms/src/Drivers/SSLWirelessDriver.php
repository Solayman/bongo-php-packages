<?php

declare(strict_types=1);

namespace Bongo\Packages\Sms\Drivers;

use Bongo\Packages\Sms\Contracts\SMSDriver;
use RuntimeException;

class SSLWirelessDriver implements SMSDriver
{
    protected string $apiToken;
    protected string $sid;
    protected string $url;

    /** @var callable|null Test mock transport */
    protected static $mockTransport = null;

    public function __construct(array $config = [])
    {
        $this->apiToken = (string) ($config['api_token'] ?? '');
        $this->sid = (string) ($config['sid'] ?? '');
        $this->url = (string) ($config['url'] ?? 'https://smsplus.sslwireless.com/api/v3/send-sms');
    }

    public static function setMockTransport(?callable $transport): void
    {
        self::$mockTransport = $transport;
    }

    public function send(string $to, string $message): array
    {
        if ($this->apiToken === '' || $this->sid === '') {
            throw new RuntimeException("SSL Wireless credentials missing. Set SSLWIRELESS_API_TOKEN and SSLWIRELESS_SID in .env");
        }

        $csmsId = 'CSMS_' . bin2hex(random_bytes(6));
        $payload = [
            'api_token' => $this->apiToken,
            'sid'       => $this->sid,
            'msisdn'    => $this->formatNumber($to),
            'sms'       => $message,
            'csms_id'   => $csmsId,
        ];

        if (self::$mockTransport !== null) {
            $response = (array) call_user_func(self::$mockTransport, $this->url, $payload);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                    'content'       => json_encode($payload),
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

        $status = strtoupper((string) ($response['status'] ?? ''));
        $statusCode = (int) ($response['status_code'] ?? 0);
        $success = ($status === 'SUCCESS' || $statusCode === 200);

        return [
            'success' => $success,
            'id'      => isset($response['smsinfo'][0]['sms_id'])
                ? (string) $response['smsinfo'][0]['sms_id']
                : ($response['csms_id'] ?? $csmsId),
            'raw'     => $response,
            'error'   => $success ? null : ($response['error_message'] ?? 'SSL Wireless API error'),
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
