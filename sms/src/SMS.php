<?php

declare(strict_types=1);

namespace Bongo\Packages\Sms;

use Bongo\Packages\Sms\Contracts\SMSDriver;
use Bongo\Packages\Sms\Drivers\AlphaSMSDriver;
use Bongo\Packages\Sms\Drivers\BulkSMSBDDriver;
use Bongo\Packages\Sms\Drivers\LogDriver;
use Bongo\Packages\Sms\Drivers\SSLWirelessDriver;
use InvalidArgumentException;
use System\Events\Event;

class SMS
{
    /** @var array<string, SMSDriver> */
    protected static array $instances = [];

    /** @var array<string, callable> */
    protected static array $customDrivers = [];

    /**
     * Send an SMS using the default or specified driver.
     *
     * @param string $to Recipient phone number
     * @param string $message Text message
     * @param string|null $driver Driver name or null for default
     * @return array{success: bool, id: ?string, raw: mixed, error: ?string}
     */
    public static function send(string $to, string $message, ?string $driver = null): array
    {
        $drv = self::driver($driver);
        $result = $drv->send($to, $message);

        if ($result['success'] && class_exists(Event::class)) {
            Event::dispatch('sms.sent', [
                'to'      => $to,
                'message' => $message,
                'driver'  => $driver ?? self::getDefaultDriver(),
                'id'      => $result['id'],
            ]);
        }

        return $result;
    }

    /**
     * Get or instantiate an SMS driver instance.
     */
    public static function driver(?string $name = null): SMSDriver
    {
        $name ??= self::getDefaultDriver();

        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        return self::$instances[$name] = self::createDriver($name);
    }

    /**
     * Register a custom driver resolver.
     */
    public static function extend(string $name, callable $resolver): void
    {
        self::$customDrivers[$name] = $resolver;
        unset(self::$instances[$name]);
    }

    /**
     * Reset cached driver instances (useful during testing).
     */
    public static function reset(): void
    {
        self::$instances = [];
    }

    protected static function createDriver(string $name): SMSDriver
    {
        if (isset(self::$customDrivers[$name])) {
            return call_user_func(self::$customDrivers[$name], self::getConfig($name));
        }

        $config = self::getConfig($name);

        return match ($name) {
            'log'         => new LogDriver($config),
            'bulksmsbd'   => new BulkSMSBDDriver($config),
            'sslwireless' => new SSLWirelessDriver($config),
            'alphasms'    => new AlphaSMSDriver($config),
            default       => throw new InvalidArgumentException("Unsupported SMS driver: [$name]"),
        };
    }

    protected static function getDefaultDriver(): string
    {
        if (function_exists('config')) {
            return (string) config('sms.default', 'log');
        }
        return (string) env('SMS_DRIVER', 'log');
    }

    protected static function getConfig(string $name): array
    {
        if (function_exists('config')) {
            return (array) config("sms.drivers.$name", []);
        }
        return [];
    }
}
