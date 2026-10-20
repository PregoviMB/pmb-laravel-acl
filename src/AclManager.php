<?php

namespace Pmb\Acl;

use Illuminate\Support\Facades\Cache;

class AclManager
{
    /**
     * Flush permission cache for a given user or user ID.
     *
     * @param mixed $user
     * @return void
     */
    public function flushUserCache(mixed $user): void
    {
        if (is_object($user) && method_exists($user, 'flushUserAclCache')) {
            $user->flushUserAclCache();
            return;
        }

        $userId = is_object($user) ? ($user->id ?? null) : $user;

        if ($userId) {
            $prefix = config('acl.cache.key', 'pmb_acl_user_permissions');
            $cacheStore = config('acl.cache.store');
            $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();
            $cache->forget("{$prefix}_{$userId}");
        }
    }

    /**
     * Flush all users cached permissions by tag or matching prefix if cache store supports it.
     *
     * @return void
     */
    public function flushCache(): void
    {
        $cacheStore = config('acl.cache.store');
        $cache = $cacheStore ? Cache::store($cacheStore) : Cache::store();

        // If flush is called, we clear or reset the ACL cache store
        if (method_exists($cache, 'flush')) {
            // Non-destructive if dedicated store is configured
            if ($cacheStore) {
                $cache->flush();
            }
        }
    }
}

