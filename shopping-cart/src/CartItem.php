<?php

declare(strict_types=1);

namespace Bongo\Packages\ShoppingCart;

use JsonSerializable;

class CartItem implements JsonSerializable
{
    protected string $rowId;
    protected string|int $id;
    protected string $name;
    protected float $price;
    protected int $quantity;
    protected array $options;
    protected float $taxRate;
    protected ?string $associatedModel;

    public function __construct(
        string|int $id,
        string $name,
        float $price,
        int $quantity = 1,
        array $options = [],
        float $taxRate = 0.0,
        ?string $associatedModel = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->price = max(0.0, (float) $price);
        $this->quantity = max(1, $quantity);
        $this->options = $options;
        $this->taxRate = max(0.0, (float) $taxRate);
        $this->associatedModel = $associatedModel;
        $this->rowId = $this->generateRowId();
    }

    public function generateRowId(): string
    {
        ksort($this->options);
        return md5((string) $this->id . serialize($this->options));
    }

    public function rowId(): string
    {
        return $this->rowId;
    }

    public function id(): string|int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): float
    {
        return $this->price;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function options(): array
    {
        return $this->options;
    }

    public function taxRate(): float
    {
        return $this->taxRate;
    }

    public function associatedModel(): ?string
    {
        return $this->associatedModel;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = max(1, $quantity);
        return $this;
    }

    public function setPrice(float $price): static
    {
        $this->price = max(0.0, $price);
        return $this;
    }

    public function setTaxRate(float $taxRate): static
    {
        $this->taxRate = max(0.0, $taxRate);
        return $this;
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;
        $this->rowId = $this->generateRowId();
        return $this;
    }

    /**
     * Subtotal before tax for this item.
     */
    public function subtotal(): float
    {
        return round($this->price * $this->quantity, 2);
    }

    /**
     * Tax amount for this item.
     */
    public function tax(): float
    {
        if ($this->taxRate <= 0.0) {
            return 0.0;
        }

        return round($this->subtotal() * ($this->taxRate / 100.0), 2);
    }

    /**
     * Total including tax for this item.
     */
    public function total(): float
    {
        return round($this->subtotal() + $this->tax(), 2);
    }

    public function toArray(): array
    {
        return [
            'rowId' => $this->rowId,
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'options' => $this->options,
            'taxRate' => $this->taxRate,
            'subtotal' => $this->subtotal(),
            'tax' => $this->tax(),
            'total' => $this->total(),
            'associatedModel' => $this->associatedModel,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
