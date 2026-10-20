<?php

/**
 * This file is part of the Laravel ACL package.
 *
 * @package   Pmb\Acl
 * @author    Pmb <pregovimb.vercel.app>
 * @link      https://github.com/pmb/laravel-acl
 * @license   MIT
 */

namespace Pmb\Acl\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface RoleInterface
{
    public function permissions(): BelongsToMany;

    public function users(): BelongsToMany;
}