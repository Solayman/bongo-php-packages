<?php

declare(strict_types=1);

namespace Bongo\Packages\Paypal\Exceptions;

use RuntimeException;
use Throwable;

class PaypalException extends RuntimeException
{
    protected array $details = [];

    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $details = [])
    {
        parent::__construct($message, $code, $previous);
        $this->details = $details;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
