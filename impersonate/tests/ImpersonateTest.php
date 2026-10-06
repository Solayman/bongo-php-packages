<?php

namespace Bongo\Packages\Impersonate\Tests;

use Bongo\Packages\Impersonate\Impersonate;
use System\Auth\Auth;
use System\Testing\TestCase;

class ImpersonateTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = [];
    }

    public function testTakeAndLeaveLifecycle(): void
    {
        $admin = (object) ['id' => 1, 'name' => 'Admin User', 'email' => 'admin@example.com'];
        $customer = (object) ['id' => 99, 'name' => 'John Customer', 'email' => 'john@example.com'];

        // Sign in as admin
        Auth::guard('web')->login($admin);
        $this->assertSame(1, Auth::guard('web')->id());
        $this->assertFalse(Impersonate::isImpersonating());

        // Take customer impersonation
        $took = Impersonate::take($customer, 'web');
        $this->assertTrue($took);
        $this->assertTrue(Impersonate::isImpersonating());
        $this->assertSame(1, Impersonate::impersonatorId());
        $this->assertSame(99, Auth::guard('web')->id());

        // Banner should render
        $banner = Impersonate::renderBanner();
        $this->assertTrue(str_contains($banner, 'John Customer'));
        $this->assertTrue(str_contains($banner, '/impersonate/leave'));

        // Leave impersonation
        $left = Impersonate::leave('web');
        $this->assertTrue($left);
        $this->assertFalse(Impersonate::isImpersonating());
        $this->assertNull(Impersonate::impersonatorId());
        $this->assertSame(1, Auth::guard('web')->id());

        // Banner is empty when not impersonating
        $this->assertSame('', Impersonate::renderBanner());
    }

    public function testCannotImpersonateSelfOrWhenNotLoggedIn(): void
    {
        Auth::guard('web')->logout();
        $this->assertFalse(Impersonate::take(2, 'web'));

        $user = (object) ['id' => 5, 'name' => 'User Five'];
        Auth::guard('web')->login($user);

        // Cannot impersonate self
        $this->assertFalse(Impersonate::take($user, 'web'));
        $this->assertFalse(Impersonate::take(5, 'web'));
    }
}
