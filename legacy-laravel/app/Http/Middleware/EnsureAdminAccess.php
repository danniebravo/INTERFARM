<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->canAccessAdminPanel()) {
            abort(403);
        }

        $permission = $user::adminPermissionForRoute($request->route()?->getName());

        if ($permission && ! $user->hasAdminPermission($permission)) {
            abort(403, 'No tienes permisos para acceder a esta seccion.');
        }

        return $next($request);
    }
}
