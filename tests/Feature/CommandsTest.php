<?php

use Illuminate\Support\Facades\Artisan;
use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;
use Pmb\Acl\Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->user = $this->createUser();
});

it('syncs permissions catalog from config using acl:sync', function () {
    /** @var TestCase $this */
    config([
        'acl.modules' => [
            'orders' => [
                'orders.create' => 'Create Order',
                'orders.delete' => 'Delete Order',
            ],
            'invoices' => [
                'invoices.view' => 'View Invoice',
            ],
        ],
    ]);

    expect(Permission::count())->toBe(0);

    $exitCode = Artisan::call('acl:sync');
    expect($exitCode)->toBe(0)
        ->and(Permission::count())->toBe(3);

    expect(Permission::where('name', 'orders.create')->first()->label)->toBe('Create Order');

    // Test prune option
    config([
        'acl.modules' => [
            'orders' => [
                'orders.create' => 'Create Order',
            ],
        ],
    ]);

    Artisan::call('acl:sync', ['--prune' => true]);
    expect(Permission::count())->toBe(1)
        ->and(Permission::where('name', 'orders.create')->exists())->toBeTrue()
        ->and(Permission::where('name', 'invoices.view')->exists())->toBeFalse();
});

it('displays user roles and permissions using acl:show', function () {
    /** @var TestCase $this */
    $role = Role::create(['name' => 'Editor', 'slug' => 'editor', 'label' => 'Content Editor']);
    $pRole = Permission::create(['name' => 'posts.edit', 'label' => 'Edit Posts', 'module' => 'blog']);
    $pDirect = Permission::create(['name' => 'direct.access', 'label' => 'Direct Access', 'module' => 'security']);

    $role->permissions()->attach($pRole->id);
    $this->user->assignRole('editor');
    $this->user->givePermissionTo('direct.access');

    $exitCode = Artisan::call('acl:show', ['user' => $this->user->id]);
    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('Editor')
        ->toContain('posts.edit')
        ->toContain('direct.access')
        ->toContain('Via Role (RBAC)')
        ->toContain('Direct (PBAC)');
});

