@extends('layouts.kiosk')

@section('title', 'Timer Aktif - '.$computer->name)

@push('styles')
<style>
    :root {
        --f-red-50:  #fff1f2;
        --f-red-100: #ffe4e6;
        --f-red-200: #fecdd3;
        --f-red-300: #fda4af;
        --f-red-600: #c8102e;
        --f-red-700: #a30b25;
        --f-red-800: #831021;
    }
    /* Hide kiosk header — floating window has its own title bar */
    body > header.kiosk-logo-bar { display:none !important; }
    body { background:transparent !important; }
    main { padding:0; margin:0; }
    @keyframes f-pulse-dot { 0%,100%{ opacity:1; transform:scale(1);} 50%{ opacity:.6; transform:scale(.85);} }

    .f-window {
        height: 100vh;
        display: flex;
        flex-direction: column;
        background: #fff;
        font-family: 'Figtree', system-ui, sans-serif;
        color: #111827;
        overflow: hidden;
    }
    .f-titlebar {
        display:flex; align-items:center; justify-content:space-between;
        padding:10px 14px;
        background:#fafaf9;
        border-bottom:1px solid #ececec;
        cursor: grab;
        user-select: none;
        flex-shrink: 0;
        -webkit-app-region: drag;
    }
    .f-titlebar:active { cursor: grabbing; }
    .f-titlebar > * { -webkit-app-region: no-drag; }
    .f-titlebar-brand { -webkit-app-region: drag; display:flex; align-items:center; gap:10px; }
    .f-logo {
        width:22px; height:22px; border-radius:6px;
        background: linear-gradient(135deg,#c8102e 0%,#831021 100%);
        display:flex; align-items:center; justify-content:center;
        box-shadow:0 2px 6px -1px rgba(200,16,46,.4);
        flex-shrink:0;
    }
    .f-titlebar-text { line-height:1.1; }
    .f-titlebar-title { font-size:12px; font-weight:700; color:#111827; letter-spacing:-0.01em; }
    .f-titlebar-sub { font-size:10px; color:#9ca3af; font-weight:500; }
    .f-tray { display:flex; gap:6px; -webkit-app-region: no-drag; }
    .f-tray span { width:9px; height:9px; border-radius:9999px; }

    .f-body {
        flex: 1;
        overflow: auto;
        padding: 18px 20px 14px;
        background: linear-gradient(180deg,#fff 0%,#fafaf9 100%);
        display: flex;
        flex-direction: column;
        gap: 12px;
        min-height: 0;
    }

    .f-user-row { display:flex; align-items:center; gap:12px; }
    .f-avatar {
        width:44px; height:44px; border-radius:9999px;
        background: linear-gradient(135deg,#fbbf24,#f59e0b);
        color:#7c2d12; display:flex; align-items:center; justify-content:center;
        font-size:16px; font-weight:800;
        box-shadow:0 4px 12px -2px rgba(245,158,11,.4);
        flex-shrink: 0;
    }
    .f-user-text { flex:1; min-width:0; line-height:1.2; }
    .f-user-eyebrow { font-size:10px; font-weight:700; letter-spacing:.08em; color:#9ca3af; text-transform:uppercase; }
    .f-user-name { font-size:15px; font-weight:700; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:-0.01em; margin-top:1px; }

    .f-pc-card {
        padding:10px 12px; background:#fff; border-radius:10px; border:1px solid #ececec;
        display:flex; align-items:center; gap:10px;
    }
    .f-pc-icon { color: var(--f-red-600); flex-shrink:0; }
    .f-pc-text { flex:1; min-width:0; }
    .f-pc-name { font-size:13px; font-weight:600; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .f-pc-sub { font-size:11px; color:#6b7280; }

    .f-timer-head { display:flex; align-items:baseline; justify-content:space-between; }
    .f-timer-eyebrow { font-size:10px; font-weight:700; color:#9ca3af; letter-spacing:.08em; text-transform:uppercase; }
    .f-timer-checkin { font-size:10px; color:#6b7280; font-family:'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace; }

    .f-timer {
        font-family:'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace;
        font-size: clamp(28px, 9vw, 44px);
        font-weight:700;
        color:#111827;
        letter-spacing:-0.02em;
        line-height:1;
        font-variant-numeric: tabular-nums;
    }

    .f-live {
        display:flex; align-items:center; gap:8px;
        padding:8px 10px; background:var(--f-red-50);
        border-radius:8px; border:1px solid var(--f-red-200);
        font-size:12px; font-weight:600; color:var(--f-red-800);
    }
    .f-live-dot {
        width:8px; height:8px; border-radius:9999px; background:var(--f-red-600);
        animation: f-pulse-dot 1.4s ease-in-out infinite;
        box-shadow:0 0 0 3px rgba(200,16,46,.2);
    }

    .f-actions {
        flex-shrink: 0;
        padding: 12px 14px 14px;
        border-top: 1px solid #f3f4f6;
        background: #fff;
        display: flex; flex-direction: column; gap: 8px;
    }

    .f-btn { font-family:inherit; font-weight:600; border-radius:10px; cursor:pointer; transition: all 150ms; display:inline-flex; align-items:center; justify-content:center; gap:8px; border:0; min-height:42px; padding:10px 16px; font-size:14px; width:100%; }
    .f-btn[disabled] { opacity:.6; cursor:not-allowed; }
    .f-btn-primary { background:var(--f-red-600); color:#fff; box-shadow:0 4px 14px -3px rgba(200,16,46,.35); }
    .f-btn-primary:hover:not([disabled]) { background:var(--f-red-700); }
    .f-btn-outline { background:#fff; color:#111827; border:1px solid #e5e7eb; }
    .f-btn-outline:hover { border-color:#9ca3af; background:#fafaf9; }
    .f-btn-danger { background:#fff; color:#dc2626; border:1.5px solid #fecaca; }
    .f-btn-danger:hover { border-color:#fca5a5; background:#fef2f2; }

    .f-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
</style>
@endpush

@section('content')
<div class="f-window" x-data="kioskTimer()" x-init="init()">
    <div class="f-titlebar">
        <div class="f-titlebar-brand">
            <span class="f-logo">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M5 4 L19 4 L19 8 L9 8 L19 14 L19 20 L5 20 L5 16 L15 16 L5 10 Z" fill="#fff"/></svg>
            </span>
            <div class="f-titlebar-text">
                <div class="f-titlebar-title">FTV UPI · Session</div>
                <div class="f-titlebar-sub">Drag to move</div>
            </div>
        </div>
        <div class="f-tray" aria-hidden="true">
            <span style="background:#fbbf24;"></span>
            <span style="background:#22c55e;"></span>
            <span style="background:#ef4444;"></span>
        </div>
    </div>

    <div class="f-body">
        <div class="f-user-row">
            <div class="f-avatar">{{ strtoupper(mb_substr($booking->user?->name ?? $booking->offline_walkin_name ?? 'U', 0, 1)) }}</div>
            <div class="f-user-text">
                <div class="f-user-eyebrow">Signed in</div>
                <div class="f-user-name">{{ $booking->user?->name ?? $booking->offline_walkin_name ?? 'User' }}</div>
            </div>
        </div>

        <div class="f-pc-card">
            <span class="f-pc-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>
            </span>
            <div class="f-pc-text">
                <div class="f-pc-name">{{ $computer->name }}</div>
                <div class="f-pc-sub">PC-{{ str_pad($computer->id, 3, '0', STR_PAD_LEFT) }}@if($computer->room) · {{ $computer->room->name }}@endif</div>
            </div>
        </div>

        <div>
            <div class="f-timer-head">
                <span class="f-timer-eyebrow">Session time</span>
                <span class="f-timer-checkin">Checked in <span x-text="checkinDisplay">--:--</span></span>
            </div>
            <div class="f-timer" x-text="display" style="margin-top:6px;">00:00:00</div>
            <div class="f-live" style="margin-top:10px;">
                <span class="f-live-dot"></span>
                <span>Sesi {{ $booking->start_time }} – {{ $booking->end_time }} · session active</span>
            </div>
        </div>
    </div>

    <div class="f-actions">
        <button type="button" @click="logout()" :disabled="loggingOut" class="f-btn f-btn-primary">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12H3.75M15 12l-3.75-3.75M15 12l-3.75 3.75"/><path d="M9 5.25V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7a2 2 0 0 1-2-2v-.25"/></svg>
            <span x-show="!loggingOut">Logout & end session</span>
            <span x-show="loggingOut">Menyimpan…</span>
        </button>
        <div class="f-row-2">
            <button type="button" @click="sleep()" class="f-btn f-btn-outline">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
                Sleep
            </button>
            <button type="button" @click="shutdown()" class="f-btn f-btn-danger">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v9"/><path d="M5.64 7.05a9 9 0 1 0 12.72 0"/></svg>
                Shutdown
            </button>
        </div>
    </div>
</div>

<script>
function kioskTimer() {
    return {
        startedAt: @json(($booking->actual_started_at ?? $booking->checked_in_at ?? now())->toIso8601String()),
        display: '00:00:00',
        checkinDisplay: '--:--',
        timer: null,
        loggingOut: false,
        bookingId: {{ $booking->id }},
        slug: @json($computer->checkin_slug),

        init() {
            const start = new Date(this.startedAt);
            const hh = String(start.getHours()).padStart(2, '0');
            const mm = String(start.getMinutes()).padStart(2, '0');
            this.checkinDisplay = `${hh}:${mm}`;

            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);

            if (window.kioskBridge && typeof window.kioskBridge.enterTimerMode === 'function') {
                window.kioskBridge.enterTimerMode({
                    bookingId: this.bookingId,
                    userName: @json($booking->user?->name ?? $booking->offline_walkin_name ?? 'User'),
                });
            }
        },

        tick() {
            const elapsed = Math.max(0, Math.floor((Date.now() - new Date(this.startedAt).getTime()) / 1000));
            const h = String(Math.floor(elapsed / 3600)).padStart(2, '0');
            const m = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
            const s = String(elapsed % 60).padStart(2, '0');
            this.display = `${h}:${m}:${s}`;
        },

        async logout() {
            if (!confirm('Logout & end session?')) return;
            this.loggingOut = true;
            const endedAt = new Date().toISOString();
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            try {
                const res = await fetch(`/kiosk/checkin/${this.slug}/logout`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `booking_id=${this.bookingId}`,
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                if (window.kioskBridge) window.kioskBridge.exitTimerMode();
                window.location.href = `/kiosk/checkin/${this.slug}`;
            } catch (e) {
                if (window.kioskBridge && window.kioskBridge.queueOfflineLogout) {
                    await window.kioskBridge.queueOfflineLogout({
                        booking_id: this.bookingId,
                        started_at: this.startedAt,
                        ended_at: endedAt,
                    });
                    window.kioskBridge.exitTimerMode();
                    window.location.href = `/kiosk/checkin/${this.slug}`;
                } else {
                    alert('Gagal logout: ' + e.message);
                    this.loggingOut = false;
                }
            }
        },

        sleep() {
            if (window.kioskBridge) window.kioskBridge.sleep();
        },
        shutdown() {
            if (window.kioskBridge && confirm('Yakin shutdown?')) window.kioskBridge.shutdown();
            else if (!window.kioskBridge) alert('Shutdown hanya tersedia di kiosk Electron app.');
        },
    };
}
</script>
@endsection
