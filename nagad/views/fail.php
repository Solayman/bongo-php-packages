<div class="nagad-fail-card" style="border: 1px solid #fecaca; border-radius: 8px; padding: 32px; max-width: 520px; margin: 40px auto; background: #ffffff; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="width: 56px; height: 56px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
        ✕
    </div>

    <h2 style="color: #b91c1c; margin: 0 0 8px; font-size: 1.5rem;">{{ __('nagad::Payment Failed') }}</h2>
    <p style="color: #64748b; margin: 0 0 24px; font-size: 0.95rem;">
        {{ $message ?? __('nagad::We could not verify your Nagad payment. Please try again or use another payment method.') }}
    </p>

    <a href="{{ $retryUrl ?? '/checkout' }}" style="display: inline-block; background: #dc2626; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 700; font-size: 0.95rem;">
        {{ __('nagad::Try Again') }}
    </a>
</div>
