<?php

declare(strict_types=1);

namespace Bongo\Packages\Health\Checks;

class CheckResult implements \JsonSerializable
{
    public const STATUS_OK = 'ok';
    public const STATUS_WARNING = 'warning';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public function __construct(
        public string $name,
        public string $status,
        public string $message,
        public float $latencyMs = 0.0,
        public array $meta = []
    ) {
    }

    public static function ok(string $name, string $message = 'Check passed', float $latencyMs = 0.0, array $meta = []): self
    {
        return new self($name, self::STATUS_OK, $message, $latencyMs, $meta);
    }

    public static function warning(string $name, string $message, float $latencyMs = 0.0, array $meta = []): self
    {
        return new self($name, self::STATUS_WARNING, $message, $latencyMs, $meta);
    }

    public static function failed(string $name, string $message, float $latencyMs = 0.0, array $meta = []): self
    {
        return new self($name, self::STATUS_FAILED, $message, $latencyMs, $meta);
    }

    public static function skipped(string $name, string $message = 'Check skipped'): self
    {
        return new self($name, self::STATUS_SKIPPED, $message, 0.0);
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
            'message' => $this->message,
            'latency_ms' => round($this->latencyMs, 2),
            'meta' => $this->meta,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
