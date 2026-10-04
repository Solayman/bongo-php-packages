<div class="sslcommerz-fail-card" style="border: 1px solid #fecaca; border-radius: 8px; padding: 32px; max-width: 520px; margin: 40px auto; background: #ffffff; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="width: 56px; height: 56px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
        ✕
    </div>

    <h2 style="color: #b91c1c; margin: 0 0 8px; font-size: 1.5rem;">{{ __('sslcommerz::Payment Failed') }}</h2>
    <p style="color: #64748b; margin: 0 0 24px; font-size: 0.95rem;">
        {{ __('sslcommerz::Your payment could not be processed. No charges were made to your account.') }}
    </p>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin-bottom: 24px; text-align: left; font-size: 0.875rem;">
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #64748b;">{{ __('sslcommerz::Transaction ID') }}:</span>
            <span style="font-weight: 600; color: #1e293b;">{{ $tran_id ?? '-' }}</span>
        </div>
    </div>

    <a href="{{ $retryUrl ?? '/shop/checkout' }}" style="display: inline-block; background: #dc2626; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 0.95rem;">
        {{ __('sslcommerz::Try Again') }}
    </a>
</div>
