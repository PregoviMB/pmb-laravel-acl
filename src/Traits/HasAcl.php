<?php

namespace Pmb\Acl\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Pmb\Acl\Events\PermissionGranted;
use Pmb\Acl\Events\PermissionRevoked;
use Pmb\Acl\Events\RoleAssigned;
use Pmb\Acl\Events\RoleRevoked;
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
     * Contextual tenant/scope identifier for multi-tenancy.
     *
     * @var mixed
     */
    protected mixed $aclCurrentTenantId = null;

    /**
     * Scope subsequent role and permission operations to a specific tenant (organization, team, company, project).
     *
     * @param mixed $tenant Tenant model or ID
     * @return static
     */
    public function forTenant(mixed $tenant): static
    {
        $clone = clone $this;
        $clone->aclCurrentTenantId = is_object($tenant) ? ($tenant->getKey() ?? $tenant->id) : $tenant;

        return $clone;
    }

    /**
     * Alias for forTenant to maintain backwards compatibility.
     *
     * @param mixed $team Team/Tenant model or ID
     * @return static
     */
    public function forTeam(mixed $team): static
    {
        return $this->forTenant($team);
    }

    /**
     * Set the current tenant ID context directly on this instance.
     *
     * @param mixed $tenant Tenant model or ID
     * @return static
     */
    public function setAclTenant(mixed $tenant): static
    {
        $this->aclCurrentTenantId = is_object($tenant) ? ($tenant->getKey() ?? $tenant->id) : $tenant;

        return $this;
    }

    /**
     * Alias for setAclTenant.
     *
     * @param mixed $team
     * @return static
     */
    public function setAclTeam(mixed $team): static
    {
        return $this->setAclTenant($team);
    }

    /**
     * Get the active tenant ID context.
     *
     * @return mixed
     */
    public function getAclTenantId(): mixed
    {
        return $this->aclCurrentTenantId;
    }

    /**
     * Alias for getAclTenantId.
     *
     * @return mixed
     */
    public function getAclTeamId(): mixed
    {
        return $this->getAclTenantId();
    }

    /**
     * Relationship: Get all assigned roles for the user (scoped to tenant if enabled).
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $roleModel = config('acl.models.role', Role::class);
        $pivotTable = config('acl.tables.user_roles', 'user_roles');

        /** @var Model $this */
        $relation = $this->belongsToMany($roleModel, $pivotTable)->withTimestamps();

        $tenantEnabled = config('acl.tenant.enabled', config('acl.teams.enabled', false));
        if ($tenantEnabled) {
            $tenantFk = config('acl.tenant.foreign_key', config('acl.teams.foreign_key', 'tenant_id'));
            $relation->withPivot($tenantFk);

            if ($this->aclCurrentTenantId !== null) {
                $relation->wherePivot($tenantFk, $this->aclCurrentTenantId);
            }
        }

        return $relation;
    }

    /**
     * Relationship: Get all direct permissions assigned to the user (scoped to tenant if enabled).
     * Note: Evaluated only if 'enable_direct_permissions' is set to true in config/acl.php.
     *
     * @return BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        $permissionModel = config('acl.models.permission', Permission::class);
        $pivotTable = config('acl.tables.user_permissions', 'user_permissions');

        /** @var Model $this */
        $relation = $this->belongsToMany($permissionModel, $pivotTable)->withTimestamps();

        $tenantEnabled = config('acl.tenant.enabled', config('acl.teams.enabled', false));
        if ($tenantEnabled) {
            $tenantFk = config('acl.tenant.foreign_key', config('acl.teams.foreign_key', 'tenant_id'));
            $relation->withPivot($tenantFk);

            if ($this->aclCurrentTenantId !== null) {
                $relation->wherePivot($tenantFk, $this->aclCurrentTenantId);
            }
        }

        return $relation;
    }

    /**
     * Relationship: Get all direct permissions assigned to the user (alias for clarity).
     *
     * @return BelongsToMany
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->permissions();
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
        $tenantEnabled = config('acl.tenant.enabled', config('acl.teams.enabled', false));
        $tenantFk = config('acl.tenant.foreign_key', config('acl.teams.foreign_key', 'tenant_id'));
        $pivotData = ($tenantEnabled && $this->aclCurrentTenantId !== null)
            ? [$tenantFk => $this->aclCurrentTenantId]
            : [];

        foreach ($roles as $role) {
            if (is_string($role)) {
                $role = $roleModel::where('slug', $role)->orWhere('name', $role)->firstOrFail();
            }
            if ($tenantEnabled && $this->aclCurrentTenantId !== null) {
                $this->roles()->syncWithoutDetaching([$role->id => $pivotData]);
            } else {
                $this->roles()->syncWithoutDetaching([$role->id]);
            }

            event(new RoleAssigned($this, $role, $this->aclCurrentTenantId));
        }

        $this->flushUserAclCache();

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
                event(new RoleRevoked($this, $role, $this->aclCurrentTenantId));
            }
        }

        $this->flushUserAclCache();

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

        $this->flushUserAclCache();

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
        $tenantEnabled = config('acl.tenant.enabled', config('acl.teams.enabled', false));
        $tenantFk = config('acl.tenant.foreign_key', config('acl.teams.foreign_key', 'tenant_id'));
        $pivotData = ($tenantEnabled && $this->aclCurrentTenantId !== null)
            ? [$tenantFk => $this->aclCurrentTenantId]
            : [];

        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $permission = $permissionModel::where('name', $permission)->firstOrFail();
            }
            if ($tenantEnabled && $this->aclCurrentTenantId !== null) {
                $this->permissions()->syncWithoutDetaching([$permission->id => $pivotData]);
            } else {
                $this->permissions()->syncWithoutDetaching([$permission->id]);
            }

            event(new PermissionGranted($this, $permission, $this->aclCurrentTenantId));
        }

        $this->flushUserAclCache();

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
                event(new PermissionRevoked($this, $permission, $this->aclCurrentTenantId));
            }
        }

        $this->flushUserAclCache();

        return $this;
    }

    /**
     * Synchronize the user's direct permissions with a given list, replacing previous direct assignments.
     *
     * @param array $permissions Array of permission names or Permission instances.
     * @return static
     */
    public function syncPermissions(array $permissions): static
    {
        if (!config('acl.enable_direct_permissions', true)) {
            return $this;
        }

        $permissionModel = config('acl.models.permission', Permission::class);
        $ids = [];

        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $permissionObj = $permissionModel::where('name', $permission)->first();
                if ($permissionObj) {
                    $ids[] = $permissionObj->id;
                }
            } elseif ($permission instanceof Permission) {
                $ids[] = $permission->id;
            }
        }

        $this->permissions()->sync($ids);

        $this->flushUserAclCache();

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
        // 1. Super Admin bypass check
        $superAdminRole = config('acl.super_admin_role', 'super-admin');
        if ($this->hasRole($superAdminRole)) {
            return true;
        }

        // 2. Retrieve all consolidated permissions (direct + via roles)
        $permissions = $this->getAllPermissions();

        // 3. Resolve wildcard permissions using pattern matching
        foreach ($permissions as $p) {
            $permissionPattern = is_string($p) ? $p : $p->name;

            // Matches wildcard patterns like 'posts.*' against 'posts.draft.comments.delete.edit'
            if (Str::is($permissionPattern, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Helper to retrieve all consolidated permissions (direct assignments + role-based).
     * Cached according to config('acl.cache').
     *
     * @return Collection
     */
    public function getAllPermissions(): Collection
    {
        $cacheEnabled = config('acl.cache.enabled', true);

        if (!$cacheEnabled || !$this->getKey()) {
            return $this->fetchConsolidatedPermissions();
        }

        $cacheStore = config('acl.cache.store');
        $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();
        $cacheKey = $this->getAclUserCacheKey();
        $cacheTtl = config('acl.cache.ttl', 86400);

        $retrieveData = function () {
            return $this->fetchConsolidatedPermissions()->map(function ($p) {
                return [
                    'id'     => $p->id ?? null,
                    'name'   => $p->name ?? null,
                    'label'  => $p->label ?? null,
                    'module' => $p->module ?? null,
                ];
            })->all();
        };

        $cachedData = $cacheTtl
            ? $cache->remember($cacheKey, $cacheTtl, $retrieveData)
            : $cache->rememberForever($cacheKey, $retrieveData);

        // Check if cache returned an incomplete or corrupted structure
        if (!is_array($cachedData)) {
            $this->flushUserAclCache();
            $cachedData = $retrieveData();
        }

        $permissionModel = config('acl.models.permission', Permission::class);

        return collect($cachedData)->map(function ($attributes) use ($permissionModel) {
            $permission = new $permissionModel();
            $permission->forceFill($attributes);
            $permission->exists = true;

            return $permission;
        });
    }

    /**
     * Fetch consolidated permissions directly from the database without cache.
     *
     * @return Collection
     */
    public function fetchConsolidatedPermissions(): Collection
    {
        $mode = config('acl.mode', 'hybrid');
        $allPermissions = collect();

        // 1. Direct user permissions (if Hybrid or PBAC)
        if (in_array($mode, ['hybrid', 'pbac'], true)) {
            $directPermissions = $this->relationLoaded('permissions')
                ? $this->permissions
                : $this->permissions()->get();
            $allPermissions = $allPermissions->merge($directPermissions);
        }

        // 2. Role permissions (if Hybrid or RBAC)
        if (in_array($mode, ['hybrid', 'rbac'], true)) {
            $roles = $this->relationLoaded('roles')
                ? $this->roles
                : $this->roles()->with('permissions')->get();

            if ($this->relationLoaded('roles') && $roles->isNotEmpty()) {
                $roles->loadMissing('permissions');
            }

            $rolePermissions = $roles->pluck('permissions')->collapse();
            $allPermissions = $allPermissions->merge($rolePermissions);
        }

        return $allPermissions->unique('id')->values();
    }

    /**
     * Get the cache key for this user's permissions.
     *
     * @return string
     */
    public function getAclUserCacheKey(): string
    {
        $prefix = config('acl.cache.key', 'pmb_acl_user_permissions');
        $scope = $this->aclCurrentTenantId !== null ? "_tenant_{$this->aclCurrentTenantId}" : '';

        return "{$prefix}_{$this->getKey()}{$scope}";
    }

    /**
     * Flush the cached permissions for this user (for current scope or global).
     *
     * @return void
     */
    public function flushUserAclCache(): void
    {
        if (!config('acl.cache.enabled', true) || !$this->getKey()) {
            return;
        }

        $cacheStore = config('acl.cache.store');
        $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();
        $cache->forget($this->getAclUserCacheKey());

        // If currently in a tenant scope, also invalidate base key
        if ($this->aclCurrentTenantId !== null) {
            $prefix = config('acl.cache.key', 'pmb_acl_user_permissions');
            $cache->forget("{$prefix}_{$this->getKey()}");
        }
    }

    /**
     * Accessor to get all consolidated permissions directly via $user->all_permissions.
     *
     * @return Collection
     */
    public function getAllPermissionsAttribute(): Collection
    {
        return $this->getAllPermissions();
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