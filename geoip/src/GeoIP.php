<?php

declare(strict_types=1);

namespace Bongo\Packages\GeoIP;

class GeoIP
{
    /** @var array<string, Location> */
    protected static array $cache = [];
    /** @var array<string, array> */
    protected static array $fakes = [];
    protected static bool $isFake = false;

    /**
     * Map country codes to primary currencies.
     */
    protected static array $currencyMap = [
        'BD' => 'BDT',
        'US' => 'USD',
        'GB' => 'GBP',
        'CA' => 'CAD',
        'AU' => 'AUD',
        'SA' => 'SAR',
        'AE' => 'AED',
        'IN' => 'INR',
        'JP' => 'JPY',
        'MY' => 'MYR',
        'SG' => 'SGD',
        'DE' => 'EUR',
        'FR' => 'EUR',
        'IT' => 'EUR',
        'ES' => 'EUR',
    ];

    public static function lookup(?string $ip = null): Location
    {
        $targetIp = $ip ?? self::resolveClientIp();

        if (isset(self::$cache[$targetIp])) {
            return self::$cache[$targetIp];
        }

        if (self::$isFake && isset(self::$fakes[$targetIp])) {
            $loc = Location::fromArray(array_merge(['ip' => $targetIp], self::$fakes[$targetIp]));
            self::$cache[$targetIp] = $loc;
            return $loc;
        }

        if (self::isPrivateIp($targetIp)) {
            $defaults = config('geoip.default_location', [
                'country'      => 'Bangladesh',
                'country_code' => 'BD',
                'city'         => 'Dhaka',
                'region'       => 'Dhaka Division',
                'timezone'     => 'Asia/Dhaka',
                'currency'     => 'BDT',
                'latitude'     => 23.8103,
                'longitude'    => 90.4125,
                'is_local'     => true,
            ]);
            $loc = Location::fromArray(array_merge(['ip' => $targetIp], $defaults));
            self::$cache[$targetIp] = $loc;
            return $loc;
        }

        // Live external IP lookup with graceful fallback
        $loc = self::fetchFromApi($targetIp);
        self::$cache[$targetIp] = $loc;
        return $loc;
    }

    public static function isPrivateIp(string $ip): bool
    {
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
            return true;
        }

        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * The visitor's IP. Forwarding headers (X-Forwarded-For, CF-Connecting-IP...) are trusted only from
     * the proxies configured in the core (Request::ip() and its trusted proxy list); otherwise any
     * visitor could claim to be in another country.
     */
    public static function resolveClientIp(): string
    {
        if (class_exists(\System\Http\Request::class)) {
            $ip = (string) \System\Http\Request::ip();
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '127.0.0.1';
    }

    public static function fake(array $mockLocations = []): void
    {
        self::$isFake = true;
        self::$fakes = $mockLocations;
    }

    public static function clearFake(): void
    {
        self::$isFake = false;
        self::$fakes = [];
        self::$cache = [];
    }

    protected static function fetchFromApi(string $ip): Location
    {
        $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,regionName,city,lat,lon,timezone,currency";
        $options = [
            'http' => [
                'timeout'       => (int) config('geoip.timeout', 5),
                'ignore_errors' => true,
            ],
        ];

        $ctx = stream_context_create($options);
        $raw = @file_get_contents($url, false, $ctx);

        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (is_array($data) && ($data['status'] ?? '') === 'success') {
                $cCode = strtoupper((string) ($data['countryCode'] ?? 'XX'));
                $currency = $data['currency'] ?? (self::$currencyMap[$cCode] ?? 'USD');
                return new Location(
                    ip: $ip,
                    country: (string) ($data['country'] ?? 'Unknown'),
                    countryCode: $cCode,
                    city: (string) ($data['city'] ?? ''),
                    region: (string) ($data['regionName'] ?? ''),
                    timezone: (string) ($data['timezone'] ?? 'UTC'),
                    currency: $currency,
                    latitude: (float) ($data['lat'] ?? 0.0),
                    longitude: (float) ($data['lon'] ?? 0.0),
                    isLocal: false
                );
            }
        }

        // Graceful fallback if offline/rate-limited
        return new Location(
            ip: $ip,
            country: 'Unknown',
            countryCode: 'XX',
            city: '',
            region: '',
            timezone: 'UTC',
            currency: 'USD',
            latitude: 0.0,
            longitude: 0.0,
            isLocal: false
        );
    }
}
