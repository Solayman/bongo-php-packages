<?php

namespace Bongo\Packages\SocialLogin\Tests;

use Bongo\Packages\SocialLogin\Contracts\ProviderInterface;
use Bongo\Packages\SocialLogin\Drivers\AbstractProvider;
use Bongo\Packages\SocialLogin\SocialLogin;
use Bongo\Packages\SocialLogin\SocialUser;
use RuntimeException;
use System\Events\Event;
use System\Http\Http;
use System\Testing\TestCase;

class SocialLoginTest extends TestCase
{
    protected array $config = [
        'social' => [
            'providers' => [
                'google' => [
                    'client_id'     => 'test-google-id',
                    'client_secret' => 'test-google-secret',
                    'redirect'      => 'http://127.0.0.1:8000/auth/google/callback',
                    'scopes'        => ['openid', 'profile', 'email'],
                ],
                'github' => [
                    'client_id'     => 'test-github-id',
                    'client_secret' => 'test-github-secret',
                    'redirect'      => 'http://127.0.0.1:8000/auth/github/callback',
                    'scopes'        => ['user:email', 'read:user'],
                ],
                'facebook' => [
                    'client_id'     => 'test-fb-id',
                    'client_secret' => 'test-fb-secret',
                    'redirect'      => 'http://127.0.0.1:8000/auth/facebook/callback',
                    'scopes'        => ['email', 'public_profile'],
                ],
            ],
        ],
    ];

    public function setUp(): void
    {
        $GLOBALS['config'] = array_merge($GLOBALS['config'] ?? [], $this->config);
        $_GET = [];
    }

    public function tearDown(): void
    {
        Http::reset();
        SocialLogin::reset();
        $_GET = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    public function testGoogleAuthRedirectUrl(): void
    {
        $provider = SocialLogin::driver('google');
        $url = $provider->getAuthUrl();

        $this->assertContains('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertContains('client_id=test-google-id', $url);
        $this->assertContains('redirect_uri=' . urlencode('http://127.0.0.1:8000/auth/google/callback'), $url);
        $this->assertContains('response_type=code', $url);
        $this->assertContains('state=', $url);
    }

    public function testStateValidationRejectsMismatch(): void
    {
        $provider = SocialLogin::driver('google');
        $provider->getAuthUrl(); // sets state in session

        $_GET['state'] = 'wrong_state_token';
        $_GET['code']  = 'valid_mock_code';

        $this->assertThrows(RuntimeException::class, function () use ($provider) {
            $provider->user();
        });
    }

    public function testGoogleUserCallbackAndMapping(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mock_google_access_token',
                'token_type'   => 'Bearer',
                'expires_in'   => 3600,
            ]),
            'openidconnect.googleapis.com/v1/userinfo' => Http::response([
                'sub'        => 'google_10928374',
                'name'       => 'Rahim Ali',
                'given_name' => 'Rahim',
                'email'      => 'rahim@example.com',
                'picture'    => 'https://lh3.googleusercontent.com/avatar.jpg',
            ]),
        ]);

        $_GET['code'] = 'sample_oauth_code';

        $user = SocialLogin::stateless('google')->user();

        $this->assertSame('google_10928374', $user->getId());
        $this->assertSame('Rahim Ali', $user->getName());
        $this->assertSame('rahim@example.com', $user->getEmail());
        $this->assertSame('https://lh3.googleusercontent.com/avatar.jpg', $user->getAvatar());
        $this->assertSame('google', $user->getProvider());
        $this->assertSame('mock_google_access_token', $user->getToken());
    }

    public function testGitHubUserCallbackWithPrivateEmailResolution(): void
    {
        Http::fake([
            'github.com/login/oauth/access_token' => Http::response([
                'access_token' => 'mock_github_token',
                'token_type'   => 'bearer',
                'scope'        => 'user:email',
            ]),
            'api.github.com/user' => Http::response([
                'id'         => 88231,
                'name'       => 'Karim Ullah',
                'login'      => 'karimdev',
                'email'      => null, // Private email in profile
                'avatar_url' => 'https://avatars.githubusercontent.com/u/88231',
            ]),
            'api.github.com/user/emails' => Http::response([
                ['email' => 'backup@nobotech.com', 'primary' => false, 'verified' => true],
                ['email' => 'karim@nobotech.com', 'primary' => true, 'verified' => true],
            ]),
        ]);

        $_GET['code'] = 'github_auth_code';

        $user = SocialLogin::stateless('github')->user();

        $this->assertSame('88231', $user->getId());
        $this->assertSame('Karim Ullah', $user->getName());
        $this->assertSame('karimdev', $user->getNickname());
        $this->assertSame('karim@nobotech.com', $user->getEmail());
        $this->assertSame('https://avatars.githubusercontent.com/u/88231', $user->getAvatar());
        $this->assertSame('github', $user->getProvider());
    }

    public function testFacebookUserCallbackAndMapping(): void
    {
        Http::fake([
            'graph.facebook.com/v19.0/oauth/access_token' => Http::response([
                'access_token' => 'mock_fb_token',
                'token_type'   => 'bearer',
            ]),
            'graph.facebook.com/v19.0/me*' => Http::response([
                'id'      => 'fb_993847',
                'name'    => 'Amina Begum',
                'email'   => 'amina@facebook.com',
                'picture' => [
                    'data' => [
                        'url' => 'https://platform-lookaside.fbsbx.com/avatar.jpg',
                    ],
                ],
            ]),
        ]);

        $_GET['code'] = 'fb_auth_code';

        $user = SocialLogin::stateless('facebook')->user();

        $this->assertSame('fb_993847', $user->getId());
        $this->assertSame('Amina Begum', $user->getName());
        $this->assertSame('amina@facebook.com', $user->getEmail());
        $this->assertSame('https://platform-lookaside.fbsbx.com/avatar.jpg', $user->getAvatar());
        $this->assertSame('facebook', $user->getProvider());
    }

    public function testCustomProviderExtension(): void
    {
        $custom = new class extends AbstractProvider {
            public function name(): string { return 'mock'; }
            protected function authUrl(): string { return 'https://mock.auth/login'; }
            protected function tokenUrl(): string { return 'https://mock.auth/token'; }
            protected function getAccessToken(string $code): string { return 'mock_token'; }
            protected function getUserByToken(string $token): array {
                return ['id' => 'm1', 'name' => 'Mock User', 'email' => 'm@mock.com'];
            }
            protected function mapUser(array $raw, string $token): SocialUser {
                return new SocialUser('m1', 'Mock User', 'm@mock.com', null, null, 'mock', $token, $raw);
            }
        };

        SocialLogin::extend('mock', fn($cfg) => $custom);

        $resolved = SocialLogin::driver('mock');
        $this->assertSame('mock', $resolved->name());

        $_GET['code'] = 'test';
        $user = SocialLogin::stateless('mock')->user();
        $this->assertSame('Mock User', $user->getName());
    }

    public function testOnlyVerifiedEmailsCount(): void
    {
        $google = fn(array $raw) => new SocialUser('1', 'A', 'a@example.com', provider: 'google', raw: $raw);
        $this->assertFalse($google([])->isEmailVerified(), 'Google without email_verified');
        $this->assertFalse($google(['email_verified' => false])->isEmailVerified());
        $this->assertTrue($google(['email_verified' => true])->isEmailVerified());
        $this->assertTrue((new SocialUser('2', 'B', 'b@example.com', provider: 'github'))->isEmailVerified());
        $this->assertFalse((new SocialUser('3', 'C', 'c@example.com', provider: 'custom'))->isEmailVerified());
        $this->assertFalse((new SocialUser('4', 'D', null, provider: 'github'))->isEmailVerified());
    }

    public function testUnverifiedEmailCannotTakeOverAnAccount(): void
    {
        $victimEmail = 'victim_' . bin2hex(random_bytes(4)) . '@example.com';
        $victim = \App\Models\User::create(['name' => 'Victim', 'email' => $victimEmail, 'password' => 'x', 'role' => 'admin']);
        $controller = new \Bongo\Packages\SocialLogin\Controllers\OAuthController();
        $resolve = new \ReflectionMethod($controller, 'resolveOrCreateUser');
        $resolve->setAccessible(true);

        try {
            // An attacker's Google account with the victim's (unverified) email
            $attacker = new SocialUser('g_' . bin2hex(random_bytes(4)), 'Attacker', $victimEmail, provider: 'google', raw: []);
            $user = $resolve->invoke($controller, 'google', $attacker);
            $this->assertNotSame((int) $victim->id, (int) $user->id, 'never signed in as the existing account');
            $this->assertNotSame($victimEmail, $user->email);

            // The real owner with a verified email is linked to the account
            $owner = new SocialUser('g_' . bin2hex(random_bytes(4)), 'Victim', $victimEmail, provider: 'google', raw: ['email_verified' => true]);
            $this->assertSame((int) $victim->id, (int) $resolve->invoke($controller, 'google', $owner)->id);
        } finally {
            \System\Database\DB::table('users')->where('email', $victimEmail)->delete();
            \System\Database\DB::table('users')->where('email', 'like', 'google_g_%@social.local')->delete();
        }
    }

    public function testRoutesUseTheSession(): void
    {
        $router = new \System\Routing\Router();
        $router->loadFile(dirname(__DIR__) . '/routes.php');
        foreach ($router->routes() as $route) {
            $this->assertTrue(in_array('web', $route->middleware, true), "{$route->path} needs the session for the OAuth state");
        }
    }
}
