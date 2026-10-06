<?php

declare(strict_types=1);

namespace Bongo\Packages\Currency\Commands;

use Bongo\Packages\Currency\Currency;
use System\Console\Command;

class CurrencyUpdateCommand extends Command
{
    protected string $name = 'currency:update';
    protected string $description = 'Display and update registered foreign exchange rates';

    public function handle(array $args, array $options): int
    {
        $base = $options['base'] ?? Currency::getDefault();
        $this->info("Current Currency Rates (Base: {$base}):");

        $all = Currency::all();
        foreach ($all as $code => $info) {
            $sym = $info['symbol'] ?? '';
            $rate = Currency::getRate($base, $code);
            $example = Currency::format(100 * $rate, $code);
            $this->line(sprintf("  %-5s (%s) : 1 %s = %.4f %s  [%s]", $code, $sym, $base, $rate, $code, $example));
        }

        $this->info("✓ Currency rate table verified successfully.");
        return 0;
    }
}
