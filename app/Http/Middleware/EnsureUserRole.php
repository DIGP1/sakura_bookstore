<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $userRole = strtoupper((string) $user?->rol);
        $allowedRoles = array_map('strtoupper', $roles);

        if ($user === null || ! in_array($userRole, $allowedRoles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
