<?php

use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;
use Pmb\Acl\Tests\Models\User;
use Pmb\Acl\Tests\TestCase;


beforeEach(function () {
    // set up a fresh user for each test
    $this->user = $this->createUser();
});

it('can assign a role to a user', function () {
    $role = Role::create(['name' => 'Manager', 'slug' => 'manager']);

    $this->user->assignRole('manager');

    expect($this->user->hasRole('manager'))->toBeTrue()
        ->and($this->user->hasRole('admin'))->toBeFalse();
});

it('can sync multiple roles', function () {
    $role1 = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $role2 = Role::create(['name' => 'Author', 'slug' => 'author']);

    $this->user->syncRoles(['editor', 'author']);

    expect($this->user->hasRole(['editor', 'author']))->toBeTrue()
        ->and($this->user->roles()->count())->toBe(2);
});

it('allows user to inherit permissions from their roles (RBAC)', function () {
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = Permission::create(['name' => 'posts.edit', 'label' => 'Edit Posts', 'module' => 'blog']);
    
    $role->permissions()->attach($permission->id);
    $this->user->assignRole('editor');

    expect($this->user->hasPermissionTo('posts.edit'))->toBeTrue();
});

it('allows direct permission assignment to user (PBAC)', function () {
    $permission = Permission::create(['name' => 'users.delete', 'label' => 'Delete Users', 'module' => 'users']);
    
    $this->user->givePermissionTo('users.delete');

    expect($this->user->hasPermissionTo('users.delete'))->toBeTrue();
});

it('resolves wildcard permissions correctly', function () {
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
    $superAdminRole = Role::create(['name' => 'Super Admin', 'slug' => 'super-admin']);
    
    // Assigning the bypass role defined in TestCase configuration
    $this->user->assignRole('super-admin');

    // The user has no direct or inherited permissions attached, but should be authorized
    expect($this->user->hasPermissionTo('any.random.permission'))->toBeTrue();
});