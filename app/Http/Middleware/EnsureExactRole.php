<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExactRole
{
    /**
     * Allow the request only when the authenticated user's role is exactly one
     * of the supplied roles (no hierarchy). Usage: ->middleware('role.exact:manager').
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Your account is not active.');
        }

        $values = array_map(fn (string $r) => Role::from($r)->value, $roles);

        if ($user->role instanceof Role && in_array($user->role->value, $values, true)) {
            return $next($request);
        }

        abort(403, 'You do not have permission to perform this action.');
    }
}
