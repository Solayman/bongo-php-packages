<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Exceptions;

use RuntimeException;

class UnauthorizedException extends RuntimeException
{
    protected int $statusCode = 403;

    public static function forRoles(array $roles): static
    {
        $roleList = implode(', ', $roles);
        $exception = new static("User does not have the required role(s): [{$roleList}].", 403);
        $exception->statusCode = 403;
        return $exception;
    }

    public static function forPermissions(array $permissions): static
    {
        $permList = implode(', ', $permissions);
        $exception = new static("User does not have the required permission(s): [{$permList}].", 403);
        $exception->statusCode = 403;
        return $exception;
    }

    public static function notLoggedIn(): static
    {
        $exception = new static("User is not authenticated.", 401);
        $exception->statusCode = 401;
        return $exception;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
