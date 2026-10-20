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
use Illuminate\Support\Str;
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
        $pkType = config('acl.primary_key_type', 'numeric');

        if ($pkType === 'ulid' || $pkType === 'uuid') {
            $this->keyType = 'string';
            $this->incrementing = false;
        } else {
            $this->keyType = 'int';
            $this->incrementing = true;
        }
    }

    /**     * Boot the model - ensure ID is generated before saving.
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $pkType = config('acl.primary_key_type', 'numeric');
            
            // Only generate ID for uuid/ulid, not for numeric (database autoincrement)
            if (($pkType === 'uuid' || $pkType === 'ulid') && !$model->getKey()) {
                $model->{$model->getKeyName()} = $model->newUniqueId();
            }
        });
    }

    /** Generate a new unique identifier for the model.
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
     * Retrieve the model for a bound value.
     *
     * Overrides route binding query to support UUIDs, ULIDs, or numeric IDs dynamically.
     *
     * @param  \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Relations\Relation|\Illuminate\Database\Eloquent\Builder
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $pkType = config('acl.primary_key_type', 'numeric');

        if ($pkType === 'uuid' && !Str::isUuid($value)) {
            return $query->whereNull($this->getRouteKeyName());
        }

        if ($pkType === 'ulid' && !Str::isUlid($value)) {
            return $query->whereNull($this->getRouteKeyName());
        }

        return $query->where($this->getRouteKeyName(), $value);
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