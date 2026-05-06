<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ \App\Models\Setting::get('site_name', 'Warehouse FTV') }} — @yield('title', 'Kiosk')</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')

    @if(isset($themeCssVariables))
        <style>
            :root { {!! $themeCssVariables !!} }
        </style>
    @endif

    <style>
        body { margin: 0; background: #f3f4f6; }
        .kiosk-logo-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
        }
        .kiosk-logo-bar img { height: 36px; width: auto; }
        .kiosk-logo-bar .site-name { font-weight: 700; color: var(--primary-700, #1d4ed8); margin-left: 10px; font-size: 1rem; }
    </style>
</head>
<body class="font-sans antialiased">
    <header class="kiosk-logo-bar">
        @php $logo = \App\Models\Setting::get('site_logo'); @endphp
        @if($logo)
            <img src="{{ \Illuminate\Support\Facades\Storage::url($logo) }}" alt="{{ \App\Models\Setting::get('site_name', 'Warehouse FTV') }}">
        @endif
        @if(\App\Models\Setting::get('site_name_in_header', true))
            <span class="site-name">{{ \App\Models\Setting::get('site_name', 'Warehouse FTV') }}</span>
        @endif
    </header>

    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
