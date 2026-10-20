<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Allow the request when the authenticated user's role is one of the
     * supplied roles, or outranks them (hierarchy: manager < executive <
     * platform_admin). Usage: ->middleware('role:platform_admin').
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Your account is not active.');
        }

        $allowed = array_map(fn (string $r) => Role::from($r), $roles);

        foreach ($allowed as $role) {
            if ($user->role instanceof Role && $user->role->atLeast($role)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}
