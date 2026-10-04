<?php

declare(strict_types=1);

namespace Bongo\Packages\Sms\Contracts;

interface SMSDriver
{
    /**
     * Send an SMS message to a recipient phone number.
     *
     * @param string $to Recipient phone number (e.g. "01700000000" or "+8801700000000")
     * @param string $message Text message content
     * @return array{success: bool, id: ?string, raw: mixed, error: ?string}
     */
    public function send(string $to, string $message): array;
}
