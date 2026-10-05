# Bongo Captcha Package (`captcha`)

[![Tests](https://img.shields.io/badge/tests-passing-brightgreen.svg)](#testing)
[![PHP](https://img.shields.io/badge/php-8.0%2B-blue.svg)](https://php.net)
[![Dependencies](https://img.shields.io/badge/dependencies-0%20required-orange.svg)](#zero-dependencies)

Universal anti-bot & human verification suite for the **Bongo PHP Framework**. Supports Cloudflare Turnstile, Google reCAPTCHA (v2 and v3), hCaptcha, and **zero-dependency pure-PHP math and GD image captchas** that work offline without third-party API keys.

---

## 🚀 Features

- **Multi-Driver Architecture:**
  - `turnstile`: Cloudflare Turnstile (frictionless, privacy-preserving modern anti-bot).
  - `recaptcha`: Google reCAPTCHA v2 (checkbox) & v3 (score-based invisible challenge).
  - `hcaptcha`: Intuitive hCaptcha challenge protection.
  - `math`: Pure-PHP session-backed math challenges (`5 + 8 = ?`), zero external API calls.
  - `image`: Pure-PHP GD distorted challenge images rendered inline via Base64 data URI (no dedicated route needed).
- **Security Hardened:**
  - Automated cryptographic replay protection (single-use token/answer consumption).
  - 10-minute session expiry on offline challenges.
  - Case-insensitive image captcha matching.
  - reCAPTCHA v3 configurable confidence threshold (`score_threshold: 0.5`).
- **Developer Experience:**
  - Global helpers: `captcha_render()`, `captcha_verify()`, `captcha_script()`.
  - Template directives: `@captcha` and `@captchascript`.
  - HTTP middleware: `VerifyCaptcha`.

---

## 📦 Installation

```bash
php bongo add captcha
```

---

## ⚙️ Configuration (`config/captcha.php`)

```php
return [
    'driver' => env('CAPTCHA_DRIVER', 'turnstile'), // turnstile, recaptcha, hcaptcha, math, image

    'turnstile' => [
        'site_key'   => env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    ],

    'recaptcha' => [
        'site_key'        => env('RECAPTCHA_SITE_KEY', ''),
        'secret_key'      => env('RECAPTCHA_SECRET_KEY', ''),
        'version'         => env('RECAPTCHA_VERSION', 'v2'), // 'v2' or 'v3'
        'score_threshold' => 0.5,
    ],

    'math' => [
        'session_key' => 'bongo_captcha_math',
        'min'         => 1,
        'max'         => 20,
    ],

    'image' => [
        'session_key' => 'bongo_captcha_image',
        'width'       => 150,
        'height'      => 45,
        'length'      => 5,
    ],
];
```

---

## 💡 Usage

### In Views & Templates

```html
<form method="POST" action="/login">
    {!! csrf_field() !!}
    <input type="email" name="email" placeholder="Email" required />
    <input type="password" name="password" placeholder="Password" required />

    <!-- Render Captcha Widget -->
    @captcha

    <button type="submit">Log In</button>

    <!-- Include Script (for Turnstile / reCAPTCHA / hCaptcha) -->
    @captchascript
</form>
```

### In Controllers

```php
use Bongo\Packages\Captcha\Captcha;

public function login(Request $request)
{
    if (!Captcha::verify()) {
        return back()->with('error', 'Security captcha verification failed.');
    }

    // Authenticate user...
}
```

### Using Middleware

```php
use Bongo\Packages\Captcha\Middleware\VerifyCaptcha;

Route::post('/contact', [ContactController::class, 'submit'])->middleware(VerifyCaptcha::class);
```
