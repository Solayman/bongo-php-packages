<?php

declare(strict_types=1);

namespace Bongo\Packages\Captcha\Middleware;

use Bongo\Packages\Captcha\Captcha;
use System\Http\HttpException;
use System\Http\Request;

/**
 * Rejects the request unless its captcha answer is valid.
 *
 *   'middleware' => ['captcha' => \Bongo\Packages\Captcha\Middleware\VerifyCaptcha::class]   (config/app.php)
 *   Route::post('/contact', [...])->middleware('captcha');            // default driver
 *   Route::post('/register', [...])->middleware('captcha:turnstile'); // a specific driver
 */
class VerifyCaptcha
{
    public function handle(?string $driver = null): void
    {
        $token = Request::input(Captcha::inputName($driver));

        if (Captcha::verify(is_string($token) ? $token : null, Request::ip(), $driver)) {
            return;
        }

        $message = 'Captcha verification failed. Please try again.';
        if (Request::wantsJson()) {
            throw new HttpException(422, $message);   // JSON error for API clients
        }

        flash('error', $message);
        back();
    }
}
