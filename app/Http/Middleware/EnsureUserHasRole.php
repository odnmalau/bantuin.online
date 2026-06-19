<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $roleValue = $request->user()?->role?->value;

        abort_unless(
            $roleValue !== null && in_array($roleValue, $roles, true),
            403,
        );

        return $next($request);
    }
}
