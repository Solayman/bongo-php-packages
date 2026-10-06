<?php

declare(strict_types=1);

namespace Bongo\Packages\Coupon;

class Coupon
{
    protected string $code;
    protected string $type; // 'percentage', 'fixed', 'free_shipping'
    protected float $value;
    protected float $minOrderAmount = 0.0;
    protected ?float $maxDiscount = null;
    protected ?string $startsAt = null;
    protected ?string $expiresAt = null;
    protected ?int $usageLimit = null;
    protected int $usageCount = 0;
    protected bool $isActive = true;

    /** @var array<string, Coupon> */
    protected static array $registry = [];
    protected static bool $booted = false;

    public function __construct(string $code, string $type, float $value = 0.0)
    {
        $this->code = strtoupper(trim($code));
        $this->type = strtolower($type);
        $this->value = $value;
    }

    public static function make(string $code, string $type, float $value = 0.0): self
    {
        return new self($code, $type, $value);
    }

    public static function percentage(string $code, float $percent): self
    {
        return new self($code, 'percentage', $percent);
    }

    public static function fixed(string $code, float $amount): self
    {
        return new self($code, 'fixed', $amount);
    }

    public static function freeShipping(string $code): self
    {
        return new self($code, 'free_shipping', 0.0);
    }

    public function minOrder(float $min): self
    {
        $this->minOrderAmount = $min;
        return $this;
    }

    public function maxDiscount(?float $max): self
    {
        $this->maxDiscount = $max;
        return $this;
    }

    public function startsAt(?string $date): self
    {
        $this->startsAt = $date;
        return $this;
    }

    public function expiresAt(?string $date): self
    {
        $this->expiresAt = $date;
        return $this;
    }

    public function usageLimit(?int $limit): self
    {
        $this->usageLimit = $limit;
        return $this;
    }

    public function usageCount(int $count): self
    {
        $this->usageCount = $count;
        return $this;
    }

    public function active(bool $active): self
    {
        $this->isActive = $active;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getMinOrderAmount(): float
    {
        return $this->minOrderAmount;
    }

    public function getMaxDiscount(): ?float
    {
        return $this->maxDiscount;
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt !== null) {
            return strtotime($this->expiresAt) < time();
        }
        return false;
    }

    public function isStarted(): bool
    {
        if ($this->startsAt !== null) {
            return strtotime($this->startsAt) <= time();
        }
        return true;
    }

    public function hasReachedUsageLimit(): bool
    {
        if ($this->usageLimit !== null) {
            return $this->usageCount >= $this->usageLimit;
        }
        return false;
    }

    /**
     * Evaluate coupon validity and calculate discount on cart.
     */
    public function evaluate(float $subtotal, float $shipping = 0.0): CouponResult
    {
        if (!$this->isActive) {
            return new CouponResult(false, 0.0, 0.0, $subtotal + $shipping, 'This coupon is currently inactive.', $this);
        }

        if (!$this->isStarted()) {
            return new CouponResult(false, 0.0, 0.0, $subtotal + $shipping, 'This coupon promotion has not started yet.', $this);
        }

        if ($this->isExpired()) {
            return new CouponResult(false, 0.0, 0.0, $subtotal + $shipping, 'This coupon has expired.', $this);
        }

        if ($this->hasReachedUsageLimit()) {
            return new CouponResult(false, 0.0, 0.0, $subtotal + $shipping, 'This coupon has reached its maximum usage limit.', $this);
        }

        if ($subtotal < $this->minOrderAmount) {
            $diff = number_format($this->minOrderAmount - $subtotal, 2);
            return new CouponResult(
                false,
                0.0,
                0.0,
                $subtotal + $shipping,
                "Minimum cart order amount of {$this->minOrderAmount} required (add {$diff} more).",
                $this
            );
        }

        $discount = 0.0;
        $shippingDiscount = 0.0;

        switch ($this->type) {
            case 'percentage':
                $discount = ($subtotal * $this->value) / 100.0;
                if ($this->maxDiscount !== null && $discount > $this->maxDiscount) {
                    $discount = $this->maxDiscount;
                }
                break;

            case 'fixed':
                $discount = min($this->value, $subtotal);
                break;

            case 'free_shipping':
                $shippingDiscount = $shipping;
                break;
        }

        $newTotal = max(0.0, ($subtotal - $discount) + ($shipping - $shippingDiscount));

        return new CouponResult(
            true,
            round($discount, 2),
            round($shippingDiscount, 2),
            round($newTotal, 2),
            'Coupon applied successfully.',
            $this
        );
    }

    /**
     * Boot registered coupons from configuration.
     */
    public static function boot(): void
    {
        if (!self::$booted) {
            self::$booted = true;
            $items = config('coupon.coupons', []);
            foreach ($items as $code => $data) {
                $coupon = new self($code, $data['type'] ?? 'fixed', (float) ($data['value'] ?? 0.0));
                if (isset($data['min_order_amount'])) {
                    $coupon->minOrder((float) $data['min_order_amount']);
                }
                if (isset($data['max_discount'])) {
                    $coupon->maxDiscount((float) $data['max_discount']);
                }
                if (isset($data['is_active'])) {
                    $coupon->active((bool) $data['is_active']);
                }
                self::register($coupon);
            }
        }
    }

    public static function register(Coupon $coupon): void
    {
        self::$registry[$coupon->getCode()] = $coupon;
    }

    public static function find(string $code): ?Coupon
    {
        self::boot();
        $code = strtoupper(trim($code));
        return self::$registry[$code] ?? null;
    }

    public static function check(string $code, float $subtotal, float $shipping = 0.0): CouponResult
    {
        $coupon = self::find($code);
        if (!$coupon) {
            return new CouponResult(false, 0.0, 0.0, $subtotal + $shipping, "Coupon '{$code}' not found.");
        }

        return $coupon->evaluate($subtotal, $shipping);
    }

    public static function all(): array
    {
        self::boot();
        return self::$registry;
    }

    public static function fake(array $coupons = []): void
    {
        self::clearFake();
        self::$booted = true;
        foreach ($coupons as $c) {
            if ($c instanceof Coupon) {
                self::register($c);
            }
        }
    }

    public static function clearFake(): void
    {
        self::$registry = [];
        self::$booted = false;
    }
}
