<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\CheckInstallation::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'customer.auth' => \App\Http\Middleware\CustomerAuth::class,
            'customer.guest' => \App\Http\Middleware\CustomerGuest::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'logout',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (\Throwable $e) {
            try {
                $admins = \App\Models\User::all();
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\SystemErrorNotification($e->getMessage()));
            } catch (\Throwable $t) {
                // Squelch errors during error reporting to prevent infinite loops
            }
        });
    })->create();
