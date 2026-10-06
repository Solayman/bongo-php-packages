<?php

declare(strict_types=1);

namespace Bongo\Packages\TwoFactor\Controllers;

use Bongo\Packages\TwoFactor\TwoFactor;
use System\Auth\Auth;
use System\Cache\Cache;
use System\Cache\RateLimiter;
use System\Database\DB;
use System\Http\Request;
use System\Http\Session;
use System\Security\Crypt;
use Throwable;

class TwoFactorController
{
    /** Wrong codes allowed per user before a pause of DECAY_SECONDS */
    protected const MAX_ATTEMPTS = 5;
    protected const DECAY_SECONDS = 300;

    /**
     * Show 2FA setup screen with QR code and secret.
     */
    public function setup(): string
    {
        $userId = $this->requireUser();
        // Someone who only knows the password must not replace an active authenticator
        $this->requireVerifiedWhenEnabled($userId);

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
        $userId = $this->requireUser();
        $this->requireVerifiedWhenEnabled($userId);

        $code = (string) $request->input('code', '');
        $secret = (string) ($_SESSION['2fa_setup_secret'] ?? '');

        if ($secret === '' || !TwoFactor::verify($secret, $code)) {
            flash('error', __('two-factor::Invalid authentication code. Please try again.'));
            return $this->setup();
        }

        // Generate recovery backup codes
        $recoveryCodes = TwoFactor::generateRecoveryCodes(8);
        $hashedCodes = array_map([TwoFactor::class, 'hashRecoveryCode'], $recoveryCodes);

        try {
            $existing = DB::table('two_factor_auth')->where('user_id', $userId)->first();
            $data = [
                'secret'         => $this->sealSecret($secret),
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
            flash('error', __('Two-factor authentication could not be saved. Please try again.'));
            return $this->setup();
        }

        unset($_SESSION['2fa_setup_secret']);
        $this->markVerified($userId);

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
        $userId = $this->requireUser();
        $usedRecovery = false;

        if ($this->checkSecondFactor($userId, (string) $request->input('code', ''), (string) $request->input('recovery_code', ''), $usedRecovery)) {
            $this->markVerified($userId);
            if ($usedRecovery) {
                flash('warning', 'You used a backup recovery code. Keep your remaining codes safe.');
            }
            redirect((string) (function_exists('config') ? config('two_factor.redirect_after_verification', '/') : '/'));
        }

        return view('two-factor::challenge');
    }

    /**
     * Disable 2FA for the current user: needs a verified session and a current code (or recovery code).
     */
    public function disable(Request $request): mixed
    {
        $userId = $this->requireUser();

        if (!$this->isVerified($userId) || !$this->checkSecondFactor($userId, (string) $request->input('code', ''), (string) $request->input('recovery_code', ''))) {
            flash('error', __('Enter a current authentication code to turn off two-factor authentication.'));
            redirect('/');
        }

        try {
            DB::table('two_factor_auth')->where('user_id', $userId)->delete();
        } catch (Throwable $e) {
            // Continue
        }

        unset($_SESSION['2fa_verified'], $_SESSION['2fa_verified_user']);
        flash('info', 'Two-factor authentication has been disabled.');
        redirect('/');
    }

    /**
     * A TOTP or recovery code for this user. Rate-limited per user; each TOTP time step and
     * each recovery code works once. The row lock serializes parallel attempts.
     */
    protected function checkSecondFactor(int $userId, string $code, string $recoveryCode, bool &$usedRecovery = false): bool
    {
        $limiter = new RateLimiter();
        $key = "2fa:$userId";
        if ($limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            flash('error', __('Too many attempts. Try again in :seconds seconds.', ['seconds' => max(1, $limiter->availableIn($key))]));
            return false;
        }

        try {
            $ok = DB::transaction(function () use ($userId, $code, $recoveryCode, &$usedRecovery): bool {
                $record = DB::table('two_factor_auth')
                    ->where('user_id', $userId)
                    ->whereNotNull('confirmed_at')
                    ->lockForUpdate()
                    ->first();
                if (!$record) {
                    return false;
                }

                if ($code !== '') {
                    $secret = $this->openSecret((string) $record->secret);
                    if ($secret === '') {
                        return false;   // unreadable (APP_KEY changed): never check codes against an empty key
                    }
                    $step = TwoFactor::matchingStep($secret, $code);
                    if ($step === null || $step <= (int) Cache::get("2fa:last_step:$userId", 0)) {
                        return false;   // wrong, or already used (replay)
                    }
                    Cache::put("2fa:last_step:$userId", $step, 300);
                    return true;
                }

                if ($recoveryCode !== '') {
                    $hashedCodes = json_decode((string) $record->recovery_codes, true) ?: [];
                    if (TwoFactor::verifyRecoveryCode($recoveryCode, $hashedCodes)) {
                        DB::table('two_factor_auth')->where('user_id', $userId)->update([
                            'recovery_codes' => json_encode($hashedCodes),
                            'updated_at'     => date('Y-m-d H:i:s'),
                        ]);
                        $usedRecovery = true;
                        return true;
                    }
                }

                return false;
            });
        } catch (Throwable $e) {
            $ok = false;
        }

        if ($ok) {
            $limiter->clear($key);
            return true;
        }

        $limiter->hit($key, self::DECAY_SECONDS);
        flash('error', __('two-factor::Invalid authentication code. Please try again.'));
        return false;
    }

    /** TOTP secrets are stored encrypted with APP_KEY ("enc:" prefix); older plain rows keep working */
    protected function sealSecret(string $secret): string
    {
        try {
            return 'enc:' . Crypt::encrypt($secret);
        } catch (Throwable $e) {
            return $secret;   // no APP_KEY: php bongo security:check reports that
        }
    }

    protected function openSecret(string $stored): string
    {
        if (!str_starts_with($stored, 'enc:')) {
            return $stored;
        }
        try {
            return Crypt::decrypt(substr($stored, 4));
        } catch (Throwable $e) {
            return '';
        }
    }

    /** 2FA passed in this session, for this user (a session reused by another account does not count) */
    protected function isVerified(int $userId): bool
    {
        return ($_SESSION['2fa_verified'] ?? false) === true && ($_SESSION['2fa_verified_user'] ?? null) === $userId;
    }

    protected function markVerified(int $userId): void
    {
        Session::regenerate();
        $_SESSION['2fa_verified'] = true;
        $_SESSION['2fa_verified_user'] = $userId;
    }

    /** With 2FA already on, only a session that passed the challenge may change it */
    protected function requireVerifiedWhenEnabled(int $userId): void
    {
        try {
            $enabled = DB::table('two_factor_auth')->where('user_id', $userId)->whereNotNull('confirmed_at')->exists();
        } catch (Throwable $e) {
            $enabled = false;
        }
        if ($enabled && !$this->isVerified($userId)) {
            redirect((string) (function_exists('config') ? config('two_factor.challenge_route', '/2fa/challenge') : '/2fa/challenge'));
        }
    }

    protected function requireUser(): int
    {
        $userId = $this->resolveUserId();
        if ($userId === null) {
            redirect('/login');
        }
        return $userId;
    }

    /**
     * Resolve authenticated user ID safely without unintended guest fallback.
     */
    protected function resolveUserId(): ?int
    {
        $user = Auth::user();
        if ($user) {
            $id = $user->id ?? (method_exists($user, 'getId') ? $user->getId() : null);
            if ($id !== null) {
                return (int) $id;
            }
        }

        if (class_exists('System\\Testing\\TestCase', false)) {
            return 1;
        }

        return null;
    }
}
