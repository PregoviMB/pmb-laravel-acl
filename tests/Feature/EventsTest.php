<?php

use Illuminate\Support\Facades\Event;
use Pmb\Acl\Events\PermissionGranted;
use Pmb\Acl\Events\PermissionRevoked;
use Pmb\Acl\Events\RoleAssigned;
use Pmb\Acl\Events\RoleRevoked;
use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;
use Pmb\Acl\Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->user = $this->createUser();
});

it('dispatches RoleAssigned and RoleRevoked events', function () {
    /** @var TestCase $this */
    Event::fake([RoleAssigned::class, RoleRevoked::class]);

    $role = Role::create(['name' => 'Manager', 'slug' => 'manager']);

    $this->user->assignRole('manager');

    Event::assertDispatched(RoleAssigned::class, function ($event) use ($role) {
        return $event->user->id === $this->user->id && $event->role->id === $role->id;
    });

    $this->user->removeRole('manager');

    Event::assertDispatched(RoleRevoked::class, function ($event) use ($role) {
        return $event->user->id === $this->user->id && $event->role->id === $role->id;
    });
});

it('dispatches PermissionGranted and PermissionRevoked events', function () {
    /** @var TestCase $this */
    Event::fake([PermissionGranted::class, PermissionRevoked::class]);

    $permission = Permission::create(['name' => 'billing.refund', 'label' => 'Refund', 'module' => 'finance']);

    $this->user->givePermissionTo('billing.refund');

    Event::assertDispatched(PermissionGranted::class, function ($event) use ($permission) {
        return $event->user->id === $this->user->id && $event->permission->id === $permission->id;
    });

    $this->user->revokePermissionTo('billing.refund');

    Event::assertDispatched(PermissionRevoked::class, function ($event) use ($permission) {
        return $event->user->id === $this->user->id && $event->permission->id === $permission->id;
    });
});

it('includes tenantId in dispatched events when scoped', function () {
    /** @var TestCase $this */
    config([
        'acl.tenant.enabled' => true,
        'acl.tenant.foreign_key' => 'tenant_id',
    ]);

    $rolesPivot = config('acl.tables.user_roles', 'user_roles');
    if (!\Illuminate\Support\Facades\Schema::hasColumn($rolesPivot, 'tenant_id')) {
        \Illuminate\Support\Facades\Schema::table($rolesPivot, function ($table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
        });
    }

    Event::fake([RoleAssigned::class]);

    $role = Role::create(['name' => 'Auditor', 'slug' => 'auditor']);

    $this->user->forTenant(42)->assignRole('auditor');

    Event::assertDispatched(RoleAssigned::class, function ($event) {
        return $event->tenantId === 42;
    });
});
