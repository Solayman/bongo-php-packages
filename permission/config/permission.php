<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The model that represents your application's users. Make sure this
    | model uses the `Bongo\Packages\Permission\Traits\HasRoles` trait.
    |
    */
    'user_model' => env('AUTH_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Super Admin Role
    |--------------------------------------------------------------------------
    |
    | When a user possesses this role, all permission checks automatically
    | return true (bypassing granular checks).
    |
    */
    'super_admin_role' => env('PERMISSION_SUPER_ADMIN_ROLE', 'super-admin'),

    /*
    |--------------------------------------------------------------------------
    | Default Guard
    |--------------------------------------------------------------------------
    |
    | The default authentication guard name for roles and permissions.
    |
    */
    'default_guard' => env('PERMISSION_DEFAULT_GUARD', 'web'),
];
