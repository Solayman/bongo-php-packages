<?php
/**
 * Stripe Checkout Button Component / Partial
 *
 * Variables:
 * - $amount: (float) e.g. 25.00
 * - $currency: (string) e.g. 'usd'
 * - $name: (string) item/order description
 * - $order_id: (string|int|null)
 * - $action: (string) URL to submit to, defaults to '/stripe/checkout'
 */
$action = $action ?? '/stripe/checkout';
$amount = $amount ?? 0;
$currency = strtoupper((string) ($currency ?? 'usd'));
$name = $name ?? 'Payment';
$order_id = $order_id ?? null;
?>
<form action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" method="POST" class="stripe-checkout-form" style="display: inline-block;">
    <?= csrf_field() ?>
    <input type="hidden" name="amount" value="<?= htmlspecialchars((string) $amount, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="currency" value="<?= htmlspecialchars(strtolower($currency), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="name" value="<?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($order_id !== null): ?>
        <input type="hidden" name="order_id" value="<?= htmlspecialchars((string) $order_id, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <button type="submit" class="stripe-pay-btn" style="background-color: #635bff; color: #ffffff; border: none; border-radius: 6px; padding: 10px 20px; font-size: 15px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(99, 91, 255, 0.2); transition: background-color 0.2s ease, transform 0.1s ease;">
        <svg width="24" height="14" viewBox="0 0 32 14" fill="none" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle;">
            <path d="M5.4 5.9C5.4 5.1 6.1 4.7 7.2 4.7C8.7 4.7 10.4 5.2 11.7 5.9L12.5 1.5C11 0.8 9.2 0.5 7.4 0.5C2.8 0.5 0 2.8 0 6.2C0 11.5 7.2 10.7 7.2 13.1C7.2 14 6.3 14.5 5 14.5C3.3 14.5 1.4 13.9 0 13L0 17.5C1.6 18.2 3.4 18.5 5.2 18.5C9.8 18.5 12.7 16.3 12.7 12.8C12.7 7.1 5.4 8 5.4 5.9Z" fill="#ffffff" transform="scale(0.7) translate(0,-1)"/>
        </svg>
        <span><?= htmlspecialchars(__('stripe::Pay with Stripe'), ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($amount > 0): ?>
            <span style="opacity: 0.9; font-weight: 500;">(<?= htmlspecialchars($currency . ' ' . number_format($amount, 2), ENT_QUOTES, 'UTF-8') ?>)</span>
        <?php endif; ?>
    </button>
</form>
