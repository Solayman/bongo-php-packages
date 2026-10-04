<?php

namespace Bongo\Packages\SocialLogin\Drivers;

use Bongo\Packages\SocialLogin\Contracts\ProviderInterface;
use Bongo\Packages\SocialLogin\SocialUser;
use RuntimeException;
use System\Http\Session;

abstract class AbstractProvider implements ProviderInterface
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;
    protected array $scopes = [];
    protected bool $stateless = false;

    public function __construct(array $config)
    {
        $this->clientId     = (string) ($config['client_id'] ?? '');
        $this->clientSecret = (string) ($config['client_secret'] ?? '');
        $this->redirectUri  = (string) ($config['redirect'] ?? '');
        $this->scopes       = (array) ($config['scopes'] ?? []);
    }

    abstract public function name(): string;
    abstract protected function authUrl(): string;
    abstract protected function tokenUrl(): string;
    abstract protected function getAccessToken(string $code): string;
    abstract protected function getUserByToken(string $token): array;
    abstract protected function mapUser(array $raw, string $token): SocialUser;

    public function scopes(array $scopes): static
    {
        $this->scopes = $scopes;
        return $this;
    }

    public function stateless(): static
    {
        $this->stateless = true;
        return $this;
    }

    public function getAuthUrl(): string
    {
        $params = [
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'scope'         => implode(' ', $this->scopes),
            'response_type' => 'code',
        ];

        if (!$this->stateless) {
            $state = bin2hex(random_bytes(16));
            if (class_exists(Session::class)) {
                Session::put('oauth_state_' . $this->name(), $state);
            } else {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    @session_start();
                }
                $_SESSION['oauth_state_' . $this->name()] = $state;
            }
            $params['state'] = $state;
        }

        return $this->authUrl() . '?' . http_build_query($params);
    }

    public function redirect(): void
    {
        $url = $this->getAuthUrl();
        if (function_exists('redirect')) {
            redirect($url);
            return;
        }
        if (!headers_sent()) {
            header('Location: ' . $url);
        }
        exit;
    }

    public function user(): SocialUser
    {
        if (!$this->stateless) {
            $this->validateState();
        }

        $code = (string) ($_GET['code'] ?? '');
        if ($code === '') {
            $err = (string) ($_GET['error_description'] ?? $_GET['error'] ?? 'Missing authorization code');
            throw new RuntimeException("OAuth Error from {$this->name()}: {$err}");
        }

        $token = $this->getAccessToken($code);
        $raw = $this->getUserByToken($token);

        return $this->mapUser($raw, $token);
    }

    protected function validateState(): void
    {
        $expected = null;
        if (class_exists(Session::class)) {
            $expected = Session::pull('oauth_state_' . $this->name());
        } elseif (isset($_SESSION['oauth_state_' . $this->name()])) {
            $expected = $_SESSION['oauth_state_' . $this->name()];
            unset($_SESSION['oauth_state_' . $this->name()]);
        }

        $given = (string) ($_GET['state'] ?? '');
        if ($expected === null || $given === '' || !hash_equals((string) $expected, $given)) {
            $message = function_exists('__')
                ? __('social::Invalid OAuth state. Please try again.')
                : 'Invalid OAuth state. Please try again.';
            throw new RuntimeException($message);
        }
    }
}
