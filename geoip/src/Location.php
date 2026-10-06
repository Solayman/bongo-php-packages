<?php

declare(strict_types=1);

namespace Bongo\Packages\GeoIP;

class Location
{
    public function __construct(
        public string $ip,
        public string $country,
        public string $countryCode,
        public string $city = '',
        public string $region = '',
        public string $timezone = 'UTC',
        public string $currency = 'USD',
        public float $latitude = 0.0,
        public float $longitude = 0.0,
        public bool $isLocal = false
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ip: (string) ($data['ip'] ?? '127.0.0.1'),
            country: (string) ($data['country'] ?? 'Unknown'),
            countryCode: strtoupper((string) ($data['country_code'] ?? $data['countryCode'] ?? 'XX')),
            city: (string) ($data['city'] ?? ''),
            region: (string) ($data['region'] ?? ''),
            timezone: (string) ($data['timezone'] ?? 'UTC'),
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            latitude: (float) ($data['latitude'] ?? $data['lat'] ?? 0.0),
            longitude: (float) ($data['longitude'] ?? $data['lon'] ?? 0.0),
            isLocal: (bool) ($data['is_local'] ?? $data['isLocal'] ?? false)
        );
    }

    public function is(string $code): bool
    {
        return strtoupper($code) === $this->countryCode;
    }

    public function isLocal(): bool
    {
        return $this->isLocal;
    }

    public function toArray(): array
    {
        return [
            'ip'           => $this->ip,
            'country'      => $this->country,
            'country_code' => $this->countryCode,
            'city'         => $this->city,
            'region'       => $this->region,
            'timezone'     => $this->timezone,
            'currency'     => $this->currency,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'is_local'     => $this->isLocal,
        ];
    }
}
