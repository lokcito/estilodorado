<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user(); // Empleado por Sanctum
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // si tu relación es $empleado->roles() con ->pluck('nombre')
        $userRoles = $user->roles?->pluck('nombre')->map(fn($r) => strtoupper($r))->toArray() ?? [];

        $required = array_map('strtoupper', $roles);
        $ok = count(array_intersect($userRoles, $required)) > 0;

        return $ok
            ? $next($request)
            : response()->json(['message' => 'Forbidden'], 403);
    }
}

