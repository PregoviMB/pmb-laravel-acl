<?php

namespace Pmb\Acl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Pmb\Acl\Contracts\PermissionInterface;
use Pmb\Acl\Models\Role;

class Permission extends Model implements PermissionInterface
{
    
    protected $guarded = [];

    protected $fillable = [
        'name',
        'label',
        'module',
    ];

    /**
     * Create a new Permission instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('acl.tables.permissions', 'permissions'));
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
            'uuid'  => (string) \Illuminate\Support\Str::uuid(),
            'ulid'  => (string) \Illuminate\Support\Str::ulid(),
            default => parent::newUniqueId(),
        };
    }

    /**
     * Define the many-to-many relationship between Permission and Role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $roleModel = config('acl.models.role', Role::class);
        $pivotTable = config('acl.tables.role_permissions', 'role_permissions');

        return $this->belongsToMany($roleModel, $pivotTable)->withTimestamps();
    }

    /**
     * Define the many-to-many relationship between Permission and User.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users(): BelongsToMany
    {
        $userModel = config('acl.models.user', 'App\Models\User');
        $pivotTable = config('acl.tables.user_permissions', 'user_permissions');

        return $this->belongsToMany($userModel, $pivotTable)->withTimestamps();
    }
}