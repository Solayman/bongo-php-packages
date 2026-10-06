<?php

declare(strict_types=1);

use Bongo\Packages\FeatureFlags\Feature;

if (!function_exists('feature')) {
    /**
     * Determine if the given feature is active.
     */
    function feature(string $name, mixed $scope = null): bool
    {
        return Feature::active($name, $scope);
    }
}

if (!function_exists('feature_when')) {
    /**
     * Execute a callback when a feature is active, or an optional fallback when inactive.
     */
    function feature_when(string $name, callable $active, ?callable $inactive = null, mixed $scope = null): mixed
    {
        return Feature::when($name, $active, $inactive, $scope);
    }
}
