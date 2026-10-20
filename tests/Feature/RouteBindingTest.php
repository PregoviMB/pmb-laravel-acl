<?php

use Illuminate\Support\Str;
use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;
use Pmb\Acl\Tests\TestCase;

it('supports route binding with uuid, ulid and numeric for Role and Permission', function () {
    /** @var TestCase $this */

    // 1. Numeric (default)
    config(['acl.primary_key_type' => 'numeric']);
    $roleNumeric = new Role();
    $query = Role::query();
    $binding = $roleNumeric->resolveRouteBindingQuery($query, '1');
    expect($binding->toSql())->toContain('where "id" = ?');

    // 2. UUID
    config(['acl.primary_key_type' => 'uuid']);
    $uuid = (string) Str::uuid();
    $roleUuid = new Role();
    $bindingUuid = $roleUuid->resolveRouteBindingQuery(Role::query(), $uuid);
    expect($bindingUuid->toSql())->toContain('where "id" = ?');

    // Invalid UUID should whereNull to prevent unauthorized lookup
    $bindingInvalidUuid = $roleUuid->resolveRouteBindingQuery(Role::query(), 'invalid-uuid');
    expect($bindingInvalidUuid->toSql())->toContain('where "id" is null');

    // 3. ULID
    config(['acl.primary_key_type' => 'ulid']);
    $ulid = (string) Str::ulid();
    $permissionUlid = new Permission();
    $bindingUlid = $permissionUlid->resolveRouteBindingQuery(Permission::query(), $ulid);
    expect($bindingUlid->toSql())->toContain('where "id" = ?');

    $bindingInvalidUlid = $permissionUlid->resolveRouteBindingQuery(Permission::query(), 'invalid-ulid');
    expect($bindingInvalidUlid->toSql())->toContain('where "id" is null');

    // 4. Custom field query (e.g. resolve by slug or name)
    $bindingField = $roleUuid->resolveRouteBindingQuery(Role::query(), 'editor', 'slug');
    expect($bindingField->toSql())->toContain('where "slug" = ?');
});
