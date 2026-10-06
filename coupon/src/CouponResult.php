<?php

declare(strict_types=1);

namespace Bongo\Packages\Coupon;

class CouponResult
{
    public function __construct(
        public bool $valid,
        public float $discount = 0.0,
        public float $shippingDiscount = 0.0,
        public float $newTotal = 0.0,
        public string $message = '',
        public ?Coupon $coupon = null
    ) {
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function getShippingDiscount(): float
    {
        return $this->shippingDiscount;
    }

    public function getTotalDiscount(): float
    {
        return $this->discount + $this->shippingDiscount;
    }

    public function getNewTotal(): float
    {
        return $this->newTotal;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        return [
            'valid'             => $this->valid,
            'discount'          => $this->discount,
            'shipping_discount' => $this->shippingDiscount,
            'total_discount'    => $this->getTotalDiscount(),
            'new_total'         => $this->newTotal,
            'message'           => $this->message,
            'code'              => $this->coupon?->getCode(),
        ];
    }
}
