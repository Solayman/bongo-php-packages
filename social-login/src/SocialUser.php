<?php

declare(strict_types=1);

namespace Bongo\Packages\SocialLogin;

/**
 * Normalized user data returned by OAuth providers.
 */
class SocialUser
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $avatar = null,
        public ?string $nickname = null,
        public string $provider = '',
        public string $token = '',
        public array $raw = []
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * The provider confirmed the user owns this email. Only then may it be linked to an existing
     * account with the same email (otherwise anyone could sign in as that account).
     * Google: email_verified. GitHub: public and primary emails must be verified. Facebook only
     * returns confirmed emails. Custom providers: set raw['email_verified'] = true.
     */
    public function isEmailVerified(): bool
    {
        if (empty($this->email)) {
            return false;
        }
        $flag = $this->raw['email_verified'] ?? null;
        return match ($this->provider) {
            'github', 'facebook' => true,
            default => $flag === true || $flag === 'true',
        };
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getRaw(): array
    {
        return $this->raw;
    }
}
