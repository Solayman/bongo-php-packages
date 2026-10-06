<?php

declare(strict_types=1);

namespace Bongo\Packages\Coupon\Commands;

use Bongo\Packages\Coupon\Coupon;
use System\Console\Command;

class CouponCheckCommand extends Command
{
    protected string $name = 'coupon:check';
    protected string $description = 'Test and validate a promotion coupon against a cart amount';

    public function handle(array $args, array $options): int
    {
        $code = $args[0] ?? ($options['code'] ?? null);
        $amount = (float) ($args[1] ?? ($options['amount'] ?? 1000.0));
        $shipping = (float) ($options['shipping'] ?? 60.0);

        if (!$code) {
            $this->error('Usage: php bongo coupon:check <coupon_code> [cart_amount] [--shipping=60]');
            return 1;
        }

        $this->info("Validating coupon '{$code}' on cart of {$amount} (shipping: {$shipping})...");
        $res = Coupon::check((string) $code, $amount, $shipping);

        if ($res->isValid()) {
            $this->info("✓ Valid: {$res->getMessage()}");
            $this->line("  Discount : " . $res->getDiscount());
            $this->line("  Shipping : " . $res->getShippingDiscount());
            $this->line("  New Total: " . $res->getNewTotal());
            return 0;
        }

        $this->warn("✗ Invalid: {$res->getMessage()}");
        return 1;
    }
}
