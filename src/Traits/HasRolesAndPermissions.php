<?php

namespace Pmb\Acl\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;

trait HasRolesAndPermissions
{
    /**
     * Relationship: User belongs to many Roles.
     */
    public function roles(): BelongsToMany
    {
        $roleModel = config('acl.models.role', Role::class);
        $tableName = config('acl.tables.user_roles', 'user_roles');

        /** @var Model $this */
        return $this->belongsToMany($roleModel, $tableName)->withTimestamps();
    }

    /**
     * Relationship: User belongs to many direct Permissions.
     */
    public function permissions(): BelongsToMany
    {
        $permissionModel = config('acl.models.permission', Permission::class);
        $tableName = config('acl.tables.user_permissions', 'user_permissions');

        /** @var Model $this */
        return $this->belongsToMany($permissionModel, $tableName)->withTimestamps();
    }

    // --- ROLE METHODS ---

    /**
     * Assign one or more roles to the user.
     *
     * @param mixed ...$roles Role names or IDs
     * @return self
     */
    public function assignRole(mixed ...$roles): self
    {
        $rolesCollection = $this->getRoleModels($roles);
        $this->roles()->syncWithoutDetaching($rolesCollection->pluck('id'));

        return $this;
    }

    /**
     * Remove one or more roles from the user.
     *
     * @param mixed ...$roles Role names or IDs
     * @return self
     */
    public function removeRole(mixed ...$roles): self
    {
        $rolesCollection = $this->getRoleModels($roles);
        $this->roles()->detach($rolesCollection->pluck('id'));

        return $this;
    }

    /**
     * Sync the user's roles with the given roles.
     *
     * @param mixed ...$roles Role names or IDs
     * @return self
     */
    public function syncRoles(mixed ...$roles): self
    {
        $rolesCollection = $this->getRoleModels($roles);
        $this->roles()->sync($rolesCollection->pluck('id'));

        return $this;
    }

    /**
     * Check if the user has a specific role.
     *
     * @param string $role Role name
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        /** @var Model $this */
        return $this->roles->contains('name', $role);
    }

    /**
     * Check if the user has any of the given roles.
     *
     * @param array $roles Array of role names
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        /** @var Model $this */
        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    // --- PERMISSION METHODS ---

    /**
     * Assign one or more direct permissions to the user.
     *
     * @param mixed ...$permissions Permission names or IDs
     * @return self
     * @throws \LogicException if direct permissions are disabled in current ACL mode
     */
    public function givePermissionTo(mixed ...$permissions): self
    {
        if (! config('acl.enable_direct_permissions', true) || config('acl.mode') === 'rbac') {
            throw new \LogicException('Direct permissions are disabled in current ACL mode.');
        }

        $permissionsCollection = $this->getPermissionModels($permissions);
        $this->permissions()->syncWithoutDetaching($permissionsCollection->pluck('id'));

        return $this;
    }

    /**
     * Remove one or more direct permissions from the user.
     *
     * @param mixed ...$permissions Permission names or IDs
     * @return self
     */
    public function revokePermissionTo(mixed ...$permissions): self
    {
        $permissionsCollection = $this->getPermissionModels($permissions);
        $this->permissions()->detach($permissionsCollection->pluck('id'));

        return $this;
    }

    /**
     * Check if the user has a specific permission.
     *
     * @param string $permission Permission name
     * @return bool
     */
    public function hasPermissionTo(string $permission): bool
    {
        /** @var Model $this */
        $mode = config('acl.mode', 'hybrid');
        $enableWildcards = config('acl.enable_wildcards', true);

        $allPermissions = collect();

        // 1. Include Role Permissions (if Hybrid or RBAC)
        if (in_array($mode, ['hybrid', 'rbac'])) {

            $rolePermissions = $this->roles->loadMissing('permissions')->pluck('permissions')->flatten();
            $allPermissions = $allPermissions->merge($rolePermissions);
        }

        // 2. Include Direct User Permissions (if Hybrid or PBAC)
        if (in_array($mode, ['hybrid', 'pbac'])) {
            $allPermissions = $allPermissions->merge($this->permissions);
        }

        $permissionNames = $allPermissions->pluck('name')->unique();

        // 3. Evaluate Wildcards using Str::is()
        return $permissionNames->contains(function ($name) use ($permission, $enableWildcards) {
            if ($name === $permission) {
                return true;
            }

            if ($enableWildcards) {
                return Str::is($name, $permission);
            }

            return false;
        });
    }

    // --- HELPERS ---

    /**
     * Get Role models based on given role names or IDs.
     *
     * @param array $roles Array of role names or IDs
     * @return Collection
     */
    protected function getRoleModels(array $roles): Collection
    {
        $roleModel = config('acl.models.role');
        $roles = collect($roles)->flatten();

        return $roleModel::whereIn('id', $roles)
            ->orWhereIn('name', $roles)
            ->get();
    }

    /**
     * Get Permission models based on given permission names or IDs.
     *
     * @param array $permissions Array of permission names or IDs
     * @return Collection
     */
    protected function getPermissionModels(array $permissions): Collection
    {
        $permissionModel = config('acl.models.permission');
        $permissions = collect($permissions)->flatten();

        return $permissionModel::whereIn('id', $permissions)
            ->orWhereIn('name', $permissions)
            ->get();
    }
}