<?php

namespace Pmb\Acl\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface PermissionInterface
{
    public function roles(): BelongsToMany;

    public function users(): BelongsToMany;
}