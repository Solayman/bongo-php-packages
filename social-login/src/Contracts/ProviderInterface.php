<?php

namespace Bongo\Packages\SocialLogin\Contracts;

use Bongo\Packages\SocialLogin\SocialUser;

interface ProviderInterface
{
    /**
     * Generate provider authorization redirect URL with CSRF state protection.
     */
    public function getAuthUrl(): string;

    /**
     * Redirect user to provider login dialog.
     */
    public function redirect(): void;

    /**
     * Complete callback, exchange code for access token, and fetch normalized user.
     */
    public function user(): SocialUser;

    /**
     * Override requested OAuth scopes.
     */
    public function scopes(array $scopes): static;
}
