<?php

declare(strict_types=1);

namespace Bongo\Packages\Honeypot\Exceptions;

use RuntimeException;

class SpamDetectedException extends RuntimeException
{
    protected string $reason;

    public function __construct(string $message = 'Spam submission detected by honeypot.', string $reason = 'spam')
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
