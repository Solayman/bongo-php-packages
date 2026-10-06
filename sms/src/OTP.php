<?php

declare(strict_types=1);

namespace Bongo\Packages\Sms;

use RuntimeException;
use System\Database\DB;
use System\Events\Event;
use Throwable;

class OTP
{
    /**
     * Generate and dispatch a one-time password code to the given phone number.
     *
     * @param string $phone Recipient phone number
     * @param string $purpose Identifier/context for the OTP (e.g. 'login', 'register', 'reset')
     * @param string|null $driver SMS gateway driver or null for default
     * @return array{success: bool, phone: string, expires_at: string, resend_after: int, error: ?string}
     */
    public static function send(string $phone, string $purpose = 'login', ?string $driver = null): array
    {
        $phone = self::normalizePhone($phone);
        $settings = self::settings();

        // Enforce resend cooldown to protect against spamming
        $cooldown = (int) ($settings['resend_cooldown'] ?? 60);
        $recent = self::getLatestRecord($phone, $purpose);

        if ($recent && empty($recent->verified_at)) {
            $createdSec = strtotime((string) $recent->created_at);
            $elapsed = time() - $createdSec;
            if ($elapsed < $cooldown) {
                $wait = $cooldown - $elapsed;
                throw new RuntimeException("Please wait {$wait} second(s) before requesting another verification code.");
            }
        }

        $length = max(4, min(8, (int) ($settings['length'] ?? 6)));
        $code = self::generateCode($length);
        $ttl = (int) ($settings['expires_in'] ?? 300);
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $codeHash = hash('sha256', $code);

        // Record in database
        self::recordOtp($phone, $codeHash, $purpose, $expiresAt);

        // Render message
        $minutes = max(1, (int) ceil($ttl / 60));
        $message = function_exists('__')
            ? __('sms::Your verification code is: :code. Valid for :minutes minutes.', ['code' => $code, 'minutes' => $minutes])
            : "Your verification code is: {$code}. Valid for {$minutes} minutes.";

        // Send SMS
        $sent = SMS::send($phone, $message, $driver);

        if (!$sent['success']) {
            return [
                'success'      => false,
                'phone'        => $phone,
                'expires_at'   => $expiresAt,
                'resend_after' => $cooldown,
                'error'        => $sent['error'] ?? 'Failed to send SMS verification code',
            ];
        }

        return [
            'success'      => true,
            'phone'        => $phone,
            'expires_at'   => $expiresAt,
            'resend_after' => $cooldown,
            'error'        => null,
        ];
    }

    /**
     * Verify an entered OTP code against the latest active record.
     *
     * @param string $phone Recipient phone number
     * @param string $code User-submitted OTP code
     * @param string $purpose Target context (default: 'login')
     * @return bool True if valid, false otherwise
     */
    public static function verify(string $phone, string $code, string $purpose = 'login'): bool
    {
        $phone = self::normalizePhone($phone);
        $code = trim($code);
        $maxAttempts = (int) (self::settings()['max_attempts'] ?? 5);

        try {
            // One locked read-check-write: parallel guesses cannot exceed max_attempts,
            // and a code cannot be accepted twice
            $verified = (bool) DB::transaction(function () use ($phone, $code, $purpose, $maxAttempts): bool {
                $record = DB::table('otp_codes')
                    ->where('phone', $phone)
                    ->where('purpose', $purpose)
                    ->orderBy('id', 'DESC')
                    ->lockForUpdate()
                    ->first();

                if (!$record || !empty($record->verified_at) || (int) $record->attempts >= $maxAttempts) {
                    return false;
                }

                $now = date('Y-m-d H:i:s');
                $ok = strtotime((string) $record->expires_at) >= time()
                    && hash_equals((string) $record->code_hash, hash('sha256', $code));

                DB::table('otp_codes')->where('id', $record->id)->update([
                    'attempts'    => (int) $record->attempts + 1,
                    'verified_at' => $ok ? $now : null,
                    'updated_at'  => $now,
                ]);
                return $ok;
            });
        } catch (Throwable $e) {
            return false;
        }

        if ($verified && class_exists(Event::class)) {
            Event::dispatch('otp.verified', [
                'phone'   => $phone,
                'purpose' => $purpose,
            ]);
        }

        return $verified;
    }

    /**
     * Normalize telephone number format.
     */
    public static function normalizePhone(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($clean, '8801')) {
            return '0' . substr($clean, 3);
        }
        return $clean;
    }

    /**
     * Cryptographically secure numeric code generator.
     */
    public static function generateCode(int $length = 6): string
    {
        $min = (int) pow(10, $length - 1);
        $max = (int) pow(10, $length) - 1;
        return (string) random_int($min, $max);
    }

    protected static function settings(): array
    {
        if (function_exists('config')) {
            return (array) config('sms.otp', []);
        }
        return [
            'length'          => 6,
            'expires_in'      => 300,
            'max_attempts'    => 5,
            'resend_cooldown' => 60,
        ];
    }

    protected static function getLatestRecord(string $phone, string $purpose): ?object
    {
        try {
            return DB::table('otp_codes')
                ->where('phone', $phone)
                ->where('purpose', $purpose)
                ->orderBy('id', 'DESC')
                ->first();
        } catch (Throwable $e) {
            return null;
        }
    }

    protected static function recordOtp(string $phone, string $codeHash, string $purpose, string $expiresAt): void
    {
        try {
            DB::table('otp_codes')->insert([
                'phone'       => $phone,
                'code_hash'   => $codeHash,
                'purpose'     => $purpose,
                'attempts'    => 0,
                'expires_at'  => $expiresAt,
                'verified_at' => null,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Non-fatal if database is not migrated yet
        }
    }

}
