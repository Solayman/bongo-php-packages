# impersonate

Let an admin sign in as another user (to see what they see), then switch back.

```bash
php bongo add impersonate
```

## Who may impersonate (required)

Impersonation is **denied by default**. Allow it in one of two ways:

```php
// app/bootstrap.php: a Gate rule
Gate::define('impersonate', fn($admin, $target) => $admin->role === 'admin');

// or on your User model
public function canImpersonate(): bool { return $this->role === 'admin'; }
public function canBeImpersonated(): bool { return $this->role !== 'admin'; }   // optional
```

Other admins (users who may impersonate) are never targets unless their model returns `canBeImpersonated() === true`.

## Routes

Both need a signed-in user and a CSRF token (`web` + `auth`):

```html
<form method="POST" action="/impersonate/{{ $user->id }}">@csrf <button>Sign in as {{ $user->name }}</button></form>
<form method="POST" action="/impersonate/leave">@csrf <button>Back to my account</button></form>
```

`{!! \Bongo\Packages\Impersonate\Impersonate::renderBanner() !!}` shows a "you are impersonating" bar with a leave button.

Config (`config/impersonate.php`): `guard` (default `web`), `redirect_to`, `leave_redirect_to`. Every start, refusal and leave is logged.
