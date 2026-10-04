<div class="nagad-card" style="border: 1px solid #fed7aa; border-radius: 8px; padding: 24px; max-width: 480px; margin: 20px auto; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(247, 148, 29, 0.1);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h3 style="margin: 0; font-size: 1.25rem; color: #c2410c; font-weight: 700;">{{ __('nagad::Pay with Nagad') }}</h3>
        <span style="background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; padding: 4px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 700;">
            {{ $currency ?? 'BDT' }} {{ number_format((float) ($amount ?? 0), 2) }}
        </span>
    </div>

    <p style="color: #64748b; font-size: 0.875rem; line-height: 1.5; margin-bottom: 24px;">
        {{ __('nagad::You will be redirected securely to the Nagad payment gateway to enter your Nagad account number and complete verification.') }}
    </p>

    <form action="{{ $actionUrl ?? '/nagad/pay' }}" method="POST">
        @csrf
        <input type="hidden" name="order_id" value="{{ $orderId ?? '' }}">
        <input type="hidden" name="amount" value="{{ $amount ?? '' }}">
        <input type="hidden" name="currency" value="{{ $currency ?? 'BDT' }}">

        <button type="submit" style="width: 100%; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); color: #ffffff; border: none; padding: 12px 20px; border-radius: 6px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: opacity 0.2s; box-shadow: 0 2px 4px rgba(234, 88, 12, 0.25);">
            {{ __('nagad::Proceed to Nagad Payment') }}
        </button>
    </form>
</div>
