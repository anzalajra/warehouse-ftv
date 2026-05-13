<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
        {{-- PWA Meta Tags --}}
        <meta name="theme-color" content="#0ea5e9">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <link rel="manifest" href="/manifest.json">
        <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @if(isset($themeCssVariables))
            <style>
                :root {
                    {!! $themeCssVariables !!}
                }
            </style>
        @endif
    </head>
    <body class="font-sans antialiased">
        @if(session()->has('impersonator_id'))
            <div class="bg-amber-500 text-white text-sm">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between gap-3">
                    <span>
                        <strong>Mode Impersonate:</strong>
                        Anda sedang masuk sebagai <strong>{{ auth('customer')->user()?->name }}</strong>.
                    </span>
                    <a href="{{ route('impersonate.stop') }}" class="bg-white text-amber-700 font-semibold px-3 py-1 rounded hover:bg-amber-50 whitespace-nowrap">
                        Berhenti Impersonate
                    </a>
                </div>
            </div>
        @endif

        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
