<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

// si usas tu propio RoleMiddleware:
use App\Http\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',     // <-- mapea WEB
        api: __DIR__.'/../routes/api.php',     // <-- mapea API (CLAVE)
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ⚠️ ¡NO usar $middleware->use('clase') con un string!
        // Si quieres agregar CORS explícitamente (opcional):
        // Usa append/prepend, no use().
        $middleware->append(\Illuminate\Http\Middleware\HandleCors::class);

        // Alias para tu middleware de roles (evita “Target class [role] does not exist”)
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability'   => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 3) Si una request a API no está autenticada, NO redirigir a 'login':
        //    devolver 401 JSON y listo (esto arregla tu "Route [login] not defined").
        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            // si tuvieras páginas Blade con login web:
            // return redirect()->guest(route('login'));
            return response()->json(['message' => 'Unauthenticated'], 401);
        });
    })->create();
