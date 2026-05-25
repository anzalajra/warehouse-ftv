@extends('layouts.kiosk')

@section('title', 'Terjadi Kesalahan')

@push('styles')
<style>
    body { background: linear-gradient(180deg,#fafaf9 0%,#f3f4f6 100%) !important; }
    main { display:flex; align-items:center; justify-content:center; padding:32px 24px; min-height: calc(100vh - 64px); }
    .ke-shell { width:100%; max-width:560px; }
    .ke-card {
        background:#fff; border-radius:16px; border:1px solid #ececec;
        box-shadow:0 1px 3px rgba(17,24,39,.04), 0 1px 2px rgba(17,24,39,.03);
        padding:36px 36px 28px;
    }
    .ke-icon {
        width:64px; height:64px; border-radius:9999px;
        background:#fef2f2; color:#c8102e;
        display:flex; align-items:center; justify-content:center;
        margin:0 auto 18px;
    }
    .ke-title { font-size:22px; font-weight:800; color:#111827; text-align:center; margin:0 0 6px; letter-spacing:-0.01em; }
    .ke-sub { font-size:14px; color:#6b7280; text-align:center; margin:0 0 22px; }
    .ke-detail {
        background:#f9fafb; border:1px solid #e5e7eb; border-radius:10px;
        padding:14px 16px; font-size:13px; color:#374151;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        word-break: break-word; line-height:1.55;
    }
    .ke-detail .row { display:flex; gap:10px; }
    .ke-detail .row + .row { margin-top:6px; }
    .ke-detail .k { color:#9ca3af; min-width:84px; flex-shrink:0; }
    .ke-actions { display:flex; gap:10px; margin-top:20px; flex-wrap:wrap; }
    .ke-btn {
        flex:1; min-width:140px; padding:11px 16px;
        border-radius:10px; border:1px solid transparent;
        font-size:14px; font-weight:600; cursor:pointer;
        display:inline-flex; align-items:center; justify-content:center; gap:8px;
        transition: background .15s, border-color .15s;
    }
    .ke-btn-primary { background:#c8102e; color:#fff; }
    .ke-btn-primary:hover { background:#a30b25; }
    .ke-btn-ghost { background:#fff; color:#374151; border-color:#e5e7eb; }
    .ke-btn-ghost:hover { background:#f9fafb; }
    .ke-copied { font-size:12px; color:#15803d; text-align:center; margin-top:10px; min-height:16px; }
</style>
@endpush

@section('content')
<div class="ke-shell">
    <div class="ke-card">
        <div class="ke-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <h1 class="ke-title">Terjadi Kesalahan</h1>
        <p class="ke-sub">Halaman tidak dapat dimuat. Silakan coba lagi atau hubungi admin.</p>

        <div class="ke-detail" id="keDetail">
            <div class="row"><span class="k">Error ID</span><span>{{ $errorId }}</span></div>
            <div class="row"><span class="k">Tipe</span><span>{{ $errorClass }}</span></div>
            <div class="row"><span class="k">Pesan</span><span>{{ $errorMessage }}</span></div>
            <div class="row"><span class="k">URL</span><span>{{ $requestUrl }}</span></div>
            <div class="row"><span class="k">Waktu</span><span>{{ $occurredAt }}</span></div>
        </div>

        <div class="ke-actions">
            <button type="button" class="ke-btn ke-btn-ghost" id="keCopy">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
                <span>Salin Detail</span>
            </button>
            <button type="button" class="ke-btn ke-btn-ghost" onclick="location.reload()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"/>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>
                </svg>
                <span>Muat Ulang</span>
            </button>
            @if(!empty($homeSlug))
                <a href="{{ route('kiosk.checkin', $homeSlug) }}" class="ke-btn ke-btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    <span>Kembali</span>
                </a>
            @endif
        </div>
        <div class="ke-copied" id="keCopied">&nbsp;</div>
    </div>
</div>

<script>
(function () {
    const btn = document.getElementById('keCopy');
    const msg = document.getElementById('keCopied');
    const payload = [
        'Error ID: {{ $errorId }}',
        'Tipe: {{ $errorClass }}',
        'Pesan: {!! addslashes($errorMessage) !!}',
        'URL: {{ $requestUrl }}',
        'Waktu: {{ $occurredAt }}',
    ].join('\n');
    btn.addEventListener('click', async () => {
        let ok = false;
        try {
            await navigator.clipboard.writeText(payload);
            ok = true;
        } catch (_) {
            const ta = document.createElement('textarea');
            ta.value = payload;
            ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(ta);
        }
        msg.textContent = ok ? 'Detail error telah disalin.' : 'Gagal menyalin — silakan catat manual.';
        msg.style.color = ok ? '#15803d' : '#991b1b';
        setTimeout(() => { msg.innerHTML = '&nbsp;'; }, 3000);
    });
})();
</script>
@endsection
