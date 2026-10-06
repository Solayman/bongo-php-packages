<?php

declare(strict_types=1);

namespace Bongo\Packages\Invoice;

class Invoice
{
    protected string $number;
    protected string $date;
    protected ?string $dueDate = null;
    protected string $status = 'PAID';
    protected array $company = [];
    protected array $customer = [];
    protected string $currency;
    protected string $currencySymbol;
    /** @var InvoiceItem[] */
    protected array $items = [];
    protected float $taxRate = 0.0;
    protected float $discountAmount = 0.0;
    protected float $shippingAmount = 0.0;
    protected string $notes = '';

    public function __construct(?string $number = null)
    {
        $this->number = $number ?? 'INV-' . date('Ymd') . '-' . rand(100, 999);
        $this->date = date('Y-m-d');
        $this->currency = config('invoice.currency', 'BDT');
        $this->currencySymbol = config('invoice.currency_symbol', '৳');
        $this->company = config('invoice.company', [
            'name' => 'Bongo Store',
            'address' => 'Dhaka, Bangladesh',
            'phone' => '+880 1700-000000',
            'email' => 'billing@example.com',
        ]);
        $this->taxRate = (float) config('invoice.tax_rate', 0.0);
    }

    public static function make(?string $number = null): self
    {
        return new static($number);
    }

    public function number(string $number): self
    {
        $this->number = $number;
        return $this;
    }

    public function date(string $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function dueDate(string $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function status(string $status): self
    {
        $this->status = strtoupper($status);
        return $this;
    }

    public function company(array $info): self
    {
        $this->company = array_merge($this->company, $info);
        return $this;
    }

    public function customer(array $info): self
    {
        $this->customer = array_merge($this->customer, $info);
        return $this;
    }

    public function currency(string $code, string $symbol = ''): self
    {
        $this->currency = $code;
        if ($symbol !== '') {
            $this->currencySymbol = $symbol;
        }
        return $this;
    }

    public function addItem(string $title, float $price, int $quantity = 1, ?string $description = null): self
    {
        $this->items[] = new InvoiceItem($title, $price, $quantity, $description);
        return $this;
    }

    public function tax(float $percentage): self
    {
        $this->taxRate = $percentage;
        return $this;
    }

    public function discount(float $amount): self
    {
        $this->discountAmount = $amount;
        return $this;
    }

    public function shipping(float $amount): self
    {
        $this->shippingAmount = $amount;
        return $this;
    }

    public function notes(string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getSubtotal(): float
    {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            $subtotal += $item->subtotal();
        }
        return $subtotal;
    }

    public function getTaxAmount(): float
    {
        return ($this->getSubtotal() * $this->taxRate) / 100;
    }

    public function getTotal(): float
    {
        $total = $this->getSubtotal() + $this->getTaxAmount() + $this->shippingAmount - $this->discountAmount;
        return max(0.0, $total);
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getDueDate(): ?string
    {
        return $this->dueDate;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCompany(): array
    {
        return $this->company;
    }

    public function getCustomer(): array
    {
        return $this->customer;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCurrencySymbol(): string
    {
        return $this->currencySymbol;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function getDiscountAmount(): float
    {
        return $this->discountAmount;
    }

    public function getShippingAmount(): float
    {
        return $this->shippingAmount;
    }

    public function getNotes(): string
    {
        return $this->notes;
    }

    /**
     * Render responsive A4 document with print CSS.
     */
    public function render(string $layout = 'a4'): string
    {
        $num = htmlspecialchars($this->number, ENT_QUOTES, 'UTF-8');
        $dt  = htmlspecialchars($this->date, ENT_QUOTES, 'UTF-8');
        $due = $this->dueDate ? htmlspecialchars($this->dueDate, ENT_QUOTES, 'UTF-8') : null;
        $st  = htmlspecialchars($this->status, ENT_QUOTES, 'UTF-8');

        $cName  = htmlspecialchars($this->company['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $cAddr  = nl2br(htmlspecialchars($this->company['address'] ?? '', ENT_QUOTES, 'UTF-8'));
        $cPhone = htmlspecialchars($this->company['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $cEmail = htmlspecialchars($this->company['email'] ?? '', ENT_QUOTES, 'UTF-8');

        $uName  = htmlspecialchars($this->customer['name'] ?? 'Guest Customer', ENT_QUOTES, 'UTF-8');
        $uAddr  = nl2br(htmlspecialchars($this->customer['address'] ?? '', ENT_QUOTES, 'UTF-8'));
        $uPhone = htmlspecialchars($this->customer['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $uEmail = htmlspecialchars($this->customer['email'] ?? '', ENT_QUOTES, 'UTF-8');

        $cur = htmlspecialchars($this->currencySymbol, ENT_QUOTES, 'UTF-8');

        $itemsRows = '';
        foreach ($this->items as $idx => $it) {
            $iNum   = $idx + 1;
            $iTitle = htmlspecialchars($it->title, ENT_QUOTES, 'UTF-8');
            $iDesc  = $it->description ? '<div style="font-size:12px;color:#64748b;">' . htmlspecialchars($it->description, ENT_QUOTES, 'UTF-8') . '</div>' : '';
            $iPrice = number_format($it->price, 2);
            $iQty   = $it->quantity;
            $iSub   = number_format($it->subtotal(), 2);

            $itemsRows .= <<<ROW
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 12px 8px; text-align: center; color: #64748b;">{$iNum}</td>
                <td style="padding: 12px 8px;"><strong>{$iTitle}</strong>{$iDesc}</td>
                <td style="padding: 12px 8px; text-align: right;">{$cur}{$iPrice}</td>
                <td style="padding: 12px 8px; text-align: center;">{$iQty}</td>
                <td style="padding: 12px 8px; text-align: right; font-weight: 600;">{$cur}{$iSub}</td>
            </tr>
ROW;
        }

        $subtotalFormatted = number_format($this->getSubtotal(), 2);
        $taxFormatted      = number_format($this->getTaxAmount(), 2);
        $shippingFormatted = number_format($this->shippingAmount, 2);
        $discountFormatted = number_format($this->discountAmount, 2);
        $totalFormatted    = number_format($this->getTotal(), 2);

        $notesBlock = $this->notes ? '<div style="margin-top:24px;padding:12px 16px;background:#f8fafc;border-left:4px solid #94a3b8;font-size:13px;color:#475569;"><strong>Notes:</strong> ' . htmlspecialchars($this->notes, ENT_QUOTES, 'UTF-8') . '</div>' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {$num}</title>
    <style>
        @page { size: A4; margin: 15mm; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #1e293b; margin: 0; padding: 24px; background: #fff; line-height: 1.5; font-size: 14px; }
        .invoice-card { max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 20px; margin-bottom: 24px; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 700; background: #dcfce7; color: #15803d; }
        .grid-info { display: flex; justify-content: space-between; margin-bottom: 28px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th { background: #f8fafc; padding: 12px 8px; font-weight: 600; text-align: left; border-bottom: 2px solid #cbd5e1; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="invoice-card">
        <div class="header">
            <div>
                <h1 style="margin: 0 0 6px 0; font-size: 26px; color: #0f172a;">INVOICE</h1>
                <div style="font-size: 14px; color: #64748b;"><strong>#{$num}</strong></div>
            </div>
            <div style="text-align: right;">
                <span class="status-badge">{$st}</span>
                <div style="margin-top: 8px; font-size: 13px; color: #64748b;">Date: <strong>{$dt}</strong></div>
            </div>
        </div>

        <div class="grid-info">
            <div style="max-width: 320px;">
                <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px;">Billed From</div>
                <div style="font-size: 16px; font-weight: 700; color: #0f172a;">{$cName}</div>
                <div style="color: #475569; margin-top: 4px;">{$cAddr}</div>
                <div style="color: #475569; margin-top: 4px;">{$cPhone} | {$cEmail}</div>
            </div>
            <div style="max-width: 320px; text-align: right;">
                <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 6px;">Billed To</div>
                <div style="font-size: 16px; font-weight: 700; color: #0f172a;">{$uName}</div>
                <div style="color: #475569; margin-top: 4px;">{$uAddr}</div>
                <div style="color: #475569; margin-top: 4px;">{$uPhone} {$uEmail}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;">#</th>
                    <th>Item Description</th>
                    <th style="width: 110px; text-align: right;">Rate</th>
                    <th style="width: 60px; text-align: center;">Qty</th>
                    <th style="width: 120px; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                {$itemsRows}
            </tbody>
        </table>

        <div style="display: flex; justify-content: flex-end;">
            <div style="width: 280px; font-size: 14px;">
                <div style="display: flex; justify-content: space-between; padding: 6px 0; color: #64748b;">
                    <span>Subtotal:</span>
                    <span>{$cur}{$subtotalFormatted}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; color: #64748b;">
                    <span>Tax / VAT ({$this->taxRate}%):</span>
                    <span>{$cur}{$taxFormatted}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 6px 0; color: #64748b;">
                    <span>Shipping:</span>
                    <span>{$cur}{$shippingFormatted}</span>
                </div>
                <div style="display: flex; justify-content: space-between; padding: 8px 0; border-top: 2px solid #0f172a; font-size: 18px; font-weight: 700; color: #0f172a;">
                    <span>Total Due:</span>
                    <span>{$cur}{$totalFormatted}</span>
                </div>
            </div>
        </div>

        {$notesBlock}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Render compact 80mm or 58mm thermal receipt layout for POS printers.
     */
    public function renderPos(int $widthMm = 80): string
    {
        $num = htmlspecialchars($this->number, ENT_QUOTES, 'UTF-8');
        $dt  = htmlspecialchars($this->date, ENT_QUOTES, 'UTF-8');
        $cName = htmlspecialchars($this->company['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $cPhone = htmlspecialchars($this->company['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $cur = htmlspecialchars($this->currencySymbol, ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($this->items as $it) {
            $iTitle = htmlspecialchars($it->title, ENT_QUOTES, 'UTF-8');
            $iQty   = $it->quantity;
            $iSub   = number_format($it->subtotal(), 2);
            $rows .= "<div style=\"display:flex;justify-content:space-between;margin:4px 0;\"><span>{$iTitle} x{$iQty}</span><span>{$cur}{$iSub}</span></div>";
        }

        $totalFormatted = number_format($this->getTotal(), 2);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>POS Receipt {$num}</title>
    <style>
        body { font-family: monospace; width: {$widthMm}mm; margin: 0 auto; padding: 8px; font-size: 12px; }
        .text-center { text-align: center; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        @media print { body { width: 100%; margin: 0; } }
    </style>
</head>
<body onload="window.print()">
    <div class="text-center">
        <h3 style="margin:0 0 4px 0;">{$cName}</h3>
        <div>{$cPhone}</div>
        <div>Receipt #{$num}</div>
        <div>{$dt}</div>
    </div>
    <div class="divider"></div>
    {$rows}
    <div class="divider"></div>
    <div style="display:flex;justify-content:space-between;font-weight:bold;font-size:14px;">
        <span>TOTAL:</span>
        <span>{$cur}{$totalFormatted}</span>
    </div>
    <div class="divider"></div>
    <div class="text-center" style="font-size:11px;margin-top:8px;">Thank you for shopping with us!</div>
</body>
</html>
HTML;
    }
}
