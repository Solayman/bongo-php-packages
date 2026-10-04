<?php

namespace Bongo\Packages\SocialLogin;

use Bongo\Packages\SocialLogin\Contracts\ProviderInterface;
use Bongo\Packages\SocialLogin\Drivers\FacebookProvider;
use Bongo\Packages\SocialLogin\Drivers\GitHubProvider;
use Bongo\Packages\SocialLogin\Drivers\GoogleProvider;
use InvalidArgumentException;

class SocialLogin
{
    /** @var array<string, ProviderInterface> */
    protected static array $instances = [];

    /** @var array<string, callable> */
    protected static array $customDrivers = [];

    public static function driver(?string $driver = null): ProviderInterface
    {
        $driver = strtolower($driver ?? 'google');
        if (isset(self::$instances[$driver])) {
            return self::$instances[$driver];
        }

        if (isset(self::$customDrivers[$driver])) {
            $config = (array) config("social.providers.{$driver}", []);
            return self::$instances[$driver] = (self::$customDrivers[$driver])($config);
        }

        $config = (array) config("social.providers.{$driver}", []);

        return self::$instances[$driver] = match ($driver) {
            'google'   => new GoogleProvider($config),
            'github'   => new GitHubProvider($config),
            'facebook' => new FacebookProvider($config),
            default    => throw new InvalidArgumentException("Unsupported social login provider [$driver]"),
        };
    }

    public static function extend(string $driver, callable $callback): void
    {
        self::$customDrivers[strtolower($driver)] = $callback;
    }

    public static function set(string $driver, ProviderInterface $instance): void
    {
        self::$instances[strtolower($driver)] = $instance;
    }

    public static function redirect(?string $driver = null): void
    {
        self::driver($driver)->redirect();
    }

    public static function user(?string $driver = null): SocialUser
    {
        return self::driver($driver)->user();
    }

    public static function stateless(?string $driver = null): ProviderInterface
    {
        return self::driver($driver)->stateless();
    }

    public static function reset(): void
    {
        self::$instances = [];
        self::$customDrivers = [];
    }
}
