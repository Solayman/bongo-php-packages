<?php

declare(strict_types=1);

namespace Bongo\Packages\Honeypot;

use JsonSerializable;

class HoneypotResult implements JsonSerializable
{
    protected bool $valid;
    protected ?string $reason;
    protected int $elapsedSeconds;

    public function __construct(bool $valid, ?string $reason = null, int $elapsedSeconds = 0)
    {
        $this->valid = $valid;
        $this->reason = $reason;
        $this->elapsedSeconds = $elapsedSeconds;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function isSpam(): bool
    {
        return !$this->valid;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function elapsedSeconds(): int
    {
        return $this->elapsedSeconds;
    }

    public function message(): string
    {
        return match ($this->reason) {
            'decoy_filled' => 'Bot submission detected (decoy honeypot field was filled).',
            'too_fast' => "Form was submitted unrealistically fast ({$this->elapsedSeconds}s).",
            'expired' => 'Form submission has expired. Please refresh the page and try again.',
            'invalid_signature' => 'Honeypot verification signature is missing or tampered.',
            default => 'Honeypot validation passed.',
        };
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'isSpam' => $this->isSpam(),
            'reason' => $this->reason,
            'elapsedSeconds' => $this->elapsedSeconds,
            'message' => $this->message(),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
