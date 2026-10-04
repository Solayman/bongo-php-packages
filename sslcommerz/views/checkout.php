<div class="sslcommerz-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; max-width: 480px; margin: 20px auto; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h3 style="margin: 0; font-size: 1.25rem; color: #1e293b;">{{ __('sslcommerz::Pay with SSLCommerz') }}</h3>
        <span style="background: #e0f2fe; color: #0284c7; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">
            {{ $currency ?? 'BDT' }} {{ number_format((float) ($amount ?? 0), 2) }}
        </span>
    </div>

    <p style="color: #64748b; font-size: 0.875rem; line-height: 1.5; margin-bottom: 24px;">
        {{ __('sslcommerz::You will be redirected securely to SSLCommerz to complete your payment with cards, mobile banking, or net banking.') }}
    </p>

    <form action="{{ $actionUrl ?? '/sslcommerz/pay' }}" method="POST">
        @csrf
        <input type="hidden" name="tran_id" value="{{ $tranId ?? '' }}">
        <input type="hidden" name="amount" value="{{ $amount ?? '' }}">
        <input type="hidden" name="currency" value="{{ $currency ?? 'BDT' }}">

        <button type="submit" style="width: 100%; background: #0284c7; color: #ffffff; border: none; padding: 12px 20px; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
            {{ __('sslcommerz::Proceed to Payment') }}
        </button>
    </form>
</div>
