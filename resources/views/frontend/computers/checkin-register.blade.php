@extends('layouts.kiosk')

@section('title', 'Daftar Akun - '.$computer->name)

@push('styles')
<style>
    :root {
        --r-red-50:  #fff1f2;
        --r-red-100: #ffe4e6;
        --r-red-200: #fecdd3;
        --r-red-300: #fda4af;
        --r-red-600: #c8102e;
        --r-red-700: #a30b25;
        --r-red-800: #831021;
    }
    body { background: linear-gradient(180deg,#fafaf9 0%,#f3f4f6 100%) !important; }
    main { display:flex; align-items:center; justify-content:center; padding:32px 24px; min-height: calc(100vh - 64px); }
    @keyframes r-pulse-dot { 0%,100%{ opacity:1; transform:scale(1);} 50%{ opacity:.6; transform:scale(.85);} }

    .r-shell { width:100%; max-width:680px; }
    .r-card { background:#fff; border-radius:16px; border:1px solid #ececec; box-shadow:0 1px 3px rgba(17,24,39,.04); overflow:hidden; }
    .r-strip { display:flex; align-items:center; gap:14px; padding:14px 22px; background:#fff; border-radius:14px; border:1px solid #ececec; box-shadow:0 1px 3px rgba(17,24,39,.04); margin-bottom:16px; }
    .r-strip-icon { width:44px; height:44px; border-radius:10px; background:linear-gradient(135deg,#c8102e 0%,#831021 100%); color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .r-strip-id { font-size:11px; font-weight:700; color:#9ca3af; letter-spacing:.1em; text-transform:uppercase; }
    .r-strip-room { font-size:11px; font-weight:600; color:var(--r-red-600); letter-spacing:.08em; text-transform:uppercase; }
    .r-strip-name { font-size:15px; font-weight:700; color:#111827; margin-top:2px; }

    .r-head { padding:32px 40px; background:linear-gradient(135deg,#c8102e 0%,#831021 100%); color:#fff; position:relative; overflow:hidden; }
    @media (max-width:700px){ .r-head{ padding:24px; } }
    .r-grid-bg { position:absolute; inset:0; background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,.1) 1px, transparent 0); background-size:20px 20px; pointer-events:none; }
    .r-eyebrow { font-size:12px; font-weight:700; letter-spacing:.14em; opacity:.85; text-transform:uppercase; margin-bottom:8px; }
    .r-h1 { font-size:30px; font-weight:800; margin:0; letter-spacing:-0.02em; line-height:1.1; }
    @media (max-width:700px){ .r-h1{ font-size:24px; } }
    .r-email-pill { margin-top:14px; display:inline-flex; align-items:center; gap:10px; padding:8px 14px; background:rgba(255,255,255,.15); border-radius:10px; font-family:'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace; font-size:14px; font-weight:600; max-width:100%; }
    .r-email-pill span { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    .r-body { padding:32px 40px 28px; }
    @media (max-width:700px){ .r-body{ padding:24px; } }

    .r-step { display:flex; gap:14px; padding:14px 16px; border:1px solid #ececec; border-radius:12px; background:#fafaf9; }
    .r-step + .r-step { margin-top:10px; }
    .r-step-num { width:28px; height:28px; border-radius:9999px; background:var(--r-red-50); color:var(--r-red-600); font-size:13px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
    .r-step-text { font-size:14px; color:#374151; line-height:1.45; }
    .r-step-text strong { color:#111827; font-weight:700; }

    .r-qr-wrap { margin-top:24px; display:flex; justify-content:center; }
    .r-qr-card { padding:16px; background:#fff; border:1.5px solid var(--r-red-200); border-radius:18px; box-shadow:0 8px 24px -8px rgba(200,16,46,.18); position:relative; }
    .r-qr-card::before { content:''; position:absolute; inset:-1px; border-radius:18px; padding:1px; background: linear-gradient(135deg, rgba(200,16,46,.4), rgba(200,16,46,0) 60%); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask-composite: exclude; pointer-events:none; }
    .r-qr-card img { display:block; width:240px; height:240px; border-radius:8px; }
    @media (max-width:480px){ .r-qr-card img{ width:200px; height:200px; } }

    .r-pulse-row { margin-top:18px; display:flex; align-items:center; justify-content:center; gap:10px; padding:10px 14px; background:var(--r-red-50); border:1px solid var(--r-red-200); border-radius:10px; font-size:13px; font-weight:600; color:var(--r-red-800); }
    .r-pulse-dot { width:8px; height:8px; border-radius:9999px; background:var(--r-red-600); animation: r-pulse-dot 1.4s ease-in-out infinite; box-shadow:0 0 0 3px rgba(200,16,46,.18); }

    .r-url { margin-top:14px; padding:10px 12px; background:#fafaf9; border:1px solid #ececec; border-radius:10px; font-family:'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace; font-size:11px; color:#6b7280; word-break:break-all; text-align:center; }

    .r-footer { display:flex; justify-content:center; margin-top:24px; }
    .r-btn { font-family:inherit; font-weight:600; border-radius:10px; cursor:pointer; transition: all 150ms; display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:11px 20px; font-size:14.5px; min-height:42px; text-decoration:none; background:#fff; color:#111827; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .r-btn:hover { border-color:#9ca3af; background:#fafaf9; }
</style>
@endpush

@push('scripts')
<script>
// Polling: kalau registrasi via HP berhasil, kiosk auto-refresh ke checkin/timer page
(function () {
    const slug = @json($computer->checkin_slug);
    setInterval(() => {
        fetch(`/kiosk/checkin/${slug}`, { redirect: 'manual' })
            .then(() => window.location.href = `/kiosk/checkin/${slug}`)
            .catch(() => {});
    }, 8000);
})();
</script>
@endpush

@section('content')
<div class="r-shell" style="margin:0 auto;">
    {{-- Computer strip --}}
    <div class="r-strip">
        <div class="r-strip-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>
        </div>
        <div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span class="r-strip-id">PC-{{ str_pad($computer->id, 3, '0', STR_PAD_LEFT) }}</span>
                @if($computer->room)
                    <span style="width:4px;height:4px;border-radius:9999px;background:#d1d5db;"></span>
                    <span class="r-strip-room">{{ $computer->room->name }}</span>
                @endif
            </div>
            <div class="r-strip-name">{{ $computer->name }}</div>
        </div>
    </div>

    <div class="r-card">
        <div class="r-head">
            <div class="r-grid-bg"></div>
            <div style="position:relative;">
                <div class="r-eyebrow">Email Belum Terdaftar</div>
                <h1 class="r-h1">Daftar dulu untuk lanjut</h1>
                <div class="r-email-pill">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                    <span>{{ $email }}</span>
                </div>
            </div>
        </div>

        <div class="r-body">
            {{-- Steps --}}
            <div class="r-step">
                <span class="r-step-num">1</span>
                <span class="r-step-text">Buka kamera HP, lalu <strong>scan QR code</strong> di bawah.</span>
            </div>
            <div class="r-step">
                <span class="r-step-num">2</span>
                <span class="r-step-text">Isi data singkat di HP (nama, NIM, dll). Akun warehouse akan dibuat dengan email ini.</span>
            </div>
            <div class="r-step">
                <span class="r-step-num">3</span>
                <span class="r-step-text">Selesai daftar, <strong>check-in akan otomatis dilakukan</strong> di komputer ini — halaman ini akan berpindah sendiri.</span>
            </div>

            {{-- QR Code --}}
            <div class="r-qr-wrap">
                <div class="r-qr-card">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={{ urlencode($registerUrl) }}"
                         alt="QR Daftar">
                </div>
            </div>

            {{-- Pulse status --}}
            <div class="r-pulse-row">
                <span class="r-pulse-dot"></span>
                <span>Menunggu pendaftaran dari HP…</span>
            </div>

            {{-- URL fallback --}}
            <div class="r-url">{{ $registerUrl }}</div>
        </div>
    </div>

    <div class="r-footer">
        <a href="{{ route('kiosk.checkin', $computer->checkin_slug) }}" class="r-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Kembali
        </a>
    </div>
</div>
@endsection
