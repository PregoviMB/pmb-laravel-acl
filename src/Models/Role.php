<?php

/**
 * This file is part of the Laravel ACL package.
 *
 * @package   Pmb\Acl
 * @author    Pmb <pmb@pmb.com>
 * @link      https://github.com/pmb/laravel-acl
 * @license   MIT
 */

namespace Pmb\Acl\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Pmb\Acl\Contracts\RoleInterface;

class Role extends Model implements RoleInterface
{
    
    protected $guarded = [];

    protected $fillable = [
        'name',
        'label',
        'slug',
        'description',
        'is_system',
    ];

    /**
     * Create a new Role instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('acl.tables.roles', 'roles'));
        $this->configurePrimaryKeyType();
    }

    /**
     * Configure the primary key type based on the configuration.
     *
     * @return void
     */
    protected function configurePrimaryKeyType(): void
    {
        $pkType = config('acl.primary_key_type', 'ulid');

        if ($pkType === 'ulid' || $pkType === 'uuid') {
            $this->keyType = 'string';
            $this->incrementing = false;
        } else {
            $this->keyType = 'int';
            $this->incrementing = true;
        }
    }

    /**
     * Generate a new unique identifier for the model.
     *
     * @return string
     */
    public function newUniqueId(): string
    {
        $pkType = config('acl.primary_key_type', 'numeric');

        return match ($pkType) {
            'uuid'  => strtolower((string) \Illuminate\Support\Str::uuid()),
            'ulid'  => strtolower((string) \Illuminate\Support\Str::ulid()),
            default => parent::newUniqueId(),
        };
    }

    /**
     * Get the permissions associated with the role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions(): BelongsToMany
    {
        $permissionModel = config('acl.models.permission', Permission::class);
        $pivotTable = config('acl.tables.role_permissions', 'role_permissions');

        return $this->belongsToMany($permissionModel, $pivotTable)->withTimestamps();
    }

    /**
     * Get the users associated with the role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        $userModel = config('acl.models.user', 'App\Models\User');
        $pivotTable = config('acl.tables.user_roles', 'user_roles');

        return $this->belongsToMany($userModel, $pivotTable)->withTimestamps();
    }
}