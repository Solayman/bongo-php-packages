<?php

namespace Bongo\Packages\SocialLogin\Drivers;

use Bongo\Packages\SocialLogin\SocialUser;
use RuntimeException;
use System\Http\Http;

class GoogleProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'google';
    }

    protected function authUrl(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function tokenUrl(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function getAccessToken(string $code): string
    {
        $response = Http::asForm()->post($this->tokenUrl(), [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if ($response->failed() || empty($response->json('access_token'))) {
            $err = $response->json('error_description') ?: $response->json('error') ?: 'Failed to retrieve access token';
            throw new RuntimeException("Google OAuth token exchange failed: {$err}");
        }

        return (string) $response->json('access_token');
    }

    protected function getUserByToken(string $token): array
    {
        $response = Http::withToken($token)->get('https://openidconnect.googleapis.com/v1/userinfo');
        if ($response->failed()) {
            throw new RuntimeException("Failed to fetch Google user profile");
        }

        return (array) $response->json();
    }

    protected function mapUser(array $raw, string $token): SocialUser
    {
        return new SocialUser(
            id: (string) ($raw['sub'] ?? ''),
            name: (string) ($raw['name'] ?? ''),
            email: isset($raw['email']) ? (string) $raw['email'] : null,
            avatar: isset($raw['picture']) ? (string) $raw['picture'] : null,
            nickname: isset($raw['given_name']) ? (string) $raw['given_name'] : null,
            provider: 'google',
            token: $token,
            raw: $raw
        );
    }
}
