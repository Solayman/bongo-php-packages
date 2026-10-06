<?php

declare(strict_types=1);

namespace Bongo\Packages\ShoppingCart;

use Bongo\Packages\ShoppingCart\Exceptions\CartException;

class Cart
{
    /** @var array<string, static> */
    protected static array $instances = [];

    protected string $instanceName = 'default';
    protected string $driver = 'session';

    /** @var array<string, CartItem> */
    protected array $items = [];

    protected float $taxRate = 0.0;
    protected float $shipping = 0.0;
    protected float $discount = 0.0;
    protected ?string $couponCode = null;

    public function __construct(string $instanceName = 'default')
    {
        $this->instanceName = $instanceName;

        if (function_exists('config')) {
            $this->taxRate = (float) config('cart.default_tax_rate', 0.0);
            $this->driver = (string) config('cart.driver', 'session');
        }

        $this->load();
    }

    /**
     * Get or create a named cart instance (e.g. 'default', 'wishlist').
     */
    public static function instance(?string $name = null): static
    {
        $name = $name ?? (function_exists('config') ? config('cart.default_instance', 'default') : 'default');

        if (!isset(static::$instances[$name])) {
            static::$instances[$name] = new static($name);
        }

        return static::$instances[$name];
    }

    /**
     * Switch driver to in-memory mode for testing.
     */
    public static function fake(): static
    {
        $cart = static::instance();
        $cart->setDriver('memory');
        $cart->clear();
        return $cart;
    }

    public function currentInstance(): string
    {
        return $this->instanceName;
    }

    public function setDriver(string $driver): static
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Add an item to the shopping cart.
     */
    public function add(
        string|int $id,
        string $name,
        float $price,
        int $quantity = 1,
        array $options = [],
        float $taxRate = 0.0,
        ?string $associatedModel = null
    ): CartItem {
        $tax = $taxRate > 0.0 ? $taxRate : $this->taxRate;
        $tempItem = new CartItem($id, $name, $price, $quantity, $options, $tax, $associatedModel);
        $rowId = $tempItem->rowId();

        if (isset($this->items[$rowId])) {
            $existing = $this->items[$rowId];
            $existing->setQuantity($existing->quantity() + $quantity);
            $this->persist();
            return $existing;
        }

        $this->items[$rowId] = $tempItem;
        $this->persist();

        return $tempItem;
    }

    /**
     * Update an item by rowId.
     * Can update quantity (integer) or multiple attributes (array).
     */
    public function update(string $rowId, int|array $attribute): CartItem
    {
        if (!isset($this->items[$rowId])) {
            throw new CartException("Cart item with rowId [{$rowId}] does not exist.");
        }

        $item = $this->items[$rowId];

        if (is_int($attribute)) {
            if ($attribute <= 0) {
                $this->remove($rowId);
                return $item;
            }
            $item->setQuantity($attribute);
        } elseif (is_array($attribute)) {
            if (isset($attribute['quantity'])) {
                $qty = (int) $attribute['quantity'];
                if ($qty <= 0) {
                    $this->remove($rowId);
                    return $item;
                }
                $item->setQuantity($qty);
            }
            if (isset($attribute['price'])) {
                $item->setPrice((float) $attribute['price']);
            }
            if (isset($attribute['taxRate'])) {
                $item->setTaxRate((float) $attribute['taxRate']);
            }
            if (isset($attribute['options']) && is_array($attribute['options'])) {
                unset($this->items[$rowId]);
                $item->setOptions($attribute['options']);
                $this->items[$item->rowId()] = $item;
            }
        }

        $this->persist();
        return $item;
    }

    /**
     * Remove an item from the cart.
     */
    public function remove(string $rowId): void
    {
        if (isset($this->items[$rowId])) {
            unset($this->items[$rowId]);
            $this->persist();
        }
    }

    /**
     * Get a specific cart item by rowId.
     */
    public function get(string $rowId): ?CartItem
    {
        return $this->items[$rowId] ?? null;
    }

    /**
     * Check if a rowId exists in the cart.
     */
    public function has(string $rowId): bool
    {
        return isset($this->items[$rowId]);
    }

    /**
     * Get all cart items.
     *
     * @return array<string, CartItem>
     */
    public function content(): array
    {
        return $this->items;
    }

    /**
     * Total quantity of all items combined.
     */
    public function count(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->quantity();
        }
        return $count;
    }

    /**
     * Number of distinct line items / rows.
     */
    public function countRows(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !empty($this->items);
    }

    /**
     * Subtotal before taxes, discounts, and shipping.
     */
    public function subtotal(): float
    {
        $sum = 0.0;
        foreach ($this->items as $item) {
            $sum += $item->subtotal();
        }
        return round($sum, 2);
    }

    /**
     * Total tax for all items.
     */
    public function tax(): float
    {
        $sum = 0.0;
        foreach ($this->items as $item) {
            $sum += $item->tax();
        }
        return round($sum, 2);
    }

    public function setTaxRate(float $rate): static
    {
        $this->taxRate = max(0.0, $rate);
        foreach ($this->items as $item) {
            if ($item->taxRate() <= 0.0) {
                $item->setTaxRate($this->taxRate);
            }
        }
        $this->persist();
        return $this;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setShipping(float $amount): static
    {
        $this->shipping = max(0.0, $amount);
        $this->persist();
        return $this;
    }

    public function getShipping(): float
    {
        return $this->shipping;
    }

    public function setDiscount(float $amount): static
    {
        $this->discount = max(0.0, $amount);
        $this->persist();
        return $this;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function discount(): float
    {
        return $this->getDiscount();
    }

    /**
     * Apply a coupon code, integrating seamlessly with coupon() package if available.
     */
    public function applyCoupon(string $code, ?float $amount = null): static
    {
        $this->couponCode = $code;

        if ($amount !== null) {
            $this->discount = max(0.0, $amount);
        } elseif (function_exists('coupon')) {
            $res = coupon()->validate($code, $this->subtotal());
            if ($res->isValid()) {
                $this->discount = $res->discount();
                if ($res->isFreeShipping()) {
                    $this->shipping = 0.0;
                }
            } else {
                throw new CartException("Coupon [{$code}] invalid: " . $res->message());
            }
        }

        $this->persist();
        return $this;
    }

    public function removeCoupon(): static
    {
        $this->couponCode = null;
        $this->discount = 0.0;
        $this->persist();
        return $this;
    }

    public function getCoupon(): ?string
    {
        return $this->couponCode;
    }

    /**
     * Final grand total: subtotal - discount + tax + shipping.
     */
    public function total(): float
    {
        $final = $this->subtotal() - $this->discount + $this->tax() + $this->shipping;
        return max(0.0, round($final, 2));
    }

    /**
     * Clear all items, discounts, shipping and coupon.
     */
    public function clear(): void
    {
        $this->items = [];
        $this->discount = 0.0;
        $this->shipping = 0.0;
        $this->couponCode = null;
        $this->persist();
    }

    /**
     * Alias for clear.
     */
    public function destroy(): void
    {
        $this->clear();
    }

    /**
     * Convert full cart state to array.
     */
    public function toArray(): array
    {
        $itemsArray = [];
        foreach ($this->items as $rowId => $item) {
            $itemsArray[$rowId] = $item->toArray();
        }

        return [
            'instance' => $this->instanceName,
            'items' => $itemsArray,
            'count' => $this->count(),
            'countRows' => $this->countRows(),
            'subtotal' => $this->subtotal(),
            'discount' => $this->discount,
            'coupon' => $this->couponCode,
            'shipping' => $this->shipping,
            'tax' => $this->tax(),
            'total' => $this->total(),
        ];
    }

    protected function sessionKey(): string
    {
        return 'bongo_cart_' . $this->instanceName;
    }

    protected function load(): void
    {
        if ($this->driver === 'session' && (session_status() === PHP_SESSION_ACTIVE || !empty($_SESSION))) {
            $data = $_SESSION[$this->sessionKey()] ?? null;
            if (is_array($data)) {
                $this->taxRate = (float) ($data['taxRate'] ?? $this->taxRate);
                $this->shipping = (float) ($data['shipping'] ?? 0.0);
                $this->discount = (float) ($data['discount'] ?? 0.0);
                $this->couponCode = $data['coupon'] ?? null;
                $this->items = [];

                if (isset($data['items']) && is_array($data['items'])) {
                    foreach ($data['items'] as $raw) {
                        $item = new CartItem(
                            $raw['id'],
                            $raw['name'],
                            (float) $raw['price'],
                            (int) $raw['quantity'],
                            (array) ($raw['options'] ?? []),
                            (float) ($raw['taxRate'] ?? 0.0),
                            $raw['associatedModel'] ?? null
                        );
                        $this->items[$item->rowId()] = $item;
                    }
                }
            }
        }
    }

    protected function persist(): void
    {
        if ($this->driver === 'session') {
            if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent() && !isset($_SESSION)) {
                @session_start();
            }

            if (isset($_SESSION)) {
                $rawItems = [];
                foreach ($this->items as $rowId => $item) {
                    $rawItems[$rowId] = $item->toArray();
                }

                $_SESSION[$this->sessionKey()] = [
                    'taxRate' => $this->taxRate,
                    'shipping' => $this->shipping,
                    'discount' => $this->discount,
                    'coupon' => $this->couponCode,
                    'items' => $rawItems,
                ];
            }
        }
    }
}
