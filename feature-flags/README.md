# Bongo Feature Flags Package

A zero-dependency feature flagging, canary rollout, and user targeting system for the Bongo PHP framework.

## Features
- **Flexible Drivers**: Store toggles in the database or in-memory arrays.
- **Percentage Rollouts**: Roll out new features to a percentage of users (e.g. 10%, 25%, 50%) using deterministic hashing.
- **Scope & User Targeting**: Target flags globally, per user, or per custom domain model.
- **Custom Resolvers**: Define closures with business logic (e.g. `$user->isBetaTester()`).
- **CLI Commands**: Manage flags from the terminal (`php bongo feature:list`, `php bongo feature:toggle`).
- **Test Faking**: `Feature::fake(['new-ui' => true])` for fast, isolated testing without database state.
- **Global Helpers**: `feature('dark-mode')` and `feature_when('canary', $activeCallback, $fallback)`.

## Installation & Configuration
Publish configuration and run migrations:
```bash
php bongo migrate
```

Configuration in `config/feature-flags.php`:
```php
return [
    'driver' => env('FEATURE_FLAGS_DRIVER', 'database'),
    'table' => 'features',
    'flags' => [
        // 'dark-mode' => true,
    ],
];
```

## Basic Usage

```php
use Bongo\Packages\FeatureFlags\Feature;

// Define custom resolution logic
Feature::define('beta-access', function ($user) {
    return $user && str_ends_with($user->email, '@company.com');
});

// Check if active
if (feature('beta-access', $user)) {
    // Show beta experience
}

// Database-backed toggling
Feature::activate('new-checkout');
Feature::deactivate('new-checkout');

// Target specific user
Feature::activate('new-checkout', $vipUser);

// Percentage canary rollout (30% of traffic)
Feature::rollout('redesigned-header', 30);

// CLI Management
// php bongo feature:list
// php bongo feature:toggle new-checkout
// php bongo feature:toggle new-checkout --scope=user:42 --off
```
