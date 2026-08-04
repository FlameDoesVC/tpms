<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        // spatie/laravel-permission does not register these itself under the
        // Laravel 11+ bootstrap style; without them `role:admin` fails with
        // "Target class [role] does not exist".
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // NOTE: throttleApi() is deliberately not used - this application serves
        // its whole API from routes/web.php (session/cookie auth), so there is no
        // "api" route group for it to attach to. The named limiters defined in
        // AppServiceProvider are applied per group in routes/web.php instead.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
