<div class="otp-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 32px; max-width: 440px; margin: 40px auto; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="width: 52px; height: 52px; background: #eff6ff; color: #2563eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 12px;">
            📱
        </div>
        <h2 style="margin: 0 0 8px; font-size: 1.5rem; color: #1e293b;">{{ __('sms::Sign in with Phone') }}</h2>
        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
            {{ __('sms::Enter your mobile number and we will send you a verification code.') }}
        </p>
    </div>

    @if (\System\Http\Session::has('error'))
    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 6px; font-size: 0.875rem; margin-bottom: 20px;">
        {{ \System\Http\Session::get('error') }}
    </div>
    @endif

    <form action="{{ $actionUrl ?? '/otp/send' }}" method="POST">
        @csrf
        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 0.875rem; font-weight: 600; color: #334155; margin-bottom: 6px;">
                {{ __('sms::Mobile Number') }}
            </label>
            <input type="tel" name="phone" value="{{ $phone ?? '' }}" placeholder="01700000000" required autofocus
                style="width: 100%; box-sizing: border-box; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem; color: #1e293b; outline: none; transition: border-color 0.2s;">
        </div>

        <button type="submit" style="width: 100%; background: #2563eb; color: #ffffff; border: none; padding: 12px; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
            {{ __('sms::Send Verification Code') }}
        </button>
    </form>
</div>
