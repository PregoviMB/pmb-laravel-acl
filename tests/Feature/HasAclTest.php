<?php

use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;
use Pmb\Acl\Tests\Models\User;
use Pmb\Acl\Tests\TestCase;

beforeEach(function () {
    // set up a fresh user for each test
    /** @var TestCase $this */
    $this->user = $this->createUser();
});

it('can assign a role to a user', function () {
    $role = Role::create(['name' => 'Manager', 'slug' => 'manager']);

    /** @var TestCase $this */
    $this->user->assignRole('manager');

    expect($this->user->hasRole('manager'))->toBeTrue()
        ->and($this->user->hasRole('admin'))->toBeFalse();
});

it('can sync multiple roles', function () {
    $role1 = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $role2 = Role::create(['name' => 'Author', 'slug' => 'author']);

    /** @var TestCase $this */
    $this->user->syncRoles(['Editor', 'Author']);

    expect($this->user->hasRole(['editor', 'author']))->toBeTrue()
        ->and($this->user->roles()->count())->toBe(2);
});

it('allows user to inherit permissions from their roles (RBAC)', function () {
    /** @var TestCase $this */
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = Permission::create(['name' => 'posts.edit', 'label' => 'Edit Posts', 'module' => 'blog']);
    
    $role->permissions()->attach($permission->id);
    $this->user->assignRole('editor');

    expect($this->user->hasPermissionTo('posts.edit'))->toBeTrue();
});

test('user without role or direct permission does not have access', function () {
    /** @var TestCase $this */
    expect($this->user->hasPermissionTo('posts.edit'))->toBeFalse();
});


it('allows direct permission assignment to user (PBAC)', function () {
    /** @var TestCase $this */
    $permission = Permission::create(['name' => 'users.delete', 'label' => 'Delete Users', 'module' => 'users']);
    
    $this->user->givePermissionTo('users.delete');

    expect($this->user->hasPermissionTo('users.delete'))->toBeTrue();
});

it('resolves wildcard permissions correctly', function () {
    /** @var TestCase $this */
    // The user has a wildcard permission giving full access to the posts module
    $permission = Permission::create(['name' => 'posts.*', 'label' => 'All Post Actions', 'module' => 'blog']);
    $this->user->givePermissionTo('posts.*');

    // The user should implicitly have these specific permissions
    expect($this->user->hasPermissionTo('posts.create'))->toBeTrue()
        ->and($this->user->hasPermissionTo('posts.edit'))->toBeTrue()
        ->and($this->user->hasPermissionTo('posts.draft.comments.delete.edit'))->toBeTrue()
        ->and($this->user->hasPermissionTo('users.view'))->toBeFalse(); // Different module
});

it('bypasses permission checks for super admins', function () {
    /** @var TestCase $this */
    $superAdminRole = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
    
    // Assigning the bypass role defined in TestCase configuration
    $this->user->assignRole('super-admin');

    // The user has no direct or inherited permissions attached, but should be authorized
    expect($this->user->hasPermissionTo('any.random.permission'))->toBeTrue();
});

it('merges direct permissions and role permissions without duplicates in getAllPermissions and all_permissions accessor', function () {
    /** @var TestCase $this */
    $permDirect = Permission::create(['name' => 'direct.perm', 'label' => 'Direct', 'module' => 'general']);
    $permShared = Permission::create(['name' => 'shared.perm', 'label' => 'Shared', 'module' => 'general']);
    $permRole = Permission::create(['name' => 'role.perm', 'label' => 'Role', 'module' => 'general']);

    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $role->permissions()->attach([$permShared->id, $permRole->id]);

    $this->user->assignRole('editor');
    $this->user->givePermissionTo('direct.perm', 'shared.perm');

    // Test direct permissions relation
    expect($this->user->directPermissions()->count())->toBe(2);

    // Test consolidated permissions
    $all = $this->user->getAllPermissions();
    expect($all)->toHaveCount(3)
        ->and($all->pluck('name')->all())->toContain('direct.perm', 'shared.perm', 'role.perm');

    // Test dynamic accessor $user->all_permissions
    expect($this->user->all_permissions)->toHaveCount(3)
        ->and($this->user->all_permissions->pluck('name')->all())->toContain('direct.perm', 'shared.perm', 'role.perm');

    // Check hasPermissionTo works for all merged permissions
    expect($this->user->hasPermissionTo('direct.perm'))->toBeTrue()
        ->and($this->user->hasPermissionTo('role.perm'))->toBeTrue()
        ->and($this->user->hasPermissionTo('shared.perm'))->toBeTrue();
});

it('can sync direct permissions using syncPermissions', function () {
    /** @var TestCase $this */
    $p1 = Permission::create(['name' => 'posts.view', 'label' => 'View Posts', 'module' => 'posts']);
    $p2 = Permission::create(['name' => 'posts.update', 'label' => 'Update Posts', 'module' => 'posts']);
    $p3 = Permission::create(['name' => 'posts.delete', 'label' => 'Delete Posts', 'module' => 'posts']);

    $this->user->givePermissionTo('posts.view', 'posts.delete');
    expect($this->user->permissions()->count())->toBe(2);

    $this->user->syncPermissions(['posts.view', 'posts.update']);

    expect($this->user->hasPermissionTo('posts.view'))->toBeTrue()
        ->and($this->user->hasPermissionTo('posts.update'))->toBeTrue()
        ->and($this->user->hasPermissionTo('posts.delete'))->toBeFalse();
});
