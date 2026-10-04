<div class="otp-card" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 32px; max-width: 440px; margin: 40px auto; background: #ffffff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="width: 52px; height: 52px; background: #f0fdf4; color: #16a34a; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 12px;">
            🔒
        </div>
        <h2 style="margin: 0 0 8px; font-size: 1.5rem; color: #1e293b;">{{ __('sms::Verify Code') }}</h2>
        <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
            {{ __('sms::Enter the 6-digit code sent to') }} <strong>{{ $phone ?? '' }}</strong>
            <a href="/otp/login" style="color: #2563eb; text-decoration: none; margin-left: 4px; font-size: 0.85rem;">({{ __('sms::Change') }})</a>
        </p>
    </div>

    @if (\System\Http\Session::has('error'))
    <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 6px; font-size: 0.875rem; margin-bottom: 20px;">
        {{ \System\Http\Session::get('error') }}
    </div>
    @endif

    <form action="{{ $actionUrl ?? '/otp/verify' }}" method="POST">
        @csrf
        <input type="hidden" name="phone" value="{{ $phone ?? '' }}">

        <div style="margin-bottom: 24px; text-align: center;">
            <input type="text" name="code" maxlength="8" placeholder="123456" required autofocus autocomplete="one-time-code"
                style="width: 100%; box-sizing: border-box; text-align: center; letter-spacing: 6px; font-size: 1.75rem; font-weight: 700; padding: 12px; border: 2px solid #cbd5e1; border-radius: 6px; color: #1e293b; outline: none;">
        </div>

        <button type="submit" style="width: 100%; background: #16a34a; color: #ffffff; border: none; padding: 12px; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
            {{ __('sms::Verify & Sign in') }}
        </button>
    </form>

    <div style="margin-top: 24px; text-align: center; font-size: 0.875rem; color: #64748b;">
        <span>{{ __('sms::Didn\'t receive the code?') }}</span>
        <form action="/otp/send" method="POST" style="display: inline;">
            @csrf
            <input type="hidden" name="phone" value="{{ $phone ?? '' }}">
            <button type="submit" style="background: none; border: none; color: #2563eb; cursor: pointer; font-weight: 600; padding: 0 4px; font-size: 0.875rem;">
                {{ __('sms::Resend Code') }}
            </button>
        </form>
    </div>
</div>
