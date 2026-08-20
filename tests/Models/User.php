<?php

namespace Pmb\Acl\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pmb\Acl\Traits\HasAcl;

class User extends Authenticatable
{
    use HasAcl;

    protected $guarded = [];
    protected $table = 'users';
}