<?php

declare(strict_types=1);

namespace Bongo\Packages\Captcha;

use Bongo\Packages\Captcha\Drivers\CaptchaDriverInterface;
use Bongo\Packages\Captcha\Drivers\HcaptchaDriver;
use Bongo\Packages\Captcha\Drivers\ImageDriver;
use Bongo\Packages\Captcha\Drivers\MathDriver;
use Bongo\Packages\Captcha\Drivers\RecaptchaDriver;
use Bongo\Packages\Captcha\Drivers\TurnstileDriver;
use InvalidArgumentException;

class Captcha
{
    protected static array $instances = [];
    protected static ?array $customConfig = null;

    /**
     * Set explicit config array (useful for testing or runtime overrides).
     */
    public static function setConfig(array $config): void
    {
        self::$customConfig = $config;
        self::$instances = [];
    }

    /**
     * Get the active driver instance.
     */
    public static function driver(?string $name = null): CaptchaDriverInterface
    {
        $config = self::resolveConfig();
        $name = strtolower($name ?? ($config['driver'] ?? 'turnstile'));

        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        switch ($name) {
            case 'turnstile':
                $driver = new TurnstileDriver($config['turnstile'] ?? []);
                break;
            case 'recaptcha':
                $driver = new RecaptchaDriver($config['recaptcha'] ?? []);
                break;
            case 'hcaptcha':
                $driver = new HcaptchaDriver($config['hcaptcha'] ?? []);
                break;
            case 'math':
                $driver = new MathDriver($config['math'] ?? []);
                break;
            case 'image':
                $driver = new ImageDriver($config['image'] ?? []);
                break;
            default:
                throw new InvalidArgumentException("Unsupported captcha driver: [{$name}].");
        }

        return self::$instances[$name] = $driver;
    }

    /**
     * Register a mock or custom driver instance.
     */
    public static function setDriverInstance(string $name, CaptchaDriverInterface $instance): void
    {
        self::$instances[strtolower($name)] = $instance;
    }

    /**
     * Render the captcha HTML widget.
     */
    public static function render(array $attributes = [], ?string $driver = null): string
    {
        return self::driver($driver)->render($attributes);
    }

    /**
     * Render the required script tag.
     */
    public static function script(?string $driver = null): string
    {
        return self::driver($driver)->script();
    }

    /**
     * Verify the user response token or input.
     */
    public static function verify(?string $response = null, ?string $clientIp = null, ?string $driver = null): bool
    {
        return self::driver($driver)->verify($response, $clientIp);
    }

    /**
     * Get the request field name for the driver.
     */
    public static function inputName(?string $driver = null): string
    {
        return self::driver($driver)->inputName();
    }

    /**
     * Reset cached driver instances.
     */
    public static function reset(): void
    {
        self::$instances = [];
        self::$customConfig = null;
    }

    /**
     * Resolve configuration from custom override or config/captcha.php file.
     */
    protected static function resolveConfig(): array
    {
        if (self::$customConfig !== null) {
            return self::$customConfig;
        }

        if (function_exists('config')) {
            $cfg = config('captcha');
            if (is_array($cfg)) {
                return $cfg;
            }
        }

        $configFile = __DIR__ . '/../config/captcha.php';
        if (is_file($configFile)) {
            $cfg = require $configFile;
            if (is_array($cfg)) {
                return $cfg;
            }
        }

        return [
            'driver' => 'math',
        ];
    }
}
