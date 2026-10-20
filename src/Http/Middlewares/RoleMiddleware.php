<?php

namespace Pmb\Acl\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Supports OR logic via '|' (e.g. 'admin|manager') and AND logic via '&' (e.g. 'admin&super-admin').
     *
     * @param Request $request
     * @param Closure $next
     * @param string $roles
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, __('pmb-acl::messages.unauthorized_role', ['roles' => $roles]));
        }

        $superAdminRole = config('acl.super_admin_role');
        if ($superAdminRole && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return $next($request);
        }

        // Logic AND: all roles separated by '&' must be possessed
        if (str_contains($roles, '&')) {
            $requiredRoles = array_filter(array_map('trim', explode('&', $roles)));
            foreach ($requiredRoles as $role) {
                if (!$user->hasRole($role)) {
                    abort(403, __('pmb-acl::messages.unauthorized_role', ['roles' => $roles]));
                }
            }

            return $next($request);
        }

        // Logic OR: at least one role separated by '|' or ',' must be possessed
        $rolesArray = array_filter(array_map('trim', preg_split('/[|,]/', $roles)));

        if (!$user->hasRole($rolesArray)) {
            abort(403, __('pmb-acl::messages.unauthorized_role', ['roles' => $roles]));
        }

        return $next($request);
    }
}