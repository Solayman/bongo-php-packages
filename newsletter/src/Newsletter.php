<?php

declare(strict_types=1);

namespace Bongo\Packages\Newsletter;

use Bongo\Packages\Newsletter\Models\Subscriber;
use InvalidArgumentException;

class Newsletter
{
    protected static bool $faking = false;
    /** @var array<int, array<string, mixed>> */
    protected static array $fakeSubscribers = [];
    /** @var array<string, bool> */
    protected static array $statusCache = [];

    /**
     * Clear memoized subscription status cache.
     */
    public static function flushCache(): void
    {
        static::$statusCache = [];
    }

    /**
     * Enable fake mode for tests.
     */
    public static function fake(): static
    {
        static::$faking = true;
        static::$fakeSubscribers = [];
        static::$statusCache = [];
        return new static();
    }

    public static function isFake(): bool
    {
        return static::$faking;
    }

    public static function stopFaking(): void
    {
        static::$faking = false;
        static::$fakeSubscribers = [];
        static::$statusCache = [];
    }

    /**
     * Subscribe an email address to the newsletter.
     */
    public function subscribe(
        string $email,
        ?string $name = null,
        ?string $source = null,
        ?string $ip = null
    ): Subscriber {
        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address: '{$email}'.");
        }

        // Sanitize name and source
        if ($name !== null) {
            $name = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $name) ?? $name;
            $name = trim(strip_tags($name));
            if (mb_strlen($name) > 100) {
                $name = mb_substr($name, 0, 100);
            }
        }

        $defaultSource = function_exists('config') ? config('newsletter.default_source', 'website') : 'website';
        if ($source !== null) {
            $source = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $source) ?? $source;
            $source = trim(strip_tags($source));
        } else {
            $source = $defaultSource;
        }
        $ip = $ip !== null ? trim(strip_tags($ip)) : null;

        $doubleOptIn = function_exists('config') ? (bool) config('newsletter.double_opt_in', true) : true;
        $now = date('Y-m-d H:i:s');

        if (static::$faking) {
            return $this->subscribeFake($email, $name, $source, $ip, $doubleOptIn, $now);
        }

        /** @var Subscriber|null $existing */
        $existing = Subscriber::query()->where('email', $email)->first();

        if ($existing) {
            if ($existing->isSubscribed()) {
                return $existing;
            }

            // If pending or unsubscribed, refresh token and status
            $token = bin2hex(random_bytes(32));
            if ($doubleOptIn) {
                $existing->status = 'pending';
                $existing->token = $token;
                $existing->verified_at = null;
            } else {
                $existing->status = 'subscribed';
                $existing->token = null;
                $existing->verified_at = $now;
                $existing->unsubscribed_at = null;
            }

            if ($name !== null) {
                $existing->name = $name;
            }
            $existing->source = $source;
            if ($ip !== null) {
                $existing->ip_address = $ip;
            }
            $existing->save();

            static::$statusCache[$email] = ($existing->status === 'subscribed');
            return $existing;
        }

        // Generate tokens
        $token = bin2hex(random_bytes(32));
        $unsubscribeToken = bin2hex(random_bytes(32));

        $subscriber = new Subscriber([
            'email' => $email,
            'name' => $name,
            'status' => $doubleOptIn ? 'pending' : 'subscribed',
            'token' => $doubleOptIn ? $token : null,
            'unsubscribe_token' => $unsubscribeToken,
            'source' => $source,
            'ip_address' => $ip,
            'verified_at' => $doubleOptIn ? null : $now,
            'unsubscribed_at' => null,
        ]);
        $subscriber->save();

        static::$statusCache[$email] = ($subscriber->status === 'subscribed');
        return $subscriber;
    }

    /**
     * Confirm subscription using double opt-in token.
     */
    public function confirm(string $token): ?Subscriber
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $now = date('Y-m-d H:i:s');

        if (static::$faking) {
            foreach (static::$fakeSubscribers as &$item) {
                if (($item['token'] ?? null) === $token) {
                    $item['status'] = 'subscribed';
                    $item['token'] = null;
                    $item['verified_at'] = $now;
                    $sub = new Subscriber($item);
                    static::$statusCache[strtolower((string) $sub->email)] = true;
                    return $sub;
                }
            }
            return null;
        }

        /** @var Subscriber|null $subscriber */
        $subscriber = Subscriber::query()->where('token', $token)->first();
        if (!$subscriber) {
            return null;
        }

        $subscriber->status = 'subscribed';
        $subscriber->token = null;
        $subscriber->verified_at = $now;
        $subscriber->save();

        static::$statusCache[strtolower((string) $subscriber->email)] = true;
        return $subscriber;
    }

    /**
     * Unsubscribe an email using unsubscribe token or email address.
     */
    public function unsubscribe(string $tokenOrEmail): bool
    {
        $target = trim(strtolower($tokenOrEmail));
        if ($target === '') {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        if (static::$faking) {
            foreach (static::$fakeSubscribers as &$item) {
                if (($item['unsubscribe_token'] ?? '') === $tokenOrEmail || strtolower($item['email'] ?? '') === $target) {
                    $item['status'] = 'unsubscribed';
                    $item['unsubscribed_at'] = $now;
                    static::$statusCache[$target] = false;
                    if (isset($item['email'])) {
                        static::$statusCache[strtolower((string) $item['email'])] = false;
                    }
                    return true;
                }
            }
            return false;
        }

        /** @var Subscriber|null $subscriber */
        $subscriber = Subscriber::query()
            ->where('unsubscribe_token', $tokenOrEmail)
            ->orWhere('email', $target)
            ->first();

        if (!$subscriber) {
            return false;
        }

        $subscriber->status = 'unsubscribed';
        $subscriber->unsubscribed_at = $now;
        $subscriber->save();

        static::$statusCache[$target] = false;
        if (!empty($subscriber->email)) {
            static::$statusCache[strtolower((string) $subscriber->email)] = false;
        }

        return true;
    }

    /**
     * Check if an email is actively subscribed.
     */
    public function isSubscribed(string $email): bool
    {
        $email = trim(strtolower($email));
        if ($email === '') {
            return false;
        }

        if (isset(static::$statusCache[$email])) {
            return static::$statusCache[$email];
        }

        if (static::$faking) {
            foreach (static::$fakeSubscribers as $item) {
                if (strtolower($item['email'] ?? '') === $email && ($item['status'] ?? '') === 'subscribed') {
                    static::$statusCache[$email] = true;
                    return true;
                }
            }
            static::$statusCache[$email] = false;
            return false;
        }

        $exists = Subscriber::query()
            ->where('email', $email)
            ->where('status', 'subscribed')
            ->exists();

        if (count(static::$statusCache) < 1000) {
            static::$statusCache[$email] = $exists;
        }

        return $exists;
    }

    /**
     * Count subscribers, optionally filtered by status.
     */
    public function count(?string $status = 'subscribed'): int
    {
        if (static::$faking) {
            if ($status === null) {
                return count(static::$fakeSubscribers);
            }
            $filtered = array_filter(static::$fakeSubscribers, fn($s) => ($s['status'] ?? '') === $status);
            return count($filtered);
        }

        $query = Subscriber::query();
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    /**
     * Retrieve subscriber records.
     *
     * @return Subscriber[]
     */
    public function subscribers(?string $status = 'subscribed', int $limit = 100): array
    {
        if (static::$faking) {
            $list = [];
            foreach (static::$fakeSubscribers as $item) {
                if ($status === null || ($item['status'] ?? '') === $status) {
                    $list[] = new Subscriber($item);
                    if (count($list) >= $limit) {
                        break;
                    }
                }
            }
            return $list;
        }

        $query = Subscriber::query();
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderBy('id', 'DESC')->limit($limit)->get();
    }

    /**
     * Export subscribers as an RFC 4180 CSV string with UTF-8 BOM and formula injection protection.
     */
    public function exportCsv(?string $status = 'subscribed'): string
    {
        $rows = $this->subscribers($status, 10000);

        // UTF-8 BOM for Microsoft Excel compatibility
        $csv = "\xEF\xBB\xBF";
        $csv .= "ID,Email,Name,Status,Source,Verified At,Subscribed At\r\n";

        foreach ($rows as $row) {
            $cols = [
                $row->id ?? '',
                $this->sanitizeCsvField((string) ($row->email ?? '')),
                $this->sanitizeCsvField((string) ($row->name ?? '')),
                $row->status ?? '',
                $this->sanitizeCsvField((string) ($row->source ?? '')),
                $row->verified_at ?? '',
                $row->created_at ?? '',
            ];

            $escaped = array_map(function ($val) {
                $v = str_replace('"', '""', (string) $val);
                return '"' . $v . '"';
            }, $cols);

            $csv .= implode(',', $escaped) . "\r\n";
        }

        return $csv;
    }

    /**
     * Get verification URL for a subscriber.
     */
    public function verificationUrl(Subscriber $subscriber): string
    {
        $tpl = function_exists('config') ? config('newsletter.verify_url', '/newsletter/verify/{token}') : '/newsletter/verify/{token}';
        return str_replace('{token}', (string) $subscriber->token, $tpl);
    }

    /**
     * Get unsubscribe URL for a subscriber.
     */
    public function unsubscribeUrl(Subscriber $subscriber): string
    {
        $tpl = function_exists('config') ? config('newsletter.unsubscribe_url', '/newsletter/unsubscribe/{token}') : '/newsletter/unsubscribe/{token}';
        return str_replace('{token}', (string) $subscriber->unsubscribe_token, $tpl);
    }

    /**
     * Prevent CSV formula injection (DDE attacks) by prefixing problematic characters.
     */
    protected function sanitizeCsvField(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Internal handler for fake mode subscriptions.
     */
    protected function subscribeFake(
        string $email,
        ?string $name,
        string $source,
        ?string $ip,
        bool $doubleOptIn,
        string $now
    ): Subscriber {
        foreach (static::$fakeSubscribers as &$item) {
            if (strtolower($item['email'] ?? '') === $email) {
                if (($item['status'] ?? '') === 'subscribed') {
                    return new Subscriber($item);
                }
                $token = bin2hex(random_bytes(32));
                $item['status'] = $doubleOptIn ? 'pending' : 'subscribed';
                $item['token'] = $doubleOptIn ? $token : null;
                $item['verified_at'] = $doubleOptIn ? null : $now;
                if ($name !== null) {
                    $item['name'] = $name;
                }
                return new Subscriber($item);
            }
        }

        $id = count(static::$fakeSubscribers) + 1;
        $token = bin2hex(random_bytes(32));
        $unsubscribeToken = bin2hex(random_bytes(32));

        $data = [
            'id' => $id,
            'email' => $email,
            'name' => $name,
            'status' => $doubleOptIn ? 'pending' : 'subscribed',
            'token' => $doubleOptIn ? $token : null,
            'unsubscribe_token' => $unsubscribeToken,
            'source' => $source,
            'ip_address' => $ip,
            'verified_at' => $doubleOptIn ? null : $now,
            'unsubscribed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        static::$fakeSubscribers[] = $data;
        return new Subscriber($data);
    }
}
