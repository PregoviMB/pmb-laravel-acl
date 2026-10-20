<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;
use Pmb\Acl\Tests\TestCase;

beforeEach(function () {
    /** @var TestCase $this */
    $this->user = $this->createUser();
});

it('supports contextual tenant roles and permissions (multi-tenancy)', function () {
    /** @var TestCase $this */
    config([
        'acl.tenant.enabled' => true,
        'acl.tenant.foreign_key' => 'tenant_id',
    ]);

    // Add tenant_id to pivot tables for this test
    $rolesPivot = config('acl.tables.user_roles', 'user_roles');
    $permsPivot = config('acl.tables.user_permissions', 'user_permissions');

    if (!Schema::hasColumn($rolesPivot, 'tenant_id')) {
        Schema::table($rolesPivot, function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
        });
    }

    if (!Schema::hasColumn($permsPivot, 'tenant_id')) {
        Schema::table($permsPivot, function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
        });
    }

    $roleAdmin = Role::create(['name' => 'Tenant Admin', 'slug' => 'tenant-admin']);
    $roleMember = Role::create(['name' => 'Tenant Member', 'slug' => 'tenant-member']);

    $permBilling = Permission::create(['name' => 'billing.manage', 'label' => 'Billing', 'module' => 'finance']);
    $roleAdmin->permissions()->attach($permBilling->id);

    // Assign Admin on Tenant 10, but Member on Tenant 20
    $this->user->forTenant(10)->assignRole('tenant-admin');
    $this->user->forTenant(20)->assignRole('tenant-member');

    // Context Tenant 10: has admin, has billing.manage
    $userTenant10 = $this->user->forTenant(10);
    expect($userTenant10->hasRole('tenant-admin'))->toBeTrue()
        ->and($userTenant10->hasRole('tenant-member'))->toBeFalse()
        ->and($userTenant10->hasPermissionTo('billing.manage'))->toBeTrue();

    // Context Tenant 20: has member, does NOT have billing.manage
    $userTenant20 = $this->user->forTenant(20);
    expect($userTenant20->hasRole('tenant-member'))->toBeTrue()
        ->and($userTenant20->hasRole('tenant-admin'))->toBeFalse()
        ->and($userTenant20->hasPermissionTo('billing.manage'))->toBeFalse();

    // Direct permissions scoped to tenant
    $permDirect = Permission::create(['name' => 'direct.invite', 'label' => 'Invite', 'module' => 'users']);
    $this->user->forTenant(10)->givePermissionTo('direct.invite');

    expect($this->user->forTenant(10)->hasPermissionTo('direct.invite'))->toBeTrue()
        ->and($this->user->forTenant(20)->hasPermissionTo('direct.invite'))->toBeFalse();

    // Backwards compatibility: forTeam() still works as alias
    expect($this->user->forTeam(10)->hasPermissionTo('direct.invite'))->toBeTrue();
});


