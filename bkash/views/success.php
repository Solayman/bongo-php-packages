<div class="bkash-success-card" style="border: 1px solid #bbf7d0; border-radius: 8px; padding: 32px; max-width: 520px; margin: 40px auto; background: #ffffff; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="width: 56px; height: 56px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
        ✓
    </div>

    <h2 style="color: #15803d; margin: 0 0 8px; font-size: 1.5rem;">{{ __('bkash::Payment Successful') }}</h2>
    <p style="color: #64748b; margin: 0 0 24px; font-size: 0.95rem;">{{ __('bkash::Thank you! Your bKash payment has been verified successfully.') }}</p>

    <div style="background: #fdf2f8; border: 1px solid #fbcfe8; border-radius: 6px; padding: 16px; margin-bottom: 24px; text-align: left; font-size: 0.875rem;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="color: #701a75;">{{ __('bkash::bKash TrxID') }}:</span>
            <span style="font-weight: 700; color: #831843;">{{ $trx_id ?? '-' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="color: #701a75;">{{ __('bkash::Payment ID') }}:</span>
            <span style="font-weight: 600; color: #1e293b;">{{ $payment_id ?? '-' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="color: #701a75;">{{ __('bkash::Amount Paid') }}:</span>
            <span style="font-weight: 700; color: #1e293b;">{{ $currency ?? 'BDT' }} {{ number_format((float) ($amount ?? 0), 2) }}</span>
        </div>
        @if (!empty($customer_msisdn))
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #701a75;">{{ __('bkash::Account Number') }}:</span>
            <span style="font-weight: 600; color: #1e293b;">{{ $customer_msisdn }}</span>
        </div>
        @endif
    </div>

    <a href="{{ $homeUrl ?? '/' }}" style="display: inline-block; background: #e2136e; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 700; font-size: 0.95rem;">
        {{ __('bkash::Return to Store') }}
    </a>
</div>
