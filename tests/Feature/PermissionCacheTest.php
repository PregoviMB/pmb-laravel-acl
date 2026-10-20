<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pmb\Acl\Facades\Acl;
use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;
use Pmb\Acl\Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->user = $this->createUser();
    Cache::flush();
});

it('caches consolidated user permissions to avoid repeated database queries', function () {
    /** @var TestCase $this */
    config(['acl.cache.enabled' => true]);

    $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);
    $permission = Permission::create(['name' => 'articles.create', 'label' => 'Create Articles', 'module' => 'blog']);
    $role->permissions()->attach($permission->id);
    $this->user->assignRole('editor');

    // First call: executes queries and caches result
    $firstPermissions = $this->user->getAllPermissions();
    expect($firstPermissions)->toHaveCount(1)
        ->and(Cache::has($this->user->getAclUserCacheKey()))->toBeTrue();

    // Reset query log
    DB::enableQueryLog();

    // Second call: must hit cache, no SQL queries for permissions
    $cachedPermissions = $this->user->getAllPermissions();
    expect($cachedPermissions)->toHaveCount(1)
        ->and($cachedPermissions->first()->name)->toBe('articles.create');

    $queries = DB::getQueryLog();
    expect(count($queries))->toBe(0);
});

it('invalidates cache when roles or permissions are updated on user', function () {
    /** @var TestCase $this */
    config(['acl.cache.enabled' => true]);

    $p1 = Permission::create(['name' => 'p1', 'label' => 'P1', 'module' => 'test']);
    $p2 = Permission::create(['name' => 'p2', 'label' => 'P2', 'module' => 'test']);

    $this->user->givePermissionTo('p1');
    expect($this->user->getAllPermissions())->toHaveCount(1)
        ->and(Cache::has($this->user->getAclUserCacheKey()))->toBeTrue();

    // Adding permission invalidates user cache automatically
    $this->user->givePermissionTo('p2');
    expect(Cache::has($this->user->getAclUserCacheKey()))->toBeFalse();

    // Re-evaluating populates cache with updated permissions
    expect($this->user->getAllPermissions())->toHaveCount(2)
        ->and(Cache::has($this->user->getAclUserCacheKey()))->toBeTrue();

    // Revoking permission invalidates user cache
    $this->user->revokePermissionTo('p1');
    expect(Cache::has($this->user->getAclUserCacheKey()))->toBeFalse()
        ->and($this->user->getAllPermissions())->toHaveCount(1);
});

it('can flush cache using Acl facade', function () {
    /** @var TestCase $this */
    config(['acl.cache.enabled' => true]);

    $permission = Permission::create(['name' => 'invoices.view', 'label' => 'Invoices', 'module' => 'billing']);
    $this->user->givePermissionTo('invoices.view');

    $this->user->getAllPermissions();
    expect(Cache::has($this->user->getAclUserCacheKey()))->toBeTrue();

    Acl::flushUserCache($this->user);
    expect(Cache::has($this->user->getAclUserCacheKey()))->toBeFalse();
});

