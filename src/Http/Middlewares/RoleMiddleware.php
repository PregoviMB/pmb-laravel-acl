<?php

namespace Pmb\Acl\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, __('pmb-acl::messages.unauthorized_role', ['roles' => $roles]));
        }

        if ($user->hasRole(config('acl.super_admin_role'))) {
            return $next($request);
        }

        $roleArray = explode('|', $roles);

        if (!$user->hasRole($roleArray)) {
            abort(403, __('pmb-acl::messages.unauthorized_role', ['roles' => $roles]));
        }

        return $next($request);
    }
}