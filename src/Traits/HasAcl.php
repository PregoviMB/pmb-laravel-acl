<?php

namespace Pmb\Acl\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Pmb\Acl\Models\Permission;
use Pmb\Acl\Models\Role;

/**
 * Trait HasAcl
 *
 * Provides a unified Access Control List (ACL) solution for the User model.
 *
 * Unlike standard HasRolesAndPermissions traits, HasAcl integrates:
 * - Hybrid mode support (RBAC + PBAC simultaneously).
 * - Native wildcard pattern matching (e.g., 'posts.*', '*').
 * - Config-driven Super Admin bypass.
 * - Dynamic resolution of primary key types (ULID, UUID, Numeric).
 */
trait HasAcl
{
    /**
     * Relationship: Get all assigned roles for the user.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $roleModel = config('acl.models.role', Role::class);
        $pivotTable = config('acl.tables.user_roles', 'user_roles');

        /** @var Model $this */
        return $this->belongsToMany($roleModel, $pivotTable)->withTimestamps();
    }

    /**
     * Relationship: Get all direct permissions assigned to the user.
     * Note: Evaluated only if 'enable_direct_permissions' is set to true in config/acl.php.
     *
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        $permissionModel = config('acl.models.permission', Permission::class);
        $pivotTable = config('acl.tables.user_permissions', 'user_permissions');

        /** @var Model $this */
        return $this->belongsToMany($permissionModel, $pivotTable)->withTimestamps();
    }

    /**
     * Assign one or multiple roles to the user by model instance, slug, or name.
     *
     * @param string|Role ...$roles
     * @return static
     */
    public function assignRole(string|Role ...$roles): static
    {
        $roleModel = config('acl.models.role', Role::class);

        foreach ($roles as $role) {
            if (is_string($role)) {
                $role = $roleModel::where('slug', $role)->orWhere('name', $role)->firstOrFail();
            }
            $this->roles()->syncWithoutDetaching([$role->id]);
        }

        return $this;
    }

    /**
     * Remove one or multiple assigned roles from the user.
     *
     * @param string|Role ...$roles
     * @return static
     */
    public function removeRole(string|Role ...$roles): static
    {
        $roleModel = config('acl.models.role', Role::class);

        foreach ($roles as $role) {
            if (is_string($role)) {
                $role = $roleModel::where('slug', $role)->orWhere('name', $role)->first();
            }
            if ($role) {
                $this->roles()->detach($role->id);
            }
        }

        return $this;
    }

    /**
     * Synchronize the user's roles with a given list, replacing previous assignments.
     *
     * @param array $roles Array of role slugs, names, or Role instances.
     * @return static
     */
    public function syncRoles(array $roles): static
    {
        $roleModel = config('acl.models.role', Role::class);
        $ids = [];

        foreach ($roles as $role) {
            if (is_string($role)) {
                $roleObj = $roleModel::where('slug', $role)->orWhere('name', $role)->first();
                if ($roleObj) {
                    $ids[] = $roleObj->id;
                }
            } elseif ($role instanceof Role) {
                $ids[] = $role->id;
            }
        }

        $this->roles()->sync($ids);

        return $this;
    }

    /**
     * Check if the user possesses any of the specified roles.
     *
     * @param string|array $roles Single role or array of roles (slug or name).
     * @return bool
     */
    public function hasRole(string|array $roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        return $this->roles()->whereIn('slug', $roles)->orWhereIn('name', $roles)->exists();
    }

    /**
     * Grant a direct permission to the user (PBAC Mode).
     * Has no effect if 'enable_direct_permissions' is disabled in configuration.
     *
     * @param string|Permission ...$permissions
     * @return static
     */
    public function givePermissionTo(string|Permission ...$permissions): static
    {
        if (!config('acl.enable_direct_permissions', true)) {
            return $this;
        }

        $permissionModel = config('acl.models.permission', Permission::class);

        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $permission = $permissionModel::where('name', $permission)->firstOrFail();
            }
            $this->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $this;
    }

    /**
     * Revoke a direct permission from the user.
     *
     * @param string|Permission ...$permissions
     * @return static
     */
    public function revokePermissionTo(string|Permission ...$permissions): static
    {
        $permissionModel = config('acl.models.permission', Permission::class);

        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $permission = $permissionModel::where('name', $permission)->first();
            }
            if ($permission) {
                $this->permissions()->detach($permission->id);
            }
        }

        return $this;
    }

    /**
     * Determine if the user has a specific permission.
     * 
     * Evaluation Flow:
     * 1. Super Admin Role Bypass (if enabled).
     * 2. Direct user permissions check (PBAC).
     * 3. Inherited role permissions check (RBAC).
     * 4. Wildcard matching evaluation (e.g., 'posts.*').
     *
     * @param string $permission Technical permission name (e.g., 'posts.create').
     * @return bool
     */
    public function hasPermissionTo(string $permission): bool
    {
        // 1. Super Admin Bypass Check
        $superAdminRole = config('acl.super_admin_role');
        if ($superAdminRole && $this->hasRole($superAdminRole)) {
            return true;
        }

        // 2. Fetch Direct User Permissions (if enabled in config)
        $directPermissions = config('acl.enable_direct_permissions', true)
            ? $this->permissions()->pluck('name')->toArray()
            : [];

        // 3. Fetch Inherited Permissions from Roles
        $rolePermissions = $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->toArray();

        $allPermissions = array_unique(array_merge($directPermissions, $rolePermissions));

        // 4. Exact Match Check
        if (in_array($permission, $allPermissions, true)) {
            return true;
        }

        // 5. Wildcard Pattern Matching Check
        if (config('acl.enable_wildcards', true)) {
            foreach ($allPermissions as $userPermission) {
                if ($this->matchWildcard($userPermission, $permission)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Evaluates wildcard expressions against requested permissions using regular expressions.
     * e.g. 'posts.*' matches 'posts.create', 'posts.edit', 'posts.*.edit', etc.
     *
     * @param string $pattern Pattern with wildcard asterisks.
     * @param string $permission Target permission to evaluate.
     * @return bool
     */
    protected function matchWildcard(string $pattern, string $permission): bool
    {
        if ($pattern === '*' || $pattern === '**') {
            return true;
        }

        // 1. Scape the pattern for regex, preserving wildcard characters.
        $quoted = preg_quote($pattern, '/');

        // 2. Replace 'Double-asterisk constant' with '.*' (multi-level)
        $regex = str_replace(
            ['\*\*'],
            ['.*'],
            $quoted
        );

        // and 'Single-asterisk constant' with '[^.]+' (single segment without a dot)
        $regex = str_replace('\*', '[^.]+', $regex);

        // output the final regex pattern for evaluation
        // e.g. 'posts.*' becomes '^posts\.[^.]+$' and 'posts.**' becomes '^posts\..*$'
        return (bool) preg_match('/^' . $regex . '$/i', $permission);
    }
}