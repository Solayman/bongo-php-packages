<?php

declare(strict_types=1);

namespace Bongo\Packages\Captcha\Drivers;

interface CaptchaDriverInterface
{
    /**
     * Render the HTML input or widget for the captcha.
     */
    public function render(array $attributes = []): string;

    /**
     * Render required client-side script tags (if any).
     */
    public function script(): string;

    /**
     * Verify the user response token.
     */
    public function verify(?string $response = null, ?string $clientIp = null): bool;

    /**
     * Get the default POST/GET request input name for this driver.
     */
    public function inputName(): string;
}
