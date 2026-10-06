<?php

declare(strict_types=1);

namespace Bongo\Packages\FeatureFlags;

use Closure;
use System\Database\Model;
use System\Database\QueryBuilder;

class Feature
{
    /** @var array<string, Closure|bool> */
    protected static array $definitions = [];

    /** @var bool */
    protected static bool $fake = false;

    /** @var array<string, bool> */
    protected static array $fakeFlags = [];

    /**
     * Define a feature and its resolution logic.
     */
    public static function define(string $feature, Closure|bool $resolver): void
    {
        self::$definitions[$feature] = $resolver;
    }

    /**
     * Check if a feature is active for the given scope.
     */
    public static function active(string $feature, mixed $scope = null): bool
    {
        if (self::$fake) {
            return self::$fakeFlags[$feature] ?? false;
        }

        $resolvedScope = self::resolveScope($scope);

        // 1. Check in-memory registered definitions
        if (isset(self::$definitions[$feature])) {
            $resolver = self::$definitions[$feature];
            if (is_bool($resolver)) {
                return $resolver;
            }
            return (bool) $resolver($scope);
        }

        // 2. Check Database driver if available
        $driver = function_exists('config') ? config('feature-flags.driver', 'database') : 'database';
        if ($driver === 'database') {
            $table = function_exists('config') ? config('feature-flags.table', 'features') : 'features';

            try {
                // Scope-specific override
                if ($resolvedScope !== '__global__') {
                    $row = (new QueryBuilder($table))
                        ->where('name', $feature)
                        ->where('scope', $resolvedScope)
                        ->first();

                    if ($row) {
                        return self::evaluateValue((string) $row->value, $feature, $resolvedScope);
                    }
                }

                // Global setting
                $global = (new QueryBuilder($table))
                    ->where('name', $feature)
                    ->where('scope', '__global__')
                    ->first();

                if ($global) {
                    return self::evaluateValue((string) $global->value, $feature, $resolvedScope);
                }
            } catch (\Throwable) {
                // Database table might not be migrated yet or unavailable
            }
        }

        // 3. Fallback to config flags
        $configFlags = function_exists('config') ? (array) config('feature-flags.flags', []) : [];
        return (bool) ($configFlags[$feature] ?? false);
    }

    /**
     * Check if a feature is inactive.
     */
    public static function inactive(string $feature, mixed $scope = null): bool
    {
        return !self::active($feature, $scope);
    }

    /**
     * Activate a feature flag for a scope (or globally).
     */
    public static function activate(string $feature, mixed $scope = null): void
    {
        if (self::$fake) {
            self::$fakeFlags[$feature] = true;
            return;
        }

        self::storeValue($feature, self::resolveScope($scope), '1');
    }

    /**
     * Deactivate a feature flag for a scope (or globally).
     */
    public static function deactivate(string $feature, mixed $scope = null): void
    {
        if (self::$fake) {
            self::$fakeFlags[$feature] = false;
            return;
        }

        self::storeValue($feature, self::resolveScope($scope), '0');
    }

    /**
     * Roll out a feature to a specific percentage of users (0 to 100).
     */
    public static function rollout(string $feature, int $percentage, mixed $scope = null): void
    {
        $percentage = max(0, min(100, $percentage));

        if (self::$fake) {
            self::$fakeFlags[$feature] = true;
            return;
        }

        self::storeValue($feature, self::resolveScope($scope), 'percentage:' . $percentage);
    }

    /**
     * Forget/delete a stored feature flag value.
     */
    public static function forget(string $feature, mixed $scope = null): void
    {
        if (self::$fake) {
            unset(self::$fakeFlags[$feature]);
            return;
        }

        $table = function_exists('config') ? config('feature-flags.table', 'features') : 'features';
        $scopeKey = self::resolveScope($scope);

        (new QueryBuilder($table))
            ->where('name', $feature)
            ->where('scope', $scopeKey)
            ->delete();
    }

    /**
     * Execute a callback when feature is active, or optional callback when inactive.
     */
    public static function when(string $feature, callable $active, ?callable $inactive = null, mixed $scope = null): mixed
    {
        if (self::active($feature, $scope)) {
            return $active($scope);
        }

        return $inactive ? $inactive($scope) : null;
    }

    /**
     * Retrieve all defined and database features with their status.
     *
     * @return array<string, bool>
     */
    public static function all(mixed $scope = null): array
    {
        $features = [];

        // In-memory definitions
        foreach (array_keys(self::$definitions) as $name) {
            $features[$name] = self::active($name, $scope);
        }

        // Database rows
        try {
            $table = function_exists('config') ? config('feature-flags.table', 'features') : 'features';
            $rows = (new QueryBuilder($table))->get();
            foreach ($rows as $row) {
                if (!isset($features[$row->name])) {
                    $features[$row->name] = self::active($row->name, $scope);
                }
            }
        } catch (\Throwable) {
            // Ignore if DB not ready
        }

        // Config flags
        $configFlags = function_exists('config') ? (array) config('feature-flags.flags', []) : [];
        foreach ($configFlags as $key => $val) {
            if (!isset($features[$key])) {
                $features[$key] = self::active($key, $scope);
            }
        }

        return $features;
    }

    /**
     * Enable test fake mode with pre-configured flags.
     *
     * @param array<string, bool> $flags
     */
    public static function fake(array $flags = []): void
    {
        self::$fake = true;
        self::$fakeFlags = $flags;
    }

    /**
     * Stop faking and reset state.
     */
    public static function stopFaking(): void
    {
        self::$fake = false;
        self::$fakeFlags = [];
        self::$definitions = [];
    }

    /**
     * Store feature value in database.
     */
    protected static function storeValue(string $feature, string $scope, string $value): void
    {
        $table = function_exists('config') ? config('feature-flags.table', 'features') : 'features';
        $builder = (new QueryBuilder($table))
            ->where('name', $feature)
            ->where('scope', $scope);

        $existing = $builder->first();
        if ($existing) {
            (new QueryBuilder($table))
                ->where('id', $existing->id)
                ->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
        } else {
            (new QueryBuilder($table))->insert([
                'name' => $feature,
                'scope' => $scope,
                'value' => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Evaluate stored value string.
     */
    protected static function evaluateValue(string $value, string $feature, string $scope): bool
    {
        if ($value === '1' || $value === 'true') {
            return true;
        }

        if ($value === '0' || $value === 'false') {
            return false;
        }

        if (str_starts_with($value, 'percentage:')) {
            $percentage = (int) substr($value, strlen('percentage:'));
            if ($percentage <= 0) {
                return false;
            }
            if ($percentage >= 100) {
                return true;
            }

            // Deterministic hash based on feature + scope
            $hash = sprintf('%u', crc32($feature . ':' . $scope));
            return ($hash % 100) < $percentage;
        }

        return false;
    }

    /**
     * Resolve scope into a normalized string key.
     */
    protected static function resolveScope(mixed $scope): string
    {
        if ($scope === null) {
            if (class_exists(\System\Auth\Auth::class) && method_exists(\System\Auth\Auth::class, 'id')) {
                $authId = \System\Auth\Auth::id();
                if ($authId !== null) {
                    return 'user:' . $authId;
                }
            }
            return '__global__';
        }

        if ($scope instanceof Model) {
            return get_class($scope) . ':' . $scope->getKey();
        }

        if (is_numeric($scope)) {
            return 'user:' . $scope;
        }

        return (string) $scope;
    }
}
