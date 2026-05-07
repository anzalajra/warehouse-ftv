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
        body { margin: 0; background: #f3f4f6; color: #111827; font-family: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .kiosk-logo-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 20px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
        }
        .kiosk-logo-bar .left { display: flex; align-items: center; gap: 10px; }
        .kiosk-logo-bar img { height: 32px; width: auto; }
        .kiosk-logo-bar .site-name { font-weight: 700; color: var(--primary-700, #1d4ed8); font-size: 1rem; }
        .kiosk-clock {
            display: flex; flex-direction: column; align-items: flex-end;
            font-variant-numeric: tabular-nums;
            color: #111827;
        }
        .kiosk-clock .time { font-size: 1.5rem; font-weight: 700; line-height: 1; letter-spacing: 0.5px; }
        .kiosk-clock .date { font-size: 0.75rem; color: #6b7280; margin-top: 2px; }
    </style>
</head>
<body class="font-sans antialiased">
    <header class="kiosk-logo-bar">
        <div class="left">
            @php $logo = \App\Models\Setting::get('site_logo'); @endphp
            @if($logo)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($logo) }}" alt="{{ \App\Models\Setting::get('site_name', 'Warehouse FTV') }}">
            @endif
            @if(\App\Models\Setting::get('site_name_in_header', true))
                <span class="site-name">{{ \App\Models\Setting::get('site_name', 'Warehouse FTV') }}</span>
            @endif
        </div>
        <div class="kiosk-clock" id="kioskClock">
            <span class="time" id="kioskClockTime">--:--:--</span>
            <span class="date" id="kioskClockDate">—</span>
        </div>
    </header>

    <script>
    (function () {
        const timeEl = document.getElementById('kioskClockTime');
        const dateEl = document.getElementById('kioskClockDate');
        const tz = @json(config('app.timezone', 'Asia/Jakarta'));
        const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        function tick() {
            const now = new Date(new Date().toLocaleString('en-US', { timeZone: tz }));
            const hh = String(now.getHours()).padStart(2, '0');
            const mm = String(now.getMinutes()).padStart(2, '0');
            const ss = String(now.getSeconds()).padStart(2, '0');
            timeEl.textContent = `${hh}:${mm}:${ss}`;
            dateEl.textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
        }
        tick();
        setInterval(tick, 1000);
    })();
    </script>

    <main>
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
