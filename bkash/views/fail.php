<div class="bkash-fail-card" style="border: 1px solid #fecaca; border-radius: 8px; padding: 32px; max-width: 520px; margin: 40px auto; background: #ffffff; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="width: 56px; height: 56px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
        ✕
    </div>

    <h2 style="color: #b91c1c; margin: 0 0 8px; font-size: 1.5rem;">{{ __('bkash::Payment Failed') }}</h2>
    <p style="color: #64748b; margin: 0 0 24px; font-size: 0.95rem;">
        {{ $error ?? __('bkash::We could not verify your bKash payment. Please try again or use another payment method.') }}
    </p>

    @if (!empty($payment_id))
    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 12px; margin-bottom: 24px; font-size: 0.875rem; color: #991b1b;">
        <span>{{ __('bkash::Payment ID') }}: <strong>{{ $payment_id }}</strong></span>
    </div>
    @endif

    <div style="display: flex; gap: 12px; justify-content: center;">
        <a href="{{ $retryUrl ?? '/cart' }}" style="display: inline-block; background: #e2136e; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; font-size: 0.95rem;">
            {{ __('bkash::Try Again') }}
        </a>
        <a href="{{ $homeUrl ?? '/' }}" style="display: inline-block; background: #f1f5f9; color: #334155; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 0.95rem;">
            {{ __('bkash::Return to Store') }}
        </a>
    </div>
</div>
