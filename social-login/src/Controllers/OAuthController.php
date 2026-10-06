<?php

declare(strict_types=1);

namespace Bongo\Packages\SocialLogin\Controllers;

use Bongo\Packages\SocialLogin\SocialLogin;
use System\Auth\Auth;
use System\Database\Schema\Schema;
use System\Events\Event;
use System\Http\Request;
use System\Log\Logger;
use Throwable;

class OAuthController
{
    /**
     * Redirect the user to the OAuth provider's authentication screen.
     */
    public function redirect(Request $request, string $provider): mixed
    {
        return $this->driver($provider)->redirect();
    }

    /** An unknown provider in the URL is a 404, not a server error */
    protected function driver(string $provider): \Bongo\Packages\SocialLogin\Contracts\ProviderInterface
    {
        try {
            return SocialLogin::driver($provider);
        } catch (\InvalidArgumentException $e) {
            abort(404);
        }
    }

    /**
     * Handle the OAuth provider callback.
     */
    public function callback(Request $request, string $provider): mixed
    {
        try {
            $socialUser = $this->driver($provider)->user();

            if (class_exists(Event::class)) {
                Event::dispatch('social.login', [
                    'provider' => $provider,
                    'user'     => $socialUser,
                ]);
            }

            $user = $this->resolveOrCreateUser($provider, $socialUser);

            if ($user && class_exists(Auth::class)) {
                $guard = config('social.guard');
                if ($guard) {
                    Auth::guard($guard)->login($user);
                } else {
                    Auth::login($user);
                }
            }

            $dest = config('social.redirect_after_login', '/');
            return redirect($dest);
        } catch (\System\Http\HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Details (token exchange errors, provider responses) go to the log, not to the visitor
            Logger::instance()->warning('Social login failed for {provider}: ' . $e->getMessage(), ['provider' => $provider]);
            if (function_exists('flash')) {
                flash('error', __('social::Sign-in with this provider failed. Please try again.'));
            }
            return redirect('/login');
        }
    }

    protected function resolveOrCreateUser(string $provider, $socialUser): ?object
    {
        $modelClass = config('social.model', \App\Models\User::class);
        if (!class_exists($modelClass)) {
            return null;
        }

        $email = $socialUser->getEmail();
        $providerId = $socialUser->getId();
        $user = null;

        // 1. Find by provider and provider_id if column exists
        if (class_exists(Schema::class) && Schema::hasColumn('users', 'provider_id')) {
            $user = $modelClass::where('provider', $provider)
                ->where('provider_id', $providerId)
                ->first();
        }

        // 2. Find by email: only an email the provider verified may sign in to an existing account
        $emailVerified = $socialUser->isEmailVerified();
        if (!$user && !empty($email) && $emailVerified && config('social.link_by_verified_email', true)) {
            $user = $modelClass::where('email', $email)->first();

            // Link existing account with social provider info
            if ($user && class_exists(Schema::class) && Schema::hasColumn('users', 'provider')) {
                $user->provider = $provider;
                $user->provider_id = $providerId;
                if (!empty($socialUser->getAvatar())) {
                    $user->avatar = $socialUser->getAvatar();
                }
                $user->save();
            }
        }

        // 3. Auto-create new user if enabled
        if (!$user && config('social.auto_create_user', true)) {
            $data = [
                'name'              => $socialUser->getName() ?: "{$provider}_user",
                // An unverified email is not used: it may belong to someone else's account
                'email'             => $emailVerified ? $email : "{$provider}_{$providerId}@social.local",
                'password'          => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                'email_verified_at' => $emailVerified ? date('Y-m-d H:i:s') : null,
            ];
            if (class_exists(Schema::class) && Schema::hasColumn('users', 'role')) {
                $data['role'] = 'customer';
            }

            if (class_exists(Schema::class) && Schema::hasColumn('users', 'provider')) {
                $data['provider'] = $provider;
                $data['provider_id'] = $providerId;
                $data['avatar'] = $socialUser->getAvatar();
            }

            $user = $modelClass::create($data);
        }

        return $user;
    }
}
