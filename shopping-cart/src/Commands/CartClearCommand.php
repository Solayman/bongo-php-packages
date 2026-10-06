<?php

declare(strict_types=1);

namespace Bongo\Packages\ShoppingCart\Commands;

use Bongo\Packages\ShoppingCart\Cart;
use System\Console\Command;

class CartClearCommand extends Command
{
    protected string $signature = 'cart:clear {instance=default : The cart instance to clear}';
    protected string $description = 'Clear items from the specified shopping cart instance';

    public function handle(array $args, array $options): int
    {
        $instance = $args['instance'] ?? 'default';
        $cart = Cart::instance($instance);
        $count = $cart->count();

        $cart->clear();

        $this->info("Successfully cleared shopping cart instance [{$instance}] ({$count} items removed).");
        return 0;
    }
}
