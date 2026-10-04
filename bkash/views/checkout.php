<div class="bkash-card" style="border: 1px solid #fbcfe8; border-radius: 8px; padding: 24px; max-width: 480px; margin: 20px auto; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(226, 19, 110, 0.08);">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h3 style="margin: 0; font-size: 1.25rem; color: #831843; font-weight: 700;">{{ __('bkash::Pay with bKash') }}</h3>
        <span style="background: #fdf2f8; color: #e2136e; border: 1px solid #fbcfe8; padding: 4px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 700;">
            {{ $currency ?? 'BDT' }} {{ number_format((float) ($amount ?? 0), 2) }}
        </span>
    </div>

    <p style="color: #64748b; font-size: 0.875rem; line-height: 1.5; margin-bottom: 24px;">
        {{ __('bkash::You will be redirected securely to the bKash payment gateway to enter your bKash mobile number and complete verification.') }}
    </p>

    <form action="{{ $actionUrl ?? '/bkash/pay' }}" method="POST">
        @csrf
        <input type="hidden" name="invoice_no" value="{{ $invoiceNo ?? '' }}">
        <input type="hidden" name="amount" value="{{ $amount ?? '' }}">
        <input type="hidden" name="currency" value="{{ $currency ?? 'BDT' }}">

        <button type="submit" style="width: 100%; background: #e2136e; color: #ffffff; border: none; padding: 12px 20px; border-radius: 6px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
            {{ __('bkash::Proceed to bKash Payment') }}
        </button>
    </form>
</div>
