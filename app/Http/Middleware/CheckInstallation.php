<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class CheckInstallation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isInstalled = app()->environment('testing') || File::exists(storage_path('installed'));

        // If installed and trying to access setup, redirect to home
        if ($isInstalled && $request->is('setup*')) {
            return redirect('/');
        }

        // If NOT installed and trying to access anything other than setup, redirect to setup
        // Also exclude static assets or debugbar if needed, but for now strict is fine
        if (!$isInstalled && !$request->is('setup*')) {
            return redirect('/setup');
        }

        return $next($request);
    }
}
