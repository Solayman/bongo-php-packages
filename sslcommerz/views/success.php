<div class="sslcommerz-success-card" style="border: 1px solid #bbf7d0; border-radius: 8px; padding: 32px; max-width: 520px; margin: 40px auto; background: #ffffff; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="width: 56px; height: 56px; background: #dcfce7; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 16px;">
        ✓
    </div>

    <h2 style="color: #15803d; margin: 0 0 8px; font-size: 1.5rem;">{{ __('sslcommerz::Payment Successful') }}</h2>
    <p style="color: #64748b; margin: 0 0 24px; font-size: 0.95rem;">{{ __('sslcommerz::Thank you! Your payment has been received and verified.') }}</p>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin-bottom: 24px; text-align: left; font-size: 0.875rem;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="color: #64748b;">{{ __('sslcommerz::Transaction ID') }}:</span>
            <span style="font-weight: 600; color: #1e293b;">{{ $tran_id ?? '-' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="color: #64748b;">{{ __('sslcommerz::Amount Paid') }}:</span>
            <span style="font-weight: 600; color: #1e293b;">{{ $currency ?? 'BDT' }} {{ number_format((float) ($amount ?? 0), 2) }}</span>
        </div>
        @if (!empty($bank_tran_id))
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
            <span style="color: #64748b;">{{ __('sslcommerz::Bank Reference') }}:</span>
            <span style="font-weight: 600; color: #1e293b;">{{ $bank_tran_id }}</span>
        </div>
        @endif
        @if (!empty($card_type))
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #64748b;">{{ __('sslcommerz::Payment Method') }}:</span>
            <span style="font-weight: 600; color: #1e293b;">{{ $card_type }}</span>
        </div>
        @endif
    </div>

    <a href="{{ $homeUrl ?? '/' }}" style="display: inline-block; background: #16a34a; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 0.95rem;">
        {{ __('sslcommerz::Return to Store') }}
    </a>
</div>
