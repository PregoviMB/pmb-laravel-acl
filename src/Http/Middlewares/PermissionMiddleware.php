<?php

namespace Pmb\Acl\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, __('pmb-acl::messages.unauthorized_permission', ['permission' => $permission]));
        }

        if (!$user->hasPermissionTo($permission)) {
            abort(403, __('pmb-acl::messages.unauthorized_permission', ['permission' => $permission]));
        }

        return $next($request);
    }
}