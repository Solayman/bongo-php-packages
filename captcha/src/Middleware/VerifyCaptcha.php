<?php

declare(strict_types=1);

namespace Bongo\Packages\Captcha\Middleware;

use Bongo\Packages\Captcha\Captcha;
use Closure;
use System\Http\Request;
use System\Http\Response;

class VerifyCaptcha
{
    /**
     * Handle an incoming request and ensure captcha is valid.
     */
    public function handle(Request $request, Closure $next, ?string $driver = null): Response
    {
        $inputName = Captcha::inputName($driver);
        $token = $request->input($inputName);
        $clientIp = $request->ip();

        if (!Captcha::verify($token, $clientIp, $driver)) {
            if ($request->isJson() || $request->wantsJson()) {
                return Response::json([
                    'success' => false,
                    'message' => 'Captcha verification failed. Please try again.',
                ], 422);
            }

            if (function_exists('redirect') && function_exists('session')) {
                session()->flash('error', 'Captcha verification failed. Please try again.');
                return redirect()->back();
            }

            return new Response('Captcha verification failed.', 422);
        }

        return $next($request);
    }
}
