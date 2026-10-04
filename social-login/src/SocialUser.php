<?php

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
