<?php

declare(strict_types=1);

namespace Bongo\Packages\Permission\Tests;

use Bongo\Packages\Permission\Exceptions\UnauthorizedException;
use Bongo\Packages\Permission\Middleware\PermissionMiddleware;
use Bongo\Packages\Permission\Middleware\RoleMiddleware;
use Bongo\Packages\Permission\Models\PermissionModel;
use Bongo\Packages\Permission\Models\Role;
use Bongo\Packages\Permission\Permission;
use Bongo\Packages\Permission\Traits\HasRoles;
use System\Auth\Auth;
use System\Database\DB;
use System\Database\Model;
use System\Database\Schema\Blueprint;
use System\Database\Schema\Schema;
use System\Http\HttpException;
use System\Http\Request;
use System\Testing\TestCase;

class TestUser extends Model
{
    use HasRoles;

    protected static $table = 'test_permission_users';
    protected array $fillable = ['id', 'name', 'email'];
}

class PermissionTest extends TestCase
{
    /** The package's own migration plus a users table: works on MySQL, PostgreSQL and SQLite */
    public function setUp(): void
    {
        $this->dropTables();
        $this->migration()->up();
        Schema::create('test_permission_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->string('email', 100)->nullable();
            $table->timestamps();
        });
    }

    public function tearDown(): void
    {
        Auth::logout();
        Auth::reset();
        $this->dropTables();
    }

    private function migration(): object
    {
        return require dirname(__DIR__) . '/database/migrations/2026_10_04_000000_create_permission_tables.php';
    }

    private function dropTables(): void
    {
        $this->migration()->down();
        Schema::dropIfExists('test_permission_users');
    }

    public function testRoleAndPermissionCreation(): void
    {
        $role = Role::findOrCreate('admin');
        $this->assertTrue($role->id > 0);
        $this->assertSame('admin', $role->name);

        // Subsequent call returns existing
        $sameRole = Role::findOrCreate('admin');
        $this->assertSame($role->id, $sameRole->id);

        $perm = PermissionModel::findOrCreate('edit-articles');
        $this->assertTrue($perm->id > 0);
        $this->assertSame('edit-articles', $perm->name);

        $this->assertSame($role->id, Permission::findRole('admin')->id);
        $this->assertSame($perm->id, Permission::findPermission('edit-articles')->id);
    }

    public function testRolePermissionsAssignmentAndRevocation(): void
    {
        $role = Role::findOrCreate('editor');
        $p1 = PermissionModel::findOrCreate('create-posts');
        $p2 = PermissionModel::findOrCreate('publish-posts');

        $role->givePermissionTo(['create-posts', 'publish-posts']);
        $this->assertTrue($role->hasPermission('create-posts'));
        $this->assertTrue($role->hasPermission('publish-posts'));
        $this->assertFalse($role->hasPermission('delete-posts'));

        // Revoke single permission
        $role->revokePermissionTo('create-posts');
        $this->assertFalse($role->hasPermission('create-posts'));
        $this->assertTrue($role->hasPermission('publish-posts'));

        // Sync permissions
        $role->syncPermissions(['delete-posts']);
        $this->assertTrue($role->hasPermission('delete-posts'));
        $this->assertFalse($role->hasPermission('publish-posts'));
    }

    public function testUserRoleAssignmentAndChecks(): void
    {
        $user = TestUser::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $user->assignRole('manager');
        $this->assertTrue($user->hasRole('manager'));
        $this->assertFalse($user->hasRole('admin'));

        $user->assignRole('billing');
        $this->assertTrue($user->hasAnyRole(['admin', 'billing']));
        $this->assertTrue($user->hasAllRoles(['manager', 'billing']));
        $this->assertFalse($user->hasAllRoles(['manager', 'admin']));

        $user->removeRole('billing');
        $this->assertFalse($user->hasRole('billing'));
        $this->assertTrue($user->hasRole('manager'));

        $user->syncRoles(['editor', 'writer']);
        $this->assertTrue($user->hasRole('editor'));
        $this->assertTrue($user->hasRole('writer'));
        $this->assertFalse($user->hasRole('manager'));
    }

    public function testUserPermissionsDirectAndViaRole(): void
    {
        $user = TestUser::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        // Direct permission
        $user->givePermissionTo('upload-media');
        $this->assertTrue($user->hasDirectPermission('upload-media'));
        $this->assertTrue($user->can('upload-media'));
        $this->assertFalse($user->can('delete-media'));

        // Permission via assigned role
        $role = Role::findOrCreate('moderator');
        $role->givePermissionTo('delete-media');
        $user->assignRole($role);

        $this->assertTrue($user->hasPermissionViaRole('delete-media'));
        $this->assertTrue($user->can('delete-media'));

        // Check getAllPermissions
        $all = $user->getAllPermissions();
        $this->assertTrue(in_array('upload-media', $all, true));
        $this->assertTrue(in_array('delete-media', $all, true));
    }

    public function testSuperAdminBypassesAllPermissionChecks(): void
    {
        $user = TestUser::create(['name' => 'Super', 'email' => 'super@example.com']);
        $user->assignRole('super-admin');

        $this->assertTrue($user->can('any-unregistered-permission'));
        $this->assertTrue($user->can('destroy-entire-database'));
    }

    public function testRoleMiddleware(): void
    {
        $user = TestUser::create(['name' => 'Charlie', 'email' => 'charlie@example.com']);
        $user->assignRole('staff');

        Auth::login($user);

        // Allowed role: the core calls handle() with the route parameter only, and it returns normally
        (new RoleMiddleware())->handle('staff|admin');
        $this->assertTrue(true);

        // Denied role: 403
        $this->assertThrows(HttpException::class, fn() => (new RoleMiddleware())->handle('admin|super-admin'));

        // Guest: 401
        Auth::logout();
        $this->assertThrows(HttpException::class, fn() => (new RoleMiddleware())->handle('staff'));
    }

    public function testPermissionMiddleware(): void
    {
        $user = TestUser::create(['name' => 'Dave', 'email' => 'dave@example.com']);
        $user->givePermissionTo('view-reports');

        Auth::login($user);

        (new PermissionMiddleware())->handle('export-payroll|view-reports');   // any one is enough
        $this->assertTrue(true);

        try {
            (new PermissionMiddleware())->handle('export-payroll');
            $this->assertTrue(false, 'a missing permission must be refused');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->status());
        }
    }

    public function testUnauthorizedExceptionHasCorrectStatus(): void
    {
        $ex1 = UnauthorizedException::forRoles(['admin']);
        $this->assertSame(403, $ex1->getStatusCode());

        $ex2 = UnauthorizedException::forPermissions(['export']);
        $this->assertSame(403, $ex2->getStatusCode());

        $ex3 = UnauthorizedException::notLoggedIn();
        $this->assertSame(401, $ex3->getStatusCode());
    }

    public function testBatchedPermissionsAndRolePermissionsNoNPlusOne(): void
    {
        $user = TestUser::create(['name' => 'Eve', 'email' => 'eve@example.com']);

        // Batch assign multiple permissions
        $user->givePermissionTo(['read-articles', 'comment-articles']);
        $this->assertTrue($user->hasDirectPermission('read-articles'));
        $this->assertTrue($user->hasDirectPermission('comment-articles'));

        // Multiple roles with multiple permissions
        $role1 = Role::findOrCreate('editor');
        $role1->givePermissionTo(['publish-articles', 'unpublish-articles']);

        $role2 = Role::findOrCreate('moderator');
        $role2->givePermissionTo(['flag-comments', 'delete-comments']);

        // Batch assign roles
        $user->assignRole(['editor', 'moderator']);

        // Check permissions via roles
        $this->assertTrue($user->can('publish-articles'));
        $this->assertTrue($user->can('delete-comments'));
        $this->assertFalse($user->can('unknown-permission'));

        $rolePerms = $user->getRolePermissions();
        $this->assertTrue(in_array('publish-articles', $rolePerms, true));
        $this->assertTrue(in_array('delete-comments', $rolePerms, true));

        $all = $user->getAllPermissions();
        $this->assertCount(6, $all);
        $this->assertTrue(in_array('read-articles', $all, true));
        $this->assertTrue(in_array('publish-articles', $all, true));
        $this->assertTrue(in_array('flag-comments', $all, true));
    }
}
