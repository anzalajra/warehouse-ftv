@extends('layouts.kiosk')

@section('title', 'Check-in '.$computer->name)

@push('styles')
<style>
    :root {
        --k-red-50:  #fff1f2;
        --k-red-100: #ffe4e6;
        --k-red-200: #fecdd3;
        --k-red-300: #fda4af;
        --k-red-600: #c8102e;
        --k-red-700: #a30b25;
        --k-red-800: #831021;
    }
    body { background: linear-gradient(180deg,#fafaf9 0%,#f3f4f6 100%) !important; }
    main { display:flex; align-items:center; justify-content:center; padding:32px 24px; min-height: calc(100vh - 64px); }
    @keyframes k-pulse-ring { 0%{ box-shadow:0 0 0 0 rgba(200,16,46,.45);} 70%{ box-shadow:0 0 0 14px rgba(200,16,46,0);} 100%{ box-shadow:0 0 0 0 rgba(200,16,46,0);} }
    @keyframes k-pulse-dot { 0%,100%{ opacity:1; transform:scale(1);} 50%{ opacity:.6; transform:scale(.85);} }
    .k-shell { width:100%; max-width:880px; }
    .k-card { background:#fff; border-radius:16px; border:1px solid #ececec; box-shadow:0 1px 3px rgba(17,24,39,.04), 0 1px 2px rgba(17,24,39,.03); }
    .k-strip { display:flex; align-items:center; gap:14px; padding:14px 22px; background:#fff; border-radius:14px; border:1px solid #ececec; box-shadow:0 1px 3px rgba(17,24,39,.04); }
    .k-strip-icon { width:44px; height:44px; border-radius:10px; background:linear-gradient(135deg,#c8102e 0%,#831021 100%); color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .k-strip-id { font-size:11px; font-weight:700; color:#9ca3af; letter-spacing:.1em; text-transform:uppercase; }
    .k-strip-room { font-size:11px; font-weight:600; color:var(--k-red-600); letter-spacing:.08em; text-transform:uppercase; }
    .k-strip-name { font-size:15px; font-weight:700; color:#111827; margin-top:2px; }
    .k-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:9999px; font-size:12px; font-weight:600; }
    .k-pill.lg { padding:6px 14px; font-size:13px; }
    .k-pill.sm { padding:2px 8px; font-size:11px; }
    .k-pill-success { background:#dcfce7; color:#15803d; }
    .k-pill-live    { background:#fef2f2; color:var(--k-red-600); }
    .k-pill-warn    { background:#fef3c7; color:#854d0e; }
    .k-pill-primary { background:var(--k-red-100); color:var(--k-red-700); }
    .k-pill-info    { background:#dbeafe; color:#1e40af; }
    .k-pill-danger  { background:#fee2e2; color:#991b1b; }
    .k-pill-neutral { background:#f3f4f6; color:#374151; }
    .k-pill-dot { width:7px; height:7px; border-radius:9999px; }
    .k-pill-dot.live { background:var(--k-red-600); animation: k-pulse-dot 1.4s ease-in-out infinite; box-shadow:0 0 0 4px rgba(200,16,46,.15); }

    .k-hero { padding:48px 56px; position:relative; overflow:hidden; }
    .k-hero-orn { position:absolute; top:-40px; right:-40px; width:320px; height:320px; border-radius:50%; background: radial-gradient(circle, rgba(200,16,46,0.05) 0%, rgba(200,16,46,0) 70%); pointer-events:none; }
    .k-hero-row { display:flex; align-items:flex-start; gap:32px; position:relative; }
    .k-emblem-idle { width:96px; height:96px; border-radius:9999px; background: linear-gradient(135deg,#fff1f2 0%,#ffe4e6 100%); display:flex; align-items:center; justify-content:center; animation: k-pulse-ring 2.5s ease-out infinite; flex-shrink:0; }
    .k-emblem-idle-inner { width:56px; height:56px; border-radius:9999px; background:var(--k-red-600); color:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 20px -4px rgba(200,16,46,.4); }
    .k-emblem-active { position:relative; width:110px; height:110px; flex-shrink:0; }
    .k-emblem-active-ring { position:absolute; inset:0; border-radius:9999px; background:linear-gradient(135deg,#fff1f2 0%,#ffe4e6 100%); animation: k-pulse-ring 2s ease-out infinite; }
    .k-emblem-active-core { position:absolute; inset:8px; border-radius:9999px; background: linear-gradient(135deg,#c8102e 0%,#831021 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-size:32px; font-weight:800; letter-spacing:-0.02em; box-shadow:0 12px 28px -6px rgba(200,16,46,.5); }

    .k-eyebrow { font-size:12px; font-weight:700; letter-spacing:.14em; color:#9ca3af; text-transform:uppercase; margin-bottom:6px; }
    .k-title { font-size:44px; font-weight:800; margin:0; color:#111827; letter-spacing:-0.025em; line-height:1.05; }
    @media (max-width:700px){ .k-title{ font-size:32px; } .k-hero{ padding:32px 24px; } .k-hero-row{ gap:18px; flex-direction:column; } }

    .k-window-pill { margin-top:14px; display:inline-flex; align-items:center; gap:10px; padding:10px 16px; background:#fafaf9; border-radius:10px; border:1px solid #ececec; font-size:14px; font-weight:500; color:#374151; }
    .k-mono-pill { font-family:'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace; font-weight:700; color:#111827; }
    .k-time-card { padding:8px 14px; background:var(--k-red-50); border:1.5px solid var(--k-red-200); border-radius:10px; font-family:'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace; font-size:17px; font-weight:700; color:var(--k-red-800); letter-spacing:-0.01em; display:inline-block; }

    .k-btn { font-family:inherit; font-weight:600; border-radius:10px; cursor:pointer; transition: all 200ms cubic-bezier(0.4,0,0.2,1); display:inline-flex; align-items:center; justify-content:center; gap:10px; border:0; }
    .k-btn-xl { padding:18px 28px; font-size:17px; min-height:60px; }
    .k-btn-md { padding:11px 20px; font-size:14.5px; min-height:42px; }
    .k-btn-primary { background:var(--k-red-600); color:#fff; box-shadow:0 4px 14px -3px rgba(200,16,46,.4); }
    .k-btn-primary:hover { background:var(--k-red-700); box-shadow:0 8px 24px -4px rgba(200,16,46,.5); transform:translateY(-1px); }
    .k-btn-outline { background:#fff; color:#111827; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .k-btn-outline:hover { border-color:#9ca3af; background:#fafaf9; }
    .k-btn-danger { background:#fff; color:#dc2626; border:1.5px solid #fecaca; }
    .k-btn-danger:hover { border-color:#fca5a5; background:#fef2f2; }
    .k-btn[disabled] { opacity:.6; cursor:not-allowed; }

    .k-bigbtn { text-align:left; padding:20px 22px; border-radius:14px; cursor:pointer; transition: all 200ms cubic-bezier(0.4,0,0.2,1); display:flex; align-items:center; gap:16px; font-family:inherit; width:100%; text-decoration:none; }
    .k-bigbtn-primary { background: linear-gradient(135deg,#c8102e 0%,#831021 100%); border:0; color:#fff; box-shadow:0 8px 20px -4px rgba(200,16,46,.35); }
    .k-bigbtn-primary:hover { background: linear-gradient(135deg,#a30b25 0%,#6b1320 100%); box-shadow:0 16px 32px -8px rgba(200,16,46,.5); transform:translateY(-1px); }
    .k-bigbtn-outline { background:#fff; border:1.5px solid #e5e7eb; color:#111827; box-shadow:0 1px 3px rgba(17,24,39,.04); }
    .k-bigbtn-outline:hover { background:#fafaf9; border-color:#9ca3af; transform:translateY(-1px); }
    .k-bigbtn-icon { width:48px; height:48px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; }
    .k-bigbtn-primary .k-bigbtn-icon { background:rgba(255,255,255,.15); color:#fff; }
    .k-bigbtn-outline .k-bigbtn-icon { background:var(--k-red-50); color:var(--k-red-600); }
    .k-bigbtn-title { font-size:18px; font-weight:700; letter-spacing:-0.01em; }
    .k-bigbtn-sub { font-size:13px; font-weight:500; margin-top:2px; }
    .k-bigbtn-primary .k-bigbtn-sub { color:rgba(255,255,255,.85); }
    .k-bigbtn-outline .k-bigbtn-sub { color:#6b7280; }

    .k-grid-actions { margin-top:36px; display:grid; grid-template-columns:1.4fr 1fr; gap:14px; }
    @media (max-width:700px){ .k-grid-actions{ grid-template-columns:1fr; } }

    .k-bookings-head { padding:16px 22px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between; }
    .k-bookings-head-title { display:flex; align-items:center; gap:10px; font-size:15px; font-weight:700; color:#111827; }
    .k-row { padding:14px 22px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; gap:16px; position:relative; }
    .k-row:last-child { border-bottom:0; }
    .k-row.current { background:var(--k-red-50); }
    .k-row.current::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--k-red-600); }
    .k-row-time { font-family:'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace; font-size:13px; font-weight:600; color:#374151; background:#fafaf9; padding:6px 10px; border-radius:8px; }
    .k-row-time.cancelled { color:#9ca3af; background:#f3f4f6; text-decoration:line-through; }
    .k-row-name { font-size:14px; font-weight:600; color:#111827; flex:1; }
    .k-row-name.cancelled { color:#9ca3af; text-decoration:line-through; }

    .k-power-bar { display:flex; justify-content:center; gap:12px; padding-bottom:28px; padding-top:24px; }

    .k-net-bar { margin-bottom:12px; padding:10px 16px; border-radius:10px; font-size:13px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .k-net-bar.connecting { background:#fefce8; border:1px solid #fcd34d; color:#854d0e; }
    .k-net-bar.offline { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .k-net-dot { width:8px; height:8px; border-radius:9999px; display:inline-block; }
    .k-net-dot.connecting { background:#eab308; animation: k-pulse-dot 1.4s ease-in-out infinite; }
    .k-net-dot.offline { background:#ef4444; }

    .k-offline-form { border-top:1px solid #f3f4f6; padding:24px 32px; background:#fefce8; border-radius:0 0 16px 16px; }
    .k-input { width:100%; padding:12px 14px; border:1.5px solid #fcd34d; border-radius:10px; font-size:14px; outline:none; font-family:inherit; background:#fff; }
    .k-input:focus { border-color:#a16207; }

    .k-success-banner { margin:0 32px 16px; padding:12px 14px; border-radius:10px; background:#f0fdf4; border:1px solid #86efac; color:#15803d; font-size:13px; }
    .k-error-banner { margin:0 32px 16px; padding:12px 14px; border-radius:10px; background:#fef2f2; border:1px solid #fecaca; color:#991b1b; font-size:13px; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const url = @json(route('api.kiosk.heartbeat.web', $computer->checkin_slug));
    const startTime = Date.now();
    let interval = 30_000;
    let timer;

    function send() {
        const payload = JSON.stringify({
            uptime_seconds: Math.round((Date.now() - startTime) / 1000),
        });
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: payload,
            keepalive: true,
        }).then(r => r.ok ? r.json() : null).then(data => {
            if (data && data.heartbeat_interval && data.heartbeat_interval * 1000 !== interval) {
                interval = Math.max(10_000, data.heartbeat_interval * 1000);
                clearInterval(timer);
                timer = setInterval(send, interval);
            }
        }).catch(() => {});
    }

    timer = setInterval(send, interval);
    send();
})();

// Auto-refresh every 30s so booking changes (other-checkin override, no-show flips) surface
setTimeout(() => window.location.reload(), 30_000);
</script>
@endpush

@section('content')
<div x-data="kioskShell()" x-init="init()" class="k-shell" style="margin:0 auto;">
    {{-- Network status bar --}}
    <div x-show="status !== 'online'" x-cloak
         class="k-net-bar"
         :class="status === 'connecting' ? 'connecting' : 'offline'">
        <div style="display:flex; align-items:center; gap:8px;">
            <span class="k-net-dot" :class="status === 'connecting' ? 'connecting' : 'offline'"></span>
            <span x-text="status === 'connecting' ? 'Menyambungkan ke server…' : 'Tidak ada koneksi internet'"></span>
            <span x-show="queueSize > 0" class="ml-2" style="margin-left:8px;font-size:12px;" x-text="`(${queueSize} event tertunda)`"></span>
        </div>
        <button x-show="hasBridge && status === 'offline'" @click="openWifi()"
                style="font-size:12px;text-decoration:underline;background:transparent;border:0;cursor:pointer;color:inherit;">Pilih WiFi</button>
    </div>

    {{-- Computer strip --}}
    <div class="k-strip">
        <div class="k-strip-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>
        </div>
        <div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="k-strip-id">PC-{{ str_pad($computer->id, 3, '0', STR_PAD_LEFT) }}</span>
                @if($computer->room)
                    <span style="width:4px;height:4px;border-radius:9999px;background:#d1d5db;"></span>
                    <span class="k-strip-room">{{ $computer->room->name }}</span>
                @endif
            </div>
            <div class="k-strip-name">{{ $computer->name }}</div>
        </div>
    </div>

    @if(session('success'))
        <div class="k-success-banner" style="margin:16px 0 0;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="k-error-banner" style="margin:16px 0 0;">
            <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Hero card --}}
    <div style="margin-top:16px;">
        <div class="k-card" style="overflow:hidden;">
            <div class="k-hero">
                <div class="k-hero-orn"></div>

                @if($activeBooking)
                    {{-- BOOKED HERO --}}
                    @php
                        $bookedName = $activeBooking->user?->name ?? 'User';
                        $initials = collect(explode(' ', trim($bookedName)))->take(2)->map(fn($w) => mb_substr($w, 0, 1))->implode('');
                        $endTimeC = \Carbon\Carbon::parse($activeBooking->booking_date->toDateString().' '.$activeBooking->end_time);
                        $minsRemain = max(0, (int) round(now()->diffInMinutes($endTimeC, false)));
                    @endphp
                    <div class="k-hero-row">
                        <div class="k-emblem-active">
                            <div class="k-emblem-active-ring"></div>
                            <div class="k-emblem-active-core">{{ strtoupper($initials ?: 'U') }}</div>
                        </div>
                        <div style="flex:1;padding-top:6px;">
                            <div style="display:inline-flex;align-items:center;gap:8px;margin-bottom:10px;">
                                <span class="k-pill k-pill-live lg"><span class="k-pill-dot live"></span>Sesi Terbooking</span>
                                @if($isLate)
                                    <span class="k-pill k-pill-danger lg">Telat — Risiko No-Show</span>
                                @endif
                                @if($activeBooking->checked_in_at)
                                    <span class="k-pill k-pill-success lg"><span class="k-pill-dot" style="background:#16a34a;"></span>Sudah check-in {{ $activeBooking->checked_in_at->format('H:i') }}</span>
                                @endif
                            </div>
                            <h1 class="k-title">{{ $bookedName }}</h1>
                            <div style="display:flex;gap:12px;margin-top:14px;align-items:center;flex-wrap:wrap;">
                                <div class="k-time-card">{{ $activeBooking->start_time }} – {{ $activeBooking->end_time }}</div>
                                <span style="font-size:14px;color:#6b7280;">· {{ $minsRemain }} menit tersisa</span>
                            </div>
                        </div>
                    </div>

                    <div class="k-grid-actions">
                        @if(! $activeBooking->checked_in_at)
                            <form method="POST" action="{{ route('kiosk.checkin.submit', $computer->checkin_slug) }}" style="margin:0;">
                                @csrf
                                <button type="submit" class="k-bigbtn k-bigbtn-primary" style="width:100%;">
                                    <span class="k-bigbtn-icon">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    </span>
                                    <span style="flex:1;">
                                        <span class="k-bigbtn-title" style="display:block;">Ya, ini saya</span>
                                        <span class="k-bigbtn-sub" style="display:block;">Check-in ke sesi saya</span>
                                    </span>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                                </button>
                            </form>
                        @else
                            <a href="{{ route('kiosk.timer', $computer->checkin_slug) }}?booking={{ $activeBooking->id }}" class="k-bigbtn k-bigbtn-primary">
                                <span class="k-bigbtn-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                </span>
                                <span style="flex:1;">
                                    <span class="k-bigbtn-title" style="display:block;">Lanjut ke Timer</span>
                                    <span class="k-bigbtn-sub" style="display:block;">Sesi sudah aktif</span>
                                </span>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                            </a>
                        @endif
                        <a href="{{ route('kiosk.checkin.other', $computer->checkin_slug) }}" class="k-bigbtn k-bigbtn-outline">
                            <span class="k-bigbtn-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                            </span>
                            <span style="flex:1;">
                                <span class="k-bigbtn-title" style="display:block;">Orang lain</span>
                                <span class="k-bigbtn-sub" style="display:block;">Check-in walk-in di sini</span>
                            </span>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                @else
                    {{-- IDLE HERO --}}
                    <div style="display:flex;flex-direction:column;align-items:flex-start;gap:24px;position:relative;">
                        <div class="k-emblem-idle">
                            <div class="k-emblem-idle-inner">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
                            </div>
                        </div>
                        <div>
                            <div class="k-eyebrow">Tidak ada booking aktif</div>
                            <h1 class="k-title">Siap untuk check-in walk-in</h1>
                            @if($currentSession)
                                <div class="k-window-pill">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                    <span>Jendela sesi saat ini:</span>
                                    <span class="k-mono-pill">{{ $currentSession['start'] }} – {{ $currentSession['end'] }}</span>
                                    @if($currentSession['is_night'])
                                        <span class="k-pill k-pill-warn sm">Jam Malam</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('kiosk.checkin.other', $computer->checkin_slug) }}" class="k-btn k-btn-primary k-btn-xl" style="margin-top:8px;padding-left:32px;padding-right:32px;text-decoration:none;">
                            Check-in sebagai walk-in
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Offline walk-in form (electron + offline) --}}
    <div x-show="hasBridge && status === 'offline'" x-cloak class="k-card" style="margin-top:16px;background:#fefce8;border-color:#fcd34d;overflow:hidden;">
        <div style="padding:24px 28px;">
            <h3 style="font-size:16px;font-weight:700;color:#854d0e;margin:0;">Mode Offline — Walk-in</h3>
            <p style="font-size:13px;color:#a16207;margin:6px 0 0;">Tidak ada koneksi. Kamu bisa walk-in dengan isi nama saja. Data akan tersinkron saat online.</p>
            <form @submit.prevent="submitOffline()" style="margin-top:16px;display:flex;flex-direction:column;gap:10px;">
                <input type="text" x-model="offlineName" required placeholder="Nama lengkap" class="k-input">
                <input type="text" x-model="offlinePurpose" required placeholder="Kegunaan (mis. editing tugas)" class="k-input">
                <button type="submit" :disabled="offlineSubmitting" class="k-btn k-btn-md" style="background:#a16207;color:#fff;width:100%;justify-content:center;">
                    <span x-show="!offlineSubmitting">Check-in Offline</span>
                    <span x-show="offlineSubmitting">Menyimpan…</span>
                </button>
            </form>
        </div>
    </div>

    {{-- Today's bookings --}}
    <div style="margin-top:16px;">
        <div class="k-card" style="overflow:hidden;">
            <div class="k-bookings-head">
                <div class="k-bookings-head-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25"/><path d="M3 18.75A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75M3 18.75V9A2.25 2.25 0 0 1 5.25 6.75h13.5A2.25 2.25 0 0 1 21 9v9.75"/></svg>
                    <span>Booking hari ini</span>
                </div>
                <span style="font-size:12px;color:#6b7280;font-weight:500;">{{ $todaysBookings->count() }} {{ $todaysBookings->count() === 1 ? 'sesi' : 'sesi' }}</span>
            </div>
            <div>
                @if($todaysBookings->isEmpty())
                    <div style="padding:18px 22px;font-size:13px;color:#6b7280;">Belum ada booking hari ini.</div>
                @else
                    @php
                        $grace = \App\Services\ComputerValidationService::noShowGraceMinutes();
                        $nowTs = now();
                    @endphp
                    @foreach($todaysBookings as $b)
                        @php
                            $isCurrent = $activeBooking && $activeBooking->id === $b->id;

                            // Derive an effective status so the table stays accurate between
                            // the 5-minute cron ticks. Persisted terminal/admin states win;
                            // we only refine confirmed/active in real time.
                            $effective = $b->status;
                            if (in_array($b->status, [\App\Models\ComputerBooking::STATUS_CONFIRMED, \App\Models\ComputerBooking::STATUS_ACTIVE])) {
                                $startTs = \Carbon\Carbon::parse($b->booking_date->toDateString().' '.$b->start_time);
                                $endTs = \Carbon\Carbon::parse($b->booking_date->toDateString().' '.$b->end_time);
                                if ($endTs->lessThan($startTs)) { $endTs->addDay(); } // overnight slot

                                if ($nowTs->greaterThanOrEqualTo($endTs)) {
                                    $effective = $b->checked_in_at
                                        ? \App\Models\ComputerBooking::STATUS_COMPLETED
                                        : \App\Models\ComputerBooking::STATUS_NO_SHOW;
                                } elseif (! $b->checked_in_at && $nowTs->greaterThanOrEqualTo($startTs->copy()->addMinutes($grace))) {
                                    $effective = \App\Models\ComputerBooking::STATUS_NO_SHOW;
                                } elseif ($b->checked_in_at && $nowTs->greaterThanOrEqualTo($startTs)) {
                                    $effective = \App\Models\ComputerBooking::STATUS_ACTIVE;
                                } elseif (! $b->checked_in_at && $nowTs->lessThan($startTs)) {
                                    $effective = \App\Models\ComputerBooking::STATUS_CONFIRMED;
                                } elseif (! $b->checked_in_at && $nowTs->between($startTs, $startTs->copy()->addMinutes($grace))) {
                                    // Within grace window — still confirmed but late
                                    $effective = \App\Models\ComputerBooking::STATUS_CONFIRMED;
                                }
                            }

                            $isCancelled = in_array($effective, ['cancelled', 'no_show', 'overridden']);
                            $statusLabelMap = [
                                'confirmed' => ['Dikonfirmasi', 'k-pill-primary', false],
                                'active'    => ['Sedang digunakan', 'k-pill-live', true],
                                'completed' => ['Selesai', 'k-pill-success', false],
                                'cancelled' => ['Dibatalkan', 'k-pill-neutral', false],
                                'no_show'   => ['No-show', 'k-pill-danger', false],
                                'overridden'=> ['Diambil alih', 'k-pill-warn', false],
                            ];
                            [$lbl, $tone, $live] = $statusLabelMap[$effective] ?? [ucfirst($effective), 'k-pill-neutral', false];
                        @endphp
                        <div class="k-row {{ $isCurrent ? 'current' : '' }}">
                            <div class="k-row-time {{ $isCancelled ? 'cancelled' : '' }}">{{ $b->start_time }} – {{ $b->end_time }}</div>
                            <span class="k-row-name {{ $isCancelled ? 'cancelled' : '' }}">{{ $b->user?->name ?? '—' }}</span>
                            <span class="k-pill {{ $tone }}">
                                @if($live)<span class="k-pill-dot live"></span>@endif
                                {{ $lbl }}
                            </span>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    {{-- Power footer (electron only) --}}
    <div x-show="hasBridge" x-cloak class="k-power-bar">
        <button type="button" @click="sleep()" class="k-btn k-btn-outline k-btn-md">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
            Sleep
        </button>
        <button type="button" @click="shutdown()" class="k-btn k-btn-danger k-btn-md">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v9"/><path d="M5.64 7.05a9 9 0 1 0 12.72 0"/></svg>
            Shutdown
        </button>
    </div>
</div>

<script>
function kioskShell() {
    return {
        status: 'connecting',
        queueSize: 0,
        hasBridge: !!window.kioskBridge,
        offlineName: '',
        offlinePurpose: '',
        offlineSubmitting: false,

        async init() {
            if (!this.hasBridge) {
                this.status = navigator.onLine ? 'online' : 'offline';
                window.addEventListener('online', () => this.status = 'online');
                window.addEventListener('offline', () => this.status = 'offline');
                return;
            }
            this.status = await window.kioskBridge.getNetworkStatus();
            window.kioskBridge.onNetworkChange((s) => { this.status = s; this.refreshQueue(); });
            this.refreshQueue();
            setInterval(() => this.refreshQueue(), 10_000);
        },

        async refreshQueue() {
            if (!this.hasBridge) return;
            this.queueSize = await window.kioskBridge.getQueueSize();
        },

        async submitOffline() {
            if (!this.hasBridge) return;
            this.offlineSubmitting = true;
            await window.kioskBridge.queueOfflineCheckin({
                name: this.offlineName,
                purpose: this.offlinePurpose,
                started_at: new Date().toISOString(),
            });
            this.offlineSubmitting = false;
            this.offlineName = '';
            this.offlinePurpose = '';
            this.refreshQueue();
            alert('Tersimpan offline. Akan disinkronkan saat online.');
        },

        sleep() { if (this.hasBridge) window.kioskBridge.sleep(); },
        shutdown() {
            if (!this.hasBridge) return;
            if (confirm('Yakin ingin shutdown komputer?')) window.kioskBridge.shutdown();
        },
        openWifi() { if (this.hasBridge) window.kioskBridge.openWifiSettings(); },
    };
}
</script>
@endsection
