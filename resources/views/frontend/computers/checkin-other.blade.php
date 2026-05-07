@extends('layouts.kiosk')

@section('title', 'Check-in Lain - '.$computer->name)

@push('styles')
<style>
    :root {
        --w-red-50:  #fff1f2;
        --w-red-100: #ffe4e6;
        --w-red-200: #fecdd3;
        --w-red-300: #fda4af;
        --w-red-600: #c8102e;
        --w-red-700: #a30b25;
        --w-red-800: #831021;
    }
    body { background: linear-gradient(180deg,#fafaf9 0%,#f3f4f6 100%) !important; }
    main { display:flex; align-items:center; justify-content:center; padding:32px 24px; min-height: calc(100vh - 64px); }
    .w-shell { width:100%; max-width:760px; }
    .w-card { background:#fff; border-radius:16px; border:1px solid #ececec; box-shadow:0 1px 3px rgba(17,24,39,.04); overflow:hidden; }
    .w-confirm-head { padding:40px 48px; background: linear-gradient(135deg,#fff1f2 0%,#fff 60%); border-bottom:1px solid #ececec; }
    @media (max-width:700px){ .w-confirm-head{ padding:28px 24px; } }
    .w-detect-pill { display:inline-flex; align-items:center; gap:8px; padding:6px 12px; background:#fff; border-radius:9999px; border:1px solid #fecdd3; box-shadow:0 1px 2px rgba(200,16,46,.06); margin-bottom:16px; font-size:12px; font-weight:700; color:#831021; letter-spacing:.04em; }
    .w-reserved-by { font-size:13px; font-weight:600; color:#6b7280; letter-spacing:.04em; margin-bottom:8px; }
    .w-name { font-size:40px; font-weight:800; margin:0; letter-spacing:-0.025em; line-height:1.1; color:#111827; }
    @media (max-width:700px){ .w-name{ font-size:30px; } }
    .w-time-card { padding:8px 14px; background:#fff; border:1.5px solid #fecdd3; border-radius:10px; font-family:'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace; font-size:16px; font-weight:700; color:#831021; }
    .w-confirm-body { padding:28px 48px 32px; }
    @media (max-width:700px){ .w-confirm-body{ padding:24px; } }
    .w-confirm-text { font-size:16px; color:#374151; margin:0; line-height:1.55; }
    .w-grid-2 { display:grid; grid-template-columns:1fr 1.4fr; gap:12px; margin-top:24px; }
    @media (max-width:600px){ .w-grid-2{ grid-template-columns:1fr; } }

    .w-form-head { padding:32px 40px; background:linear-gradient(135deg,#c8102e 0%,#831021 100%); color:#fff; position:relative; overflow:hidden; }
    @media (max-width:700px){ .w-form-head{ padding:24px; } }
    .w-form-head .w-grid-bg { position:absolute; inset:0; background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,.1) 1px, transparent 0); background-size:20px 20px; pointer-events:none; }
    .w-eyebrow { font-size:12px; font-weight:700; letter-spacing:.14em; opacity:.85; text-transform:uppercase; margin-bottom:8px; }
    .w-h1 { font-size:32px; font-weight:800; margin:0; letter-spacing:-0.02em; line-height:1.1; }
    .w-strip-pill { margin-top:14px; display:inline-flex; align-items:center; gap:10px; padding:8px 14px; background:rgba(255,255,255,.15); border-radius:10px; font-size:14px; font-weight:500; }

    .w-field { margin-bottom:22px; }
    .w-label { font-size:13px; font-weight:600; color:#374151; display:block; margin-bottom:8px; }
    .w-input, .w-textarea { width:100%; padding:13px 16px; border:1.5px solid #e5e7eb; border-radius:10px; font-size:15px; color:#111827; outline:none; transition: all 150ms; font-family:inherit; }
    .w-textarea { min-height:88px; padding:12px 14px; resize:vertical; font-size:14.5px; }
    .w-input:focus, .w-textarea:focus { border-color:var(--w-red-600); box-shadow:0 0 0 3px rgba(200,16,46,.15); }
    .w-hint { margin-top:8px; font-size:12px; color:#6b7280; display:flex; align-items:center; gap:6px; }

    .w-session-card { padding:14px 16px; border:1.5px solid var(--w-red-600); border-radius:10px; background:var(--w-red-50); display:flex; align-items:center; gap:12px; margin-bottom:8px; cursor:pointer; transition: all 150ms; }
    .w-session-card.unchecked { border-color:#e5e7eb; background:#fff; }
    .w-session-card.unchecked:hover { border-color: var(--w-red-300); }
    .w-session-radio { width:22px; height:22px; border-radius:9999px; flex-shrink:0; border:1.5px solid var(--w-red-600); display:flex; align-items:center; justify-content:center; }
    .w-session-radio.checked { background:var(--w-red-600); border:4px solid #fff; box-shadow:0 0 0 1.5px var(--w-red-600); }
    .w-session-card.unchecked .w-session-radio { border-color:#d1d5db; background:#fff; }

    .w-pill { display:inline-flex; align-items:center; gap:6px; padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
    .w-pill-primary { background:var(--w-red-100); color:var(--w-red-700); }

    .w-btn { font-family:inherit; font-weight:600; border-radius:10px; cursor:pointer; transition: all 150ms; display:inline-flex; align-items:center; justify-content:center; gap:10px; border:0; text-decoration:none; }
    .w-btn-xl { padding:18px 28px; font-size:17px; min-height:60px; }
    .w-btn-primary { background:var(--w-red-600); color:#fff; box-shadow:0 4px 14px -3px rgba(200,16,46,.4); }
    .w-btn-primary:hover { background:var(--w-red-700); transform:translateY(-1px); }
    .w-btn-outline { background:#fff; color:#111827; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(0,0,0,.04); }
    .w-btn-outline:hover { border-color:#9ca3af; background:#fafaf9; }

    .w-error { margin-bottom:14px; border-radius:10px; background:#fef2f2; border:1px solid #fecaca; padding:12px 14px; font-size:13px; color:#991b1b; }
</style>
@endpush

@section('content')
<div class="w-shell" style="margin:0 auto;" x-data="{ confirmed: {{ $activeBooking ? 'false' : 'true' }} }">
    @if($activeBooking)
        {{-- Walk-in confirm guard --}}
        <div x-show="!confirmed" x-cloak class="w-card">
            <div class="w-confirm-head">
                <div class="w-detect-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    <span>BOOKING TERDETEKSI</span>
                </div>
                <div class="w-reserved-by">Komputer ini dipesan oleh:</div>
                <h1 class="w-name">{{ $activeBooking->user?->name ?? '—' }}</h1>
                <div style="margin-top:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <span class="w-time-card">{{ $activeBooking->start_time }} – {{ $activeBooking->end_time }}</span>
                    <span style="font-size:14px;color:#6b7280;">Hari ini, {{ $activeBooking->booking_date->translatedFormat('j F Y') }}</span>
                </div>
            </div>

            <div class="w-confirm-body">
                <p class="w-confirm-text">
                    Anda akan <strong style="color:#111827;">check-in di bawah booking ini</strong>. Sesi ini milik
                    <strong style="color:var(--w-red-600);">{{ $activeBooking->user?->name ?? '—' }}</strong>. Apakah Anda yakin?
                </p>

                <div class="w-grid-2">
                    <a href="{{ route('kiosk.checkin', $computer->checkin_slug) }}" class="w-btn w-btn-outline w-btn-xl" style="width:100%;">
                        Tidak, kembali
                    </a>
                    <button type="button" @click="confirmed = true" class="w-btn w-btn-primary w-btn-xl" style="width:100%;">
                        Ya, lanjutkan
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Walk-in form --}}
    <div x-show="confirmed" x-cloak class="w-card">
        <div class="w-form-head">
            <div class="w-grid-bg"></div>
            <div style="position:relative;">
                <div class="w-eyebrow">Check-in Walk-in</div>
                <h1 class="w-h1">Check-in ke komputer ini</h1>
                <div class="w-strip-pill">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/></svg>
                    <span>{{ $computer->name }}</span>
                    @if($computer->room)
                        <span style="opacity:.5;">·</span>
                        <span style="opacity:.85;">{{ $computer->room->name }}</span>
                    @endif
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('kiosk.checkin.other.submit', $computer->checkin_slug) }}" style="padding:32px 40px;">
            @csrf
            @if($errors->any())
                <div class="w-error">
                    <ul style="margin:0;padding-left:18px;">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="w-field">
                <label class="w-label">Alamat email</label>
                <input type="email" name="email" required value="{{ old('email') }}" placeholder="email@example.com" class="w-input">
                <div class="w-hint">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    Jika email Anda belum terdaftar, Anda akan diarahkan ke pendaftaran.
                </div>
            </div>

            <div class="w-field">
                <label class="w-label">Pilih Sesi</label>
                <div style="display:flex;flex-direction:column;gap:8px;" x-data="{ selected: 0 }">
                    @foreach($sessions as $i => $sess)
                        <label class="w-session-card" :class="selected === {{ $i }} ? '' : 'unchecked'">
                            <input type="radio" name="session_index" value="{{ $i }}" @click="selected = {{ $i }}" @if($i === 0) checked @endif required style="position:absolute;opacity:0;pointer-events:none;">
                            <span class="w-session-radio" :class="selected === {{ $i }} ? 'checked' : ''"></span>
                            <div style="flex:1;">
                                <div style="font-size:14px;font-weight:700;color:#111827;">{{ $sess['label'] ?? 'Sesi' }}</div>
                                @if(! empty($sess['start']) && ! empty($sess['end']))
                                    <div style="font-family:'JetBrains Mono', monospace;font-size:14px;color:#831021;font-weight:600;margin-top:2px;">{{ $sess['start'] }} – {{ $sess['end'] }}</div>
                                @endif
                            </div>
                            @if(! empty($sess['is_night']))
                                <span class="w-pill" style="background:#fef3c7;color:#854d0e;">Malam</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="w-field">
                <label class="w-label">Untuk apa Anda menggunakannya?</label>
                <textarea name="purpose" required class="w-textarea"
                          placeholder="Misal: editing tugas akhir, render video, dll.">{{ old('purpose') }}</textarea>
            </div>

            <div class="w-grid-2" style="margin-top:8px;">
                <a href="{{ route('kiosk.checkin', $computer->checkin_slug) }}" class="w-btn w-btn-outline w-btn-xl" style="width:100%;">Batal</a>
                <button type="submit" class="w-btn w-btn-primary w-btn-xl" style="width:100%;">
                    Check-in
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
