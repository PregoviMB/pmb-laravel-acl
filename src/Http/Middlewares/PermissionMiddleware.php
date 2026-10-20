<?php

namespace Pmb\Acl\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     * Supports OR logic via '|' (e.g. 'posts.edit|posts.create') and AND logic via '&' (e.g. 'payments.make&payments.approve').
     *
     * @param Request $request
     * @param Closure $next
     * @param string $permission
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, __('pmb-acl::messages.unauthorized_permission', ['permission' => $permission]));
        }

        $superAdminRole = config('acl.super_admin_role');
        if ($superAdminRole && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return $next($request);
        }

        // Logic AND: all permissions separated by '&' must be possessed
        if (str_contains($permission, '&')) {
            $requiredPermissions = array_filter(array_map('trim', explode('&', $permission)));
            foreach ($requiredPermissions as $perm) {
                if (!$user->hasPermissionTo($perm)) {
                    abort(403, __('pmb-acl::messages.unauthorized_permission', ['permission' => $permission]));
                }
            }

            return $next($request);
        }

        // Logic OR: at least one permission separated by '|' or ',' must be possessed
        $permissionsArray = array_filter(array_map('trim', preg_split('/[|,]/', $permission)));

        foreach ($permissionsArray as $perm) {
            if ($user->hasPermissionTo($perm)) {
                return $next($request);
            }
        }

        abort(403, __('pmb-acl::messages.unauthorized_permission', ['permission' => $permission]));
    }
}