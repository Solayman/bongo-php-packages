<div class="bkash-cancel-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 32px; max-width: 520px; margin: 40px auto; background: #ffffff; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="width: 56px; height: 56px; background: #f1f5f9; color: #64748b; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
        –
    </div>

    <h2 style="color: #334155; margin: 0 0 8px; font-size: 1.5rem;">{{ __('bkash::Payment Cancelled') }}</h2>
    <p style="color: #64748b; margin: 0 0 24px; font-size: 0.95rem;">
        {{ __('bkash::You cancelled the bKash payment process. No money was deducted from your bKash wallet.') }}
    </p>

    @if (!empty($payment_id))
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 24px; font-size: 0.875rem; color: #64748b;">
        <span>{{ __('bkash::Payment ID') }}: <strong>{{ $payment_id }}</strong></span>
    </div>
    @endif

    <div style="display: flex; gap: 12px; justify-content: center;">
        <a href="{{ $retryUrl ?? '/cart' }}" style="display: inline-block; background: #e2136e; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; font-size: 0.95rem;">
            {{ __('bkash::Resume Checkout') }}
        </a>
        <a href="{{ $homeUrl ?? '/' }}" style="display: inline-block; background: #f1f5f9; color: #334155; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 0.95rem;">
            {{ __('bkash::Return to Store') }}
        </a>
    </div>
</div>
