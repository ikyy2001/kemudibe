<?php

namespace App\Http\Middleware;

use Closure;
use Spatie\Permission\Middleware\RoleMiddleware as SpatieRoleMiddleware;

class RoleMiddleware extends SpatieRoleMiddleware
{
    /**
     * Handle an incoming request.
     * Grants superadmin bypass across all role requirements.
     */
    public function handle($request, Closure $next, $role, $guard = null)
    {
        $user = $request->user();

        if ($user && $user->hasRole('superadmin')) {
            return $next($request);
        }

        return parent::handle($request, $next, $role, $guard);
    }
}
