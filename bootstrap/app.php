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
            'kiosk.auth' => \App\Http\Middleware\KioskBearerAuth::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'logout',
            'api/kiosk/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) return null;

            $path = '/'.ltrim($request->path(), '/');
            $isKioskRoute = str_starts_with($path, '/kiosk/checkin/')
                || str_starts_with($path, '/m/kiosk-register/');
            if (! $isKioskRoute) return null;

            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $status = $e->getStatusCode();
                if ($status < 500 && $status !== 419) return null;
            }

            $errorId = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            try {
                \Illuminate\Support\Facades\Log::error('Kiosk error '.$errorId.': '.$e->getMessage(), [
                    'url' => $request->fullUrl(),
                    'exception' => $e,
                ]);
            } catch (\Throwable $t) {}

            return response()->view('errors.kiosk-error', [
                'errorId' => $errorId,
                'errorMessage' => $e->getMessage() ?: class_basename($e),
                'errorClass' => class_basename($e),
                'requestUrl' => $request->fullUrl(),
                'occurredAt' => now()->toIso8601String(),
                'homeSlug' => $request->route('slug'),
            ], 500);
        });

        $exceptions->reportable(function (\Throwable $e) {
            // Skip non-critical exceptions
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) return;
            if ($e instanceof \Illuminate\Validation\ValidationException) return;
            if ($e instanceof \Illuminate\Auth\AuthenticationException) return;

            try {
                $admins = \App\Models\User::role(['super_admin'])->get();
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\SystemErrorNotification($e->getMessage()));
                }
            } catch (\Throwable $t) {
                // Squelch errors during error reporting to prevent infinite loops
            }
        });
    })->create();
