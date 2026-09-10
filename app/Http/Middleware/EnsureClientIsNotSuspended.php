<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientIsNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->canAccessAdminPanel() || ! $user->isSuspended()) {
            return $next($request);
        }

        if ($this->routeIsAllowed($request)) {
            return $next($request);
        }

        return redirect()->route('client.suspended');
    }

    protected function routeIsAllowed(Request $request): bool
    {
        return $request->routeIs([
            'client.suspended',
            'client.billing.*',
            'notifications.*',
            'logout',
            'password.*',
        ]);
    }
}
