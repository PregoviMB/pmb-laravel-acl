<?php

namespace Pmb\Acl\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Pmb\Acl\Models\Permission;

class PermissionRevoked
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public mixed $user,
        public Permission $permission,
        public mixed $tenantId = null
    ) {}
}

