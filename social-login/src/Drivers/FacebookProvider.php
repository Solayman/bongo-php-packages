<?php

declare(strict_types=1);

namespace Bongo\Packages\SocialLogin\Drivers;

use Bongo\Packages\SocialLogin\SocialUser;
use RuntimeException;
use System\Http\Http;

class FacebookProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'facebook';
    }

    protected function authUrl(): string
    {
        return 'https://www.facebook.com/v19.0/dialog/oauth';
    }

    protected function tokenUrl(): string
    {
        return 'https://graph.facebook.com/v19.0/oauth/access_token';
    }

    protected function getAccessToken(string $code): string
    {
        $response = Http::asForm()->post($this->tokenUrl(), [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
        ]);

        if ($response->failed() || empty($response->json('access_token'))) {
            $err = $response->json('error.message') ?: 'Failed to retrieve access token';
            throw new RuntimeException("Facebook OAuth token exchange failed: {$err}");
        }

        return (string) $response->json('access_token');
    }

    protected function getUserByToken(string $token): array
    {
        $response = Http::withToken($token)->get('https://graph.facebook.com/v19.0/me', [
            'fields' => 'id,name,email,picture.width(250).height(250)',
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Failed to fetch Facebook user profile");
        }

        return (array) $response->json();
    }

    protected function mapUser(array $raw, string $token): SocialUser
    {
        $avatar = $raw['picture']['data']['url'] ?? null;

        return new SocialUser(
            id: (string) ($raw['id'] ?? ''),
            name: (string) ($raw['name'] ?? ''),
            email: isset($raw['email']) ? (string) $raw['email'] : null,
            avatar: $avatar ? (string) $avatar : null,
            nickname: null,
            provider: 'facebook',
            token: $token,
            raw: $raw
        );
    }
}
