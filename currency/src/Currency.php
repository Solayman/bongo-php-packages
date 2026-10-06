<?php

declare(strict_types=1);

namespace Bongo\Packages\Currency;

use InvalidArgumentException;

class Currency
{
    /** @var array<string, array> */
    protected static array $currencies = [];
    /** @var array<string, float> */
    protected static array $customRates = [];
    /** @var string */
    protected static string $defaultCurrency = 'BDT';
    /** @var bool */
    protected static bool $fake = false;

    /**
     * Initialize currency definitions from config.
     */
    public static function boot(): void
    {
        if (empty(self::$currencies)) {
            self::$defaultCurrency = (string) config('currency.default', 'BDT');
            self::$currencies = config('currency.currencies', [
                'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳', 'precision' => 2, 'rate' => 1.0, 'format' => ':symbol:amount'],
                'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'precision' => 2, 'rate' => 0.0082, 'format' => ':symbol:amount'],
                'EUR' => ['name' => 'Euro', 'symbol' => '€', 'precision' => 2, 'rate' => 0.0076, 'format' => ':symbol:amount'],
                'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'precision' => 2, 'rate' => 0.0064, 'format' => ':symbol:amount'],
            ]);
        }
    }

    public static function getDefault(): string
    {
        self::boot();
        return self::$defaultCurrency;
    }

    public static function setDefault(string $currency): void
    {
        self::$defaultCurrency = strtoupper($currency);
    }

    public static function setRate(string $from, string $to, float $rate): void
    {
        $key = strtoupper($from) . '_' . strtoupper($to);
        self::$customRates[$key] = $rate;

        // Inverse rate automatically
        if ($rate > 0) {
            $invKey = strtoupper($to) . '_' . strtoupper($from);
            self::$customRates[$invKey] = 1.0 / $rate;
        }
    }

    public static function getRate(string $from, string $to): float
    {
        self::boot();
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $directKey = $from . '_' . $to;
        if (isset(self::$customRates[$directKey])) {
            return self::$customRates[$directKey];
        }

        $base = self::$defaultCurrency;
        if ($from !== $base && $to !== $base) {
            $fromToBase = $from . '_' . $base;
            $baseToTo = $base . '_' . $to;
            if (isset(self::$customRates[$fromToBase]) && isset(self::$customRates[$baseToTo])) {
                return self::$customRates[$fromToBase] * self::$customRates[$baseToTo];
            }
        }

        // Base-currency bridge (BDT is base = 1.0)
        $fromRate = self::$currencies[$from]['rate'] ?? null;
        $toRate = self::$currencies[$to]['rate'] ?? null;

        if ($fromRate !== null && $toRate !== null && $fromRate > 0) {
            return $toRate / $fromRate;
        }

        throw new InvalidArgumentException("Exchange rate not defined between {$from} and {$to}.");
    }

    public static function convert(float $amount, string $from, ?string $to = null, int $decimals = 2): float
    {
        self::boot();
        $to = $to ?? self::$defaultCurrency;
        $rate = self::getRate($from, $to);
        return round($amount * $rate, $decimals);
    }

    public static function format(float $amount, ?string $currency = null, bool $includeSymbol = true): string
    {
        self::boot();
        $curr = strtoupper($currency ?? self::$defaultCurrency);
        $info = self::$currencies[$curr] ?? [
            'symbol'    => $curr . ' ',
            'precision' => 2,
            'format'    => ':symbol:amount',
        ];

        $precision = $info['precision'] ?? 2;
        $formattedNum = number_format($amount, $precision, '.', ',');

        if (!$includeSymbol) {
            return $formattedNum;
        }

        $symbol = $info['symbol'] ?? '';
        $pattern = $info['format'] ?? ':symbol:amount';

        return str_replace([':symbol', ':amount'], [$symbol, $formattedNum], $pattern);
    }

    public static function symbol(string $currency): string
    {
        self::boot();
        $curr = strtoupper($currency);
        return self::$currencies[$curr]['symbol'] ?? $curr;
    }

    public static function has(string $currency): bool
    {
        self::boot();
        return isset(self::$currencies[strtoupper($currency)]);
    }

    public static function all(): array
    {
        self::boot();
        return self::$currencies;
    }

    public static function fake(array $rates = []): void
    {
        self::$fake = true;
        foreach ($rates as $pair => $rate) {
            [$from, $to] = explode('_', strtoupper($pair));
            self::setRate($from, $to, (float) $rate);
        }
    }

    public static function clearFake(): void
    {
        self::$fake = false;
        self::$customRates = [];
        self::$currencies = [];
    }
}
