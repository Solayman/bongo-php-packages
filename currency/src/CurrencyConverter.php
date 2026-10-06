<?php

declare(strict_types=1);

namespace Bongo\Packages\Currency;

class CurrencyConverter
{
    protected float $amount;
    protected string $fromCurrency;

    public function __construct(float $amount, ?string $from = null)
    {
        $this->amount = $amount;
        $this->fromCurrency = $from ?? Currency::getDefault();
    }

    public static function of(float $amount, ?string $from = null): self
    {
        return new self($amount, $from);
    }

    public function from(string $currency): self
    {
        $this->fromCurrency = strtoupper($currency);
        return $this;
    }

    public function to(string $targetCurrency, int $decimals = 2): float
    {
        return Currency::convert($this->amount, $this->fromCurrency, $targetCurrency, $decimals);
    }

    public function format(?string $targetCurrency = null, bool $includeSymbol = true): string
    {
        $target = $targetCurrency ?? $this->fromCurrency;
        $converted = ($target === $this->fromCurrency)
            ? $this->amount
            : $this->to($target);

        return Currency::format($converted, $target, $includeSymbol);
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getFromCurrency(): string
    {
        return $this->fromCurrency;
    }
}
