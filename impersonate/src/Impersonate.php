<?php

namespace Bongo\Packages\Impersonate;

use System\Auth\Auth;
use System\Http\Session;
use System\Log\Logger;

class Impersonate
{
    public const SESSION_KEY = '_bongo_impersonator_id';
    public const GUARD_KEY   = '_bongo_impersonator_guard';

    public static function take(int|object $user, ?string $guard = 'web'): bool
    {
        $guard = $guard ?? 'web';
        $currentAdmin = Auth::guard($guard)->user();
        if (!$currentAdmin) {
            return false;
        }

        // Prevent nested impersonation
        if (static::isImpersonating()) {
            return false;
        }

        $adminId = is_object($currentAdmin) ? ($currentAdmin->id ?? 1) : 1;
        $targetUser = is_object($user) ? $user : static::findUser($user, $guard);

        if (!$targetUser) {
            return false;
        }

        $targetId = is_object($targetUser) ? ($targetUser->id ?? (int) $user) : (int) $user;

        // Cannot impersonate oneself
        if ((int) $adminId === (int) $targetId) {
            return false;
        }

        // Authorization checks
        if (is_object($currentAdmin) && method_exists($currentAdmin, 'canImpersonate') && !$currentAdmin->canImpersonate()) {
            return false;
        }
        if (is_object($targetUser) && method_exists($targetUser, 'canBeImpersonated') && !$targetUser->canBeImpersonated()) {
            return false;
        }

        // Store impersonator in session
        Session::put(static::SESSION_KEY, (int) $adminId);
        Session::put(static::GUARD_KEY, $guard);

        // Switch authenticated user
        Auth::guard($guard)->login($targetUser);

        try {
            Logger::instance()->info("Admin {$adminId} started impersonating user {$targetId}", [
                'admin_id'  => $adminId,
                'target_id' => $targetId,
                'guard'     => $guard,
            ]);
        } catch (\Throwable $e) {
            // Logger silenced
        }

        return true;
    }

    public static function leave(?string $guard = null): bool
    {
        if (!static::isImpersonating()) {
            return false;
        }

        $adminId = static::impersonatorId();
        $guard = $guard ?? (Session::get(static::GUARD_KEY) ?? 'web');

        Session::forget(static::SESSION_KEY);
        Session::forget(static::GUARD_KEY);

        if ($adminId) {
            $adminUser = static::findUser($adminId, $guard);
            if ($adminUser) {
                Auth::guard($guard)->login($adminUser);
            } else {
                Auth::guard($guard)->logout();
            }
        }

        try {
            Logger::instance()->info("Admin {$adminId} left impersonation mode", [
                'admin_id' => $adminId,
                'guard'    => $guard,
            ]);
        } catch (\Throwable $e) {
            // Logger silenced
        }

        return true;
    }

    public static function isImpersonating(): bool
    {
        return !empty(Session::get(static::SESSION_KEY));
    }

    public static function impersonatorId(): ?int
    {
        $id = Session::get(static::SESSION_KEY);
        return $id !== null ? (int) $id : null;
    }

    public static function renderBanner(): string
    {
        if (!static::isImpersonating()) {
            return '';
        }

        $guard = Session::get(static::GUARD_KEY) ?? 'web';
        $user = Auth::guard($guard)->user();
        $name = is_object($user) ? ($user->name ?? $user->email ?? 'User #' . $user->id) : 'User';
        $safeName = htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8');
        $adminId = (int) (static::impersonatorId() ?? 0);
        $csrfField = function_exists('csrf_field') ? csrf_field() : '';

        $actionUrl = '/impersonate/leave';

        return <<<HTML
<div id="bongo-impersonate-banner" style="position:fixed;top:0;left:0;right:0;z-index:999999;background:#b91c1c;color:#fff;padding:8px 16px;font-family:sans-serif;font-size:13px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 10px rgba(0,0,0,0.3);">
    <div style="display:flex;align-items:center;gap:8px;">
        <span style="background:rgba(255,255,255,0.2);padding:2px 6px;border-radius:4px;font-weight:bold;text-transform:uppercase;font-size:11px;">Impersonating</span>
        <span>You are currently signed in as <strong>{$safeName}</strong> (Original Admin ID: {$adminId})</span>
    </div>
    <form method="POST" action="{$actionUrl}" style="margin:0;">
        {$csrfField}
        <button type="submit" style="background:#fff;color:#b91c1c;border:none;padding:4px 12px;border-radius:4px;font-weight:bold;font-size:12px;cursor:pointer;">
            Leave Impersonation &rarr;
        </button>
    </form>
</div>
<style>body { margin-top: 36px !important; }</style>
HTML;
    }

    protected static function findUser(int $id, string $guard)
    {
        $modelClass = config("auth.guards.{$guard}.model") ?? config('auth.providers.users.model') ?? 'App\\Models\\User';
        if (class_exists($modelClass) && method_exists($modelClass, 'find')) {
            return $modelClass::find($id);
        }

        // Generic object fallback
        return (object) ['id' => $id, 'name' => "User {$id}", 'email' => "user{$id}@example.com"];
    }
}
