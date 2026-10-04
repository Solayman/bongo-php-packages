<form method="POST" action="<?= htmlspecialchars($action ?? '/paypal/checkout', ENT_QUOTES, 'UTF-8') ?>" class="paypal-checkout-form" style="display: inline-block;">
    <?php if (function_exists('csrf_token')): ?>
        <input type="hidden" name="_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <input type="hidden" name="amount" value="<?= htmlspecialchars((string) ($amount ?? '10.00'), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="currency" value="<?= htmlspecialchars((string) ($currency ?? 'USD'), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="description" value="<?= htmlspecialchars((string) ($description ?? 'Order Payment'), ENT_QUOTES, 'UTF-8') ?>">
    <?php if (!empty($reference_id)): ?>
        <input type="hidden" name="reference_id" value="<?= htmlspecialchars((string) $reference_id, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <button type="submit" style="background-color: #ffc439; color: #111827; border: none; border-radius: 24px; padding: 12px 28px; font-weight: 700; font-size: 15px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: background-color 0.2s, transform 0.1s;">
        <svg style="height: 20px; width: auto;" viewBox="0 0 100 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12.5 4H5.2c-.7 0-1.3.5-1.4 1.2L1 24.3c-.1.5.3 1 .8 1h3.7c.7 0 1.3-.5 1.4-1.2l.8-5.3c.1-.7.7-1.2 1.4-1.2h2.5c5.3 0 8.4-2.6 9.2-7.8.4-2.3 0-4.1-1.2-5.3C18.3 4.5 15.8 4 12.5 4z" fill="#003087"/>
            <path d="M26.5 10.3h-4.2c-.3 0-.5.2-.6.5l-2.4 15.3c-.1.5.3 1 .8 1h3.7c.4 0 .7-.3.8-.7l.7-4.5c.1-.4.4-.7.8-.7h1.5c3.2 0 5.1-1.5 5.6-4.7.2-1.4 0-2.5-.7-3.2-.8-.8-2.3-1.2-4.3-1.2z" fill="#0079C1"/>
        </svg>
        <span><?= htmlspecialchars($label ?? 'Pay with PayPal', ENT_QUOTES, 'UTF-8') ?></span>
    </button>
</form>
