<div class="sslcommerz-cancel-card" style="border: 1px solid #fed7aa; border-radius: 8px; padding: 32px; max-width: 520px; margin: 40px auto; background: #ffffff; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="width: 56px; height: 56px; background: #ffedd5; color: #ea580c; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
        !
    </div>

    <h2 style="color: #c2410c; margin: 0 0 8px; font-size: 1.5rem;">{{ __('sslcommerz::Payment Cancelled') }}</h2>
    <p style="color: #64748b; margin: 0 0 24px; font-size: 0.95rem;">
        {{ __('sslcommerz::You cancelled the transaction before completing the payment.') }}
    </p>

    <a href="{{ $cartUrl ?? '/shop/cart' }}" style="display: inline-block; background: #ea580c; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 0.95rem;">
        {{ __('sslcommerz::Return to Cart') }}
    </a>
</div>
