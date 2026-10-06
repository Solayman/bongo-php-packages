<?php

declare(strict_types=1);

namespace Bongo\Packages\Invoice;

class InvoiceItem
{
    public function __construct(
        public string $title,
        public float $price,
        public int $quantity = 1,
        public ?string $description = null
    ) {
    }

    public static function make(string $title, float $price, int $quantity = 1, ?string $description = null): self
    {
        return new self($title, $price, $quantity, $description);
    }

    public function subtotal(): float
    {
        return $this->price * $this->quantity;
    }

    public function toArray(): array
    {
        return [
            'title'       => $this->title,
            'price'       => $this->price,
            'quantity'    => $this->quantity,
            'description' => $this->description,
            'subtotal'    => $this->subtotal(),
        ];
    }
}
