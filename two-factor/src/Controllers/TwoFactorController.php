<?php

declare(strict_types=1);

namespace Bongo\Packages\TwoFactor\Controllers;

use Bongo\Packages\TwoFactor\TwoFactor;
use System\Auth\Auth;
use System\Database\DB;
use System\Http\Request;
use Throwable;

class TwoFactorController
{
    /**
     * Show 2FA setup screen with QR code and secret.
     */
    public function setup(): string
    {
        $user = Auth::user();
        $email = $user->email ?? 'user@example.com';

        // Check if existing secret in session or generate new
        $secret = $_SESSION['2fa_setup_secret'] ?? TwoFactor::generateSecret(16);
        $_SESSION['2fa_setup_secret'] = $secret;

        $qrCodeSvg = TwoFactor::qrCodeSvg($email, $secret);

        return view('two-factor::setup', [
            'secret'    => $secret,
            'qrCodeSvg' => $qrCodeSvg,
            'email'     => $email,
        ]);
    }

    /**
     * Confirm 2FA setup by verifying the user's first TOTP code.
     */
    public function confirm(Request $request): string
    {
        $code = (string) $request->input('code', '');
        $secret = (string) ($_SESSION['2fa_setup_secret'] ?? '');

        if ($secret === '' || !TwoFactor::verify($secret, $code)) {
            flash('error', __('two-factor::Invalid authentication code. Please try again.'));
            return $this->setup();
        }

        $user = Auth::user();
        $userId = $user->id ?? 1;

        // Generate recovery backup codes
        $recoveryCodes = TwoFactor::generateRecoveryCodes(8);
        $hashedCodes = array_map([TwoFactor::class, 'hashRecoveryCode'], $recoveryCodes);

        if (class_exists(DB::class)) {
            try {
                // Upsert two_factor_auth record
                $existing = DB::table('two_factor_auth')->where('user_id', $userId)->first();
                $data = [
                    'secret'         => $secret,
                    'recovery_codes' => json_encode($hashedCodes),
                    'confirmed_at'   => date('Y-m-d H:i:s'),
                    'updated_at'     => date('Y-m-d H:i:s'),
                ];
                if ($existing) {
                    DB::table('two_factor_auth')->where('user_id', $userId)->update($data);
                } else {
                    $data['user_id'] = $userId;
                    $data['created_at'] = date('Y-m-d H:i:s');
                    DB::table('two_factor_auth')->insert($data);
                }
            } catch (Throwable $e) {
                // DB error handling
            }
        }

        unset($_SESSION['2fa_setup_secret']);
        $_SESSION['2fa_verified'] = true;

        flash('success', __('two-factor::Two-factor authentication has been activated.'));

        return view('two-factor::recovery', [
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    /**
     * Display 2FA challenge during login.
     */
    public function challenge(): string
    {
        return view('two-factor::challenge');
    }

    /**
     * Verify TOTP code or recovery code.
     */
    public function verify(Request $request): mixed
    {
        $code = (string) $request->input('code', '');
        $recoveryCode = (string) $request->input('recovery_code', '');

        $user = Auth::user();
        $userId = $user->id ?? 1;

        $secret = '';
        $hashedCodes = [];

        if (class_exists(DB::class)) {
            try {
                $record = DB::table('two_factor_auth')
                    ->where('user_id', $userId)
                    ->whereNotNull('confirmed_at')
                    ->first();
                if ($record) {
                    $secret = (string) $record->secret;
                    $hashedCodes = json_decode((string) $record->recovery_codes, true) ?: [];
                }
            } catch (Throwable $e) {
                // Continue
            }
        }

        // 1. Try TOTP code
        if ($code !== '' && $secret !== '' && TwoFactor::verify($secret, $code)) {
            $_SESSION['2fa_verified'] = true;
            $redirect = function_exists('config') ? config('two_factor.redirect_after_verification', '/') : '/';
            return redirect($redirect);
        }

        // 2. Try recovery code
        if ($recoveryCode !== '' && !empty($hashedCodes)) {
            if (TwoFactor::verifyRecoveryCode($recoveryCode, $hashedCodes)) {
                // Save updated recovery codes (consumed one code)
                if (class_exists(DB::class)) {
                    DB::table('two_factor_auth')
                        ->where('user_id', $userId)
                        ->update([
                            'recovery_codes' => json_encode($hashedCodes),
                            'updated_at'     => date('Y-m-d H:i:s'),
                        ]);
                }

                $_SESSION['2fa_verified'] = true;
                flash('warning', 'You used a backup recovery code. Keep your remaining codes safe.');
                $redirect = function_exists('config') ? config('two_factor.redirect_after_verification', '/') : '/';
                return redirect($redirect);
            }
        }

        flash('error', __('two-factor::Invalid authentication code. Please try again.'));
        return view('two-factor::challenge');
    }

    /**
     * Disable 2FA for the current user.
     */
    public function disable(): mixed
    {
        $user = Auth::user();
        $userId = $user->id ?? 1;

        if (class_exists(DB::class)) {
            try {
                DB::table('two_factor_auth')->where('user_id', $userId)->delete();
            } catch (Throwable $e) {
                // Continue
            }
        }

        unset($_SESSION['2fa_verified']);
        flash('info', 'Two-factor authentication has been disabled.');
        return redirect('/');
    }
}
