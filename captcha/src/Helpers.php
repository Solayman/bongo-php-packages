<?php

declare(strict_types=1);

use Bongo\Packages\Captcha\Captcha;

if (!function_exists('captcha_render')) {
    function captcha_render(array $attributes = [], ?string $driver = null): string
    {
        return Captcha::render($attributes, $driver);
    }
}

if (!function_exists('captcha_script')) {
    function captcha_script(?string $driver = null): string
    {
        return Captcha::script($driver);
    }
}

if (!function_exists('captcha_verify')) {
    function captcha_verify(?string $token = null, ?string $clientIp = null, ?string $driver = null): bool
    {
        return Captcha::verify($token, $clientIp, $driver);
    }
}

if (!function_exists('captcha_input_name')) {
    function captcha_input_name(?string $driver = null): string
    {
        return Captcha::inputName($driver);
    }
}
