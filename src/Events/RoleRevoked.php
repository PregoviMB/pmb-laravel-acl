<?php

namespace Pmb\Acl\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Pmb\Acl\Models\Role;

class RoleRevoked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public mixed $user,
        public Role $role,
        public mixed $tenantId = null
    ) {}
}

