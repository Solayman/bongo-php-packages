<?php

namespace Bongo\Packages\SocialLogin\Drivers;

use Bongo\Packages\SocialLogin\SocialUser;
use RuntimeException;
use System\Http\Http;

class GitHubProvider extends AbstractProvider
{
    public function name(): string
    {
        return 'github';
    }

    protected function authUrl(): string
    {
        return 'https://github.com/login/oauth/authorize';
    }

    protected function tokenUrl(): string
    {
        return 'https://github.com/login/oauth/access_token';
    }

    protected function getAccessToken(string $code): string
    {
        $response = Http::withHeaders(['Accept' => 'application/json'])
            ->asForm()
            ->post($this->tokenUrl(), [
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code'          => $code,
                'redirect_uri'  => $this->redirectUri,
            ]);

        if ($response->failed() || empty($response->json('access_token'))) {
            $err = $response->json('error_description') ?: $response->json('error') ?: 'Failed to retrieve access token';
            throw new RuntimeException("GitHub OAuth token exchange failed: {$err}");
        }

        return (string) $response->json('access_token');
    }

    protected function getUserByToken(string $token): array
    {
        $response = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'Bongo-OAuth-Client'])
            ->get('https://api.github.com/user');

        if ($response->failed()) {
            throw new RuntimeException("Failed to fetch GitHub user profile");
        }

        $user = (array) $response->json();

        // If email is private on GitHub profile, query primary verified email
        if (empty($user['email'])) {
            $emailsRes = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'Bongo-OAuth-Client'])
                ->get('https://api.github.com/user/emails');

            if ($emailsRes->ok() && is_array($emailsRes->json())) {
                foreach ($emailsRes->json() as $entry) {
                    if (!empty($entry['primary']) && !empty($entry['verified'])) {
                        $user['email'] = (string) $entry['email'];
                        break;
                    }
                }
            }
        }

        return $user;
    }

    protected function mapUser(array $raw, string $token): SocialUser
    {
        return new SocialUser(
            id: (string) ($raw['id'] ?? ''),
            name: (string) ($raw['name'] ?? $raw['login'] ?? ''),
            email: isset($raw['email']) ? (string) $raw['email'] : null,
            avatar: isset($raw['avatar_url']) ? (string) $raw['avatar_url'] : null,
            nickname: isset($raw['login']) ? (string) $raw['login'] : null,
            provider: 'github',
            token: $token,
            raw: $raw
        );
    }
}
