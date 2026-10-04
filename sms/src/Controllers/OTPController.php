<?php

namespace Bongo\Packages\Sms\Controllers;

use Bongo\Packages\Sms\OTP;
use System\Auth\Auth;
use System\Database\DB;
use System\Http\Request;
use System\Http\Session;
use Throwable;

class OTPController
{
    /**
     * Show initial phone input form for OTP login.
     */
    public function showLoginForm(): string
    {
        return view('sms::login');
    }

    /**
     * Send OTP code to the requested phone number.
     */
    public function sendOtp(Request $request): string
    {
        $phone = (string) $request->get('phone', '');
        $clean = OTP::normalizePhone($phone);

        if (!preg_match('/^01[3-9]\d{8}$/', $clean)) {
            Session::flash('error', function_exists('__')
                ? __('sms::Please enter a valid 11-digit Bangladeshi mobile number.')
                : 'Please enter a valid 11-digit Bangladeshi mobile number.');
            return view('sms::login', ['phone' => $phone]);
        }

        try {
            $result = OTP::send($clean, 'login');

            if (!$result['success']) {
                Session::flash('error', $result['error'] ?? 'Failed to send verification code.');
                return view('sms::login', ['phone' => $phone]);
            }

            Session::put('otp_phone', $clean);
            Session::put('otp_resend_until', time() + ($result['resend_after'] ?? 60));

            return view('sms::verify', [
                'phone'        => $clean,
                'resend_after' => $result['resend_after'] ?? 60,
            ]);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
            return view('sms::login', ['phone' => $phone]);
        }
    }

    /**
     * Show code verification screen.
     */
    public function showVerifyForm(): string
    {
        $phone = (string) Session::get('otp_phone', '');
        if ($phone === '') {
            return view('sms::login');
        }

        $resendUntil = (int) Session::get('otp_resend_until', 0);
        $resendAfter = max(0, $resendUntil - time());

        return view('sms::verify', [
            'phone'        => $phone,
            'resend_after' => $resendAfter,
        ]);
    }

    /**
     * Verify code and authenticate the user.
     */
    public function verifyOtp(Request $request): string
    {
        $phone = (string) $request->get('phone', Session::get('otp_phone', ''));
        $clean = OTP::normalizePhone($phone);
        $code = (string) $request->get('code', '');

        if ($clean === '' || $code === '') {
            Session::flash('error', function_exists('__')
                ? __('sms::Phone number and verification code are required.')
                : 'Phone number and verification code are required.');
            return view('sms::verify', ['phone' => $clean]);
        }

        $isValid = OTP::verify($clean, $code, 'login');

        if (!$isValid) {
            Session::flash('error', function_exists('__')
                ? __('sms::Invalid or expired verification code.')
                : 'Invalid or expired verification code.');
            return view('sms::verify', ['phone' => $clean]);
        }

        // Successfully verified! Find or create user
        $user = $this->findOrCreateUser($clean);

        if ($user) {
            if (class_exists(Auth::class)) {
                // If customer guard exists, authenticate customer
                try {
                    Auth::guard('customer')->login($user);
                } catch (Throwable $e) {
                    Auth::login($user);
                }
            }
        }

        Session::forget('otp_phone');
        Session::forget('otp_resend_until');

        // Redirect to intended or home
        $redirectUrl = (string) Session::get('_previous_url', '/');
        if (function_exists('redirect')) {
            return redirect($redirectUrl);
        }

        return view('sms::success', ['phone' => $clean]);
    }

    protected function findOrCreateUser(string $phone): ?object
    {
        try {
            $user = DB::table('users')->where('phone', $phone)->first();
            if ($user) {
                return $user;
            }

            // If user doesn't exist, create customer account
            $userId = DB::table('users')->insertGetId([
                'name'       => 'User ' . substr($phone, -4),
                'email'      => $phone . '@phone.local',
                'phone'      => $phone,
                'role'       => 'customer',
                'password'   => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return DB::table('users')->where('id', $userId)->first();
        } catch (Throwable $e) {
            return null;
        }
    }
}
