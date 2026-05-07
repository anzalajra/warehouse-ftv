@extends('layouts.frontend')

@section('title', $computer->name)

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --cb-red-50:  #fff1f2;
            --cb-red-100: #ffe4e6;
            --cb-red-200: #fecdd3;
            --cb-red-300: #fda4af;
            --cb-red-600: #c8102e;
            --cb-red-700: #a30b25;
            --cb-red-800: #831021;
        }
        .cb-page { background:#fafaf9; min-height: calc(100vh - 4rem); font-family: 'Figtree', system-ui, sans-serif; }
        .cb-back-btn { background:transparent; border:0; padding:8px 0; color:var(--cb-red-600); font-size:14px; font-weight:600; display:inline-flex; align-items:center; gap:6px; cursor:pointer; }
        .cb-card { background:#fff; border-radius:16px; border:1px solid #ececec; box-shadow:0 1px 3px rgba(17,24,39,0.04), 0 1px 2px rgba(17,24,39,0.03); }
        .cb-hero-grid { display:grid; grid-template-columns: 420px 1fr; gap:0; }
        @media (max-width: 900px) { .cb-hero-grid { grid-template-columns: 1fr; } }
        .cb-hero-photo { background: linear-gradient(135deg,#fafafa 0%,#f3f4f6 100%); position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center; aspect-ratio:1/1; }
        .cb-hero-photo .grid-bg { position:absolute; inset:0; background-image: radial-gradient(circle at 1px 1px,#e5e7eb 1px,transparent 0); background-size:24px 24px; opacity:.6; }
        .cb-hero-photo img { width:100%; height:100%; object-fit:cover; position:absolute; inset:0; }
        .cb-id-badge { position:absolute; top:16px; left:16px; background:#fff; padding:6px 10px; border-radius:8px; font-size:11px; font-weight:700; letter-spacing:.08em; color:#6b7280; box-shadow:0 1px 3px rgba(0,0,0,0.06); z-index:2; text-transform:uppercase; }
        .cb-hero-info { padding:32px; display:flex; flex-direction:column; }
        .cb-room-pin { display:flex; align-items:center; gap:8px; margin-bottom:8px; color:var(--cb-red-600); font-size:12px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; }
        .cb-name { font-size: clamp(24px, 3.4vw, 34px); font-weight:800; margin:0; letter-spacing:-0.02em; line-height:1.1; color:#111827; }
        .cb-brand-row { display:flex; align-items:center; gap:12px; margin-top:12px; }
        .cb-pill { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:9999px; font-size:12px; font-weight:600; letter-spacing:.01em; }
        .cb-pill-success { background:#dcfce7; color:#15803d; }
        .cb-pill-warn    { background:#fef3c7; color:#854d0e; }
        .cb-pill-live    { background:#fef2f2; color:var(--cb-red-600); }
        .cb-pill-primary { background:var(--cb-red-100); color:var(--cb-red-700); }
        .cb-pill-dot { width:7px; height:7px; border-radius:9999px; }
        .cb-spec-label { font-size:12px; font-weight:600; letter-spacing:.06em; color:#6b7280; text-transform:uppercase; margin-bottom:8px; }
        .cb-spec-row { display:grid; grid-template-columns: 140px 1fr; padding:10px 0; border-bottom:1px solid #f3f4f6; font-size:14px; }
        .cb-spec-row .k { color:#6b7280; font-weight:500; }
        .cb-spec-row .v { color:#111827; font-weight:600; font-family:'JetBrains Mono', ui-monospace, Menlo, Consolas, monospace; }
        .cb-step-bar { padding:24px 32px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between; gap:24px; flex-wrap:wrap; }
        .cb-step-eyebrow { font-size:11px; font-weight:700; letter-spacing:.1em; color:var(--cb-red-600); text-transform:uppercase; }
        .cb-step-title { font-size:22px; font-weight:800; color:#111827; margin-top:4px; letter-spacing:-0.01em; }
        .cb-stepper { display:flex; align-items:center; }
        .cb-step-circle { width:32px; height:32px; border-radius:9999px; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; transition: all 200ms; }
        .cb-step-circle.idle { background:#fff; color:#9ca3af; border:1.5px solid #e5e7eb; }
        .cb-step-circle.on { background:var(--cb-red-600); color:#fff; box-shadow:0 4px 12px -3px rgba(200,16,46,.4); }
        .cb-step-line { flex:1; min-width:40px; height:2px; margin:0 16px; background:#e5e7eb; }
        .cb-step-line.on { background:var(--cb-red-600); }
        .cb-section-label { display:flex; align-items:baseline; gap:10px; }
        .cb-section-num { width:24px; height:24px; border-radius:9999px; background:var(--cb-red-50); color:var(--cb-red-600); font-size:12px; font-weight:800; display:inline-flex; align-items:center; justify-content:center; }
        .cb-section-text { font-size:16px; font-weight:700; color:#111827; }
        .cb-section-hint { font-size:13px; color:#6b7280; font-weight:500; }
        .cb-grid { display:grid; gap:40px; grid-template-columns: 1.1fr 1fr; }
        @media (max-width: 900px) { .cb-grid { grid-template-columns: 1fr; gap:28px; } }
        .cb-flatpickr-wrap .flatpickr-calendar.inline { box-shadow:none; border:1px solid #ececec; border-radius:14px; padding:10px; width:100%; max-width:none; }
        .cb-flatpickr-wrap .flatpickr-day.selected { background:var(--cb-red-600) !important; border-color:var(--cb-red-600) !important; box-shadow:0 4px 12px -2px rgba(200,16,46,.4); }
        .cb-flatpickr-wrap .flatpickr-day.today { color:var(--cb-red-600); border-color:transparent; }
        .cb-flatpickr-wrap .flatpickr-day:hover { background:#f3f4f6; }
        .cb-date-info { margin-top:14px; padding:12px 14px; background:#fafaf9; border-radius:10px; font-size:13px; color:#374151; display:flex; align-items:center; gap:8px; }
        .cb-slot-list { display:flex; flex-direction:column; gap:10px; }
        .cb-slot { text-align:left; padding:14px 16px; border-radius:12px; border:1.5px solid #e5e7eb; background:#fff; transition: all 150ms; cursor:pointer; font-family:inherit; width:100%; }
        .cb-slot:hover:not(:disabled) { border-color: var(--cb-red-300); }
        .cb-slot.selected { border-color:var(--cb-red-600); background:var(--cb-red-50); box-shadow:0 4px 12px -3px rgba(200,16,46,.2); }
        .cb-slot:disabled { opacity:.55; cursor:not-allowed; background:#fafafa; }
        .cb-slot-row1 { display:flex; align-items:center; justify-content:space-between; margin-bottom:4px; }
        .cb-slot-time { font-size:16px; font-weight:700; color:#111827; font-family:'JetBrains Mono',ui-monospace,Menlo,Consolas,monospace; letter-spacing:-0.01em; }
        .cb-slot-row2 { display:flex; align-items:center; gap:8px; font-size:12px; color:#6b7280; font-weight:500; }
        .cb-slot-check { width:22px; height:22px; border-radius:9999px; background:var(--cb-red-600); color:#fff; display:flex; align-items:center; justify-content:center; }
        .cb-slot-pill-sm { padding:2px 8px; border-radius:9999px; font-size:11px; font-weight:600; }
        .cb-slot-pill-sm.warn { background:#fef3c7; color:#854d0e; }
        .cb-slot-pill-sm.danger { background:#fee2e2; color:#991b1b; }
        .cb-summary-banner { margin-top:16px; padding:12px 14px; background:var(--cb-red-50); border-radius:10px; font-size:13px; color:var(--cb-red-800); font-weight:600; display:flex; align-items:center; gap:8px; }
        .cb-summary-card { margin-top:14px; background: linear-gradient(135deg,#fafaf9 0%,#f3f4f6 100%); border:1px solid #ececec; border-radius:14px; padding:22px; display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        @media (max-width:700px){ .cb-summary-card{ grid-template-columns:1fr; } }
        .cb-summary-label { font-size:11px; font-weight:700; letter-spacing:.08em; color:#6b7280; text-transform:uppercase; }
        .cb-summary-value { font-size:16px; font-weight:700; color:#111827; margin-top:4px; }
        .cb-summary-sub { font-size:13px; color:#6b7280; margin-top:2px; }
        .cb-slot-tag { padding:4px 10px; border-radius:9999px; background:#fff; border:1px solid var(--cb-red-300); color:var(--cb-red-800); font-size:13px; font-weight:600; font-family:'JetBrains Mono',ui-monospace,Menlo,Consolas,monospace; }
        .cb-warn-banner { margin-top:18px; padding:14px 18px; background:#fefce8; border:1.5px solid #fcd34d; border-radius:12px; display:flex; gap:14px; }
        .cb-warn-title { font-size:14px; font-weight:700; color:#854d0e; }
        .cb-warn-body { font-size:13px; color:#a16207; margin-top:4px; line-height:1.5; }
        .cb-textarea { width:100%; min-height:90px; padding:12px 14px; border:1.5px solid #e5e7eb; border-radius:10px; font-size:14px; color:#111827; outline:none; resize:vertical; transition:border-color 150ms; font-family:inherit; }
        .cb-textarea:focus { border-color:var(--cb-red-600); }
        .cb-tnc-box { padding:12px 16px; background:#fafaf9; border-radius:10px; font-size:13px; }
        .cb-tnc-title { font-weight:700; color:#111827; margin-bottom:4px; }
        .cb-tnc-body { color:#6b7280; line-height:1.5; white-space:pre-line; }
        .cb-check-row { display:flex; align-items:flex-start; gap:10px; cursor:pointer; }
        .cb-check-box { width:20px; height:20px; border-radius:6px; flex-shrink:0; background:#fff; border:1.5px solid #d1d5db; display:flex; align-items:center; justify-content:center; transition:all 150ms; margin-top:1px; }
        .cb-check-row input { position:absolute; opacity:0; pointer-events:none; }
        .cb-check-row input:checked + .cb-check-box { background:var(--cb-red-600); border-color:var(--cb-red-600); box-shadow:0 2px 6px -1px rgba(200,16,46,.3); }
        .cb-check-row input:checked + .cb-check-box svg { display:block; }
        .cb-check-box svg { display:none; }
        .cb-footer { padding:20px 32px; border-top:1px solid #f3f4f6; background:#fafaf9; border-radius:0 0 16px 16px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
        .cb-btn { font-family:inherit; font-weight:600; border-radius:10px; cursor:pointer; transition: all 150ms; display:inline-flex; align-items:center; justify-content:center; gap:8px; }
        .cb-btn:disabled { cursor:not-allowed; opacity:.55; }
        .cb-btn-primary { padding:14px 24px; font-size:15.5px; min-height:50px; background:var(--cb-red-600); color:#fff; border:0; box-shadow:0 1px 2px rgba(0,0,0,.05), 0 4px 14px -3px rgba(200,16,46,.35); }
        .cb-btn-primary:hover:not(:disabled){ background:var(--cb-red-700); }
        .cb-btn-primary:disabled { background:#e5e7eb; color:#9ca3af; box-shadow:none; }
        .cb-btn-outline { padding:11px 20px; font-size:14.5px; min-height:42px; background:#fff; color:#111827; border:1px solid #e5e7eb; box-shadow:0 1px 2px rgba(0,0,0,.04); }
        .cb-btn-outline:hover { border-color:#9ca3af; background:#fafaf9; }
        .cb-btn-ghost { padding:11px 20px; font-size:14.5px; min-height:42px; background:transparent; color:#374151; border:0; }
        .cb-btn-ghost:hover { background:#f3f4f6; }
        .cb-error { margin-bottom:14px; border-radius:10px; background:#fef2f2; border:1px solid #fecaca; padding:12px 14px; font-size:13px; color:#991b1b; }
        .cb-fade { animation: cb-fadein 300ms cubic-bezier(0.16,1,0.3,1) both; }
        @keyframes cb-fadein { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
        .cb-hidden { display:none !important; }
    </style>
@endpush

@section('content')
<div class="cb-page" x-data="computerBookingWizard()" x-init="init()" x-cloak>
    <div style="max-width:1280px;margin:0 auto;padding:24px 32px 64px;">
        <a href="{{ $computer->room ? route('computers.rooms.show', $computer->room) : route('computers.index') }}" class="cb-back-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Kembali
        </a>

        {{-- Computer Hero --}}
        <div class="cb-card" style="margin-top:20px;overflow:hidden;">
            <div class="cb-hero-grid">
                <div class="cb-hero-photo">
                    <div class="grid-bg"></div>
                    <div class="cb-id-badge">PC-{{ str_pad($computer->id, 3, '0', STR_PAD_LEFT) }}</div>
                    @if($computer->image_path)
                        <img src="{{ asset('storage/'.$computer->image_path) }}" alt="{{ $computer->name }}">
                    @else
                        <div style="position:relative;width:240px;height:180px;transform:perspective(800px) rotateX(8deg);">
                            <div style="position:absolute;inset:0;background:linear-gradient(180deg,#1f2937 0%,#111827 100%);border-radius:12px 12px 4px 4px;border:2px solid #0a0a0a;padding:8px;box-shadow:0 30px 60px -20px rgba(0,0,0,.4);">
                                <div style="width:100%;height:100%;background:linear-gradient(135deg,#c8102e 0%,#831021 100%);border-radius:4px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:32px;font-weight:800;letter-spacing:-0.03em;">FTV</div>
                            </div>
                            <div style="position:absolute;bottom:-16px;left:20%;right:20%;height:12px;background:#1f2937;border-radius:0 0 8px 8px;"></div>
                        </div>
                    @endif
                </div>
                <div class="cb-hero-info">
                    @if($computer->room)
                        <div class="cb-room-pin">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-7.5-7-12.5a7 7 0 1 1 14 0c0 5-7 12.5-7 12.5Z"/><circle cx="12" cy="9" r="2.5"/></svg>
                            <span>{{ $computer->room->name }}</span>
                        </div>
                    @endif
                    <h1 class="cb-name">{{ $computer->name }}</h1>
                    <div class="cb-brand-row">
                        @if($computer->brand)
                            <span style="font-size:15px;color:#6b7280;font-weight:500;">{{ $computer->brand }}</span>
                            <span style="width:4px;height:4px;border-radius:9999px;background:#d1d5db;"></span>
                        @endif
                        @if($computer->status === \App\Models\Computer::STATUS_AVAILABLE)
                            <span class="cb-pill cb-pill-success"><span class="cb-pill-dot" style="background:#16a34a;"></span>Tersedia</span>
                        @else
                            <span class="cb-pill cb-pill-warn"><span class="cb-pill-dot" style="background:#ca8a04;"></span>Maintenance</span>
                        @endif
                    </div>

                    @if(! empty($computer->specs))
                        <div style="margin-top:20px;">
                            <div class="cb-spec-label">Spesifikasi</div>
                            @foreach($computer->specs as $key => $value)
                                <div class="cb-spec-row">
                                    <span class="k">{{ $key }}</span>
                                    <span class="v">{{ $value }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($computer->notes)
                        <p style="margin-top:14px;font-size:13px;color:#6b7280;">{{ $computer->notes }}</p>
                    @endif
                </div>
            </div>
        </div>

        @if($computer->status === \App\Models\Computer::STATUS_AVAILABLE)
        {{-- Wizard Card --}}
        <div class="cb-card" style="margin-top:24px;overflow:hidden;">
            {{-- Step header --}}
            <div class="cb-step-bar">
                <div>
                    <div class="cb-step-eyebrow" x-text="'Langkah ' + step + ' dari 2'"></div>
                    <div class="cb-step-title" x-text="step === 1 ? 'Kapan Anda membutuhkannya?' : 'Review & konfirmasi'"></div>
                </div>
                <div class="cb-stepper">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="cb-step-circle" :class="step >= 1 ? 'on' : 'idle'">
                            <template x-if="step > 1">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.75 6 6 9-13.5"/></svg>
                            </template>
                            <template x-if="step <= 1"><span>1</span></template>
                        </div>
                        <span style="font-size:13px;font-weight:600;color:#111827;">Pilih tanggal & waktu</span>
                    </div>
                    <div class="cb-step-line" :class="step > 1 ? 'on' : ''"></div>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div class="cb-step-circle" :class="step === 2 ? 'on' : 'idle'"><span>2</span></div>
                        <span style="font-size:13px;font-weight:600;" :style="step === 2 ? 'color:#111827' : 'color:#9ca3af'">Konfirmasi booking</span>
                    </div>
                </div>
            </div>

            @if($errors->any())
                <div style="padding:0 32px;margin-top:16px;">
                    <div class="cb-error">
                        <ul style="margin:0;padding-left:18px;">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- Step 1 --}}
            <div x-show="step === 1" class="cb-fade" style="padding:32px;">
                <div class="cb-grid">
                    <div>
                        <div class="cb-section-label">
                            <span class="cb-section-num">1</span>
                            <span class="cb-section-text">Pilih tanggal</span>
                        </div>
                        <div class="cb-flatpickr-wrap" style="margin-top:14px;" wire:ignore>
                            <input type="text" id="booking-date" class="cb-hidden">
                        </div>
                        <div class="cb-date-info" x-show="date">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25"/><path d="M3 18.75A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75M3 18.75V9A2.25 2.25 0 0 1 5.25 6.75h13.5A2.25 2.25 0 0 1 21 9v9.75"/></svg>
                            <span>Dipilih: <strong style="color:#111827;" x-text="date ? formatDate(date) : ''"></strong></span>
                        </div>
                        <p x-show="!date" style="font-size:13px;color:#6b7280;margin-top:10px;">Pilih tanggal terlebih dahulu.</p>
                    </div>

                    <div>
                        <div class="cb-section-label">
                            <span class="cb-section-num">2</span>
                            <span class="cb-section-text">Pilih slot waktu</span>
                            <span class="cb-section-hint">(boleh pilih lebih dari 1)</span>
                        </div>

                        <template x-if="!date">
                            <p style="margin-top:14px;font-size:13px;color:#6b7280;font-style:italic;">Pilih tanggal dulu di kalender.</p>
                        </template>
                        <template x-if="date && loading">
                            <p style="margin-top:14px;font-size:13px;color:#6b7280;">Memuat slot…</p>
                        </template>
                        <template x-if="date && !loading && slots.length === 0">
                            <p style="margin-top:14px;font-size:13px;color:#6b7280;">Tidak ada slot operasional di tanggal ini.</p>
                        </template>

                        <div x-show="date && !loading && slots.length > 0" class="cb-slot-list" style="margin-top:14px;">
                            <template x-for="slot in slots" :key="slot.start + '-' + slot.end">
                                <button type="button" class="cb-slot"
                                        :class="isSelected(slot) ? 'selected' : ''"
                                        :disabled="!slot.available"
                                        @click="toggleSlot(slot)">
                                    <div class="cb-slot-row1">
                                        <span class="cb-slot-time" x-text="slot.start + ' – ' + slot.end"></span>
                                        <template x-if="isSelected(slot)">
                                            <span class="cb-slot-check">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.75 6 6 9-13.5"/></svg>
                                            </span>
                                        </template>
                                    </div>
                                    <div class="cb-slot-row2">
                                        <span x-text="slotPeriodLabel(slot)"></span>
                                        <template x-if="slot.is_night">
                                            <span class="cb-slot-pill-sm warn">Malam</span>
                                        </template>
                                        <template x-if="!slot.available">
                                            <span class="cb-slot-pill-sm danger">Penuh</span>
                                        </template>
                                    </div>
                                </button>
                            </template>
                        </div>

                        <div x-show="selected.length > 0" class="cb-summary-banner">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            <strong x-text="selected.length"></strong>
                            <span>slot dipilih</span>
                            <span style="color:var(--cb-red-600);">·</span>
                            <span x-text="totalDurationLabel"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 2 --}}
            <div x-show="step === 2" class="cb-fade" style="padding:32px;">
                @auth('customer')
                <form id="cb-form" method="POST" action="{{ route('customer.computer-bookings.store') }}">
                    @csrf
                    <input type="hidden" name="computer_id" value="{{ $computer->id }}">
                    <input type="hidden" name="booking_date" :value="date">
                    <template x-for="(slot, idx) in selected" :key="slot.start + '-' + slot.end">
                        <div>
                            <input type="hidden" :name="`slots[${idx}][start]`" :value="slot.start">
                            <input type="hidden" :name="`slots[${idx}][end]`" :value="slot.end">
                        </div>
                    </template>

                    <div class="cb-section-label">
                        <span class="cb-section-num">3</span>
                        <span class="cb-section-text">Review booking Anda</span>
                    </div>

                    <div class="cb-summary-card">
                        <div>
                            <div class="cb-summary-label">Komputer</div>
                            <div class="cb-summary-value">{{ $computer->name }}</div>
                            <div class="cb-summary-sub">{{ $computer->brand }}@if($computer->room) · {{ $computer->room->name }}@endif</div>
                        </div>
                        <div>
                            <div class="cb-summary-label">Tanggal</div>
                            <div class="cb-summary-value" x-text="date ? formatDate(date) : ''"></div>
                        </div>
                        <div>
                            <div class="cb-summary-label">Slot waktu</div>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
                                <template x-for="slot in selected" :key="slot.start + '-' + slot.end">
                                    <span class="cb-slot-tag" x-text="slot.start + ' – ' + slot.end"></span>
                                </template>
                            </div>
                        </div>
                        <div>
                            <div class="cb-summary-label">Durasi total</div>
                            <div class="cb-summary-value" x-text="totalDurationLabel"></div>
                        </div>
                    </div>

                    <template x-if="hasNightSlot">
                        <div class="cb-warn-banner">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#a16207" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:2px;"><path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                            <div>
                                <div class="cb-warn-title">Booking jam malam</div>
                                <div class="cb-warn-body">Booking jam malam diwajibkan mengurus perizinan menginap di kampus. Pastikan Anda sudah mengurusnya.</div>
                            </div>
                        </div>
                    </template>

                    <div style="margin-top:24px;">
                        <label for="purpose" style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:8px;">Untuk apa Anda menggunakannya?</label>
                        <textarea name="purpose" id="purpose" rows="3" required
                                  class="cb-textarea"
                                  placeholder="Misal: editing tugas akhir mata kuliah Dokumenter">{{ old('purpose') }}</textarea>
                    </div>

                    <div style="margin-top:24px;">
                        <div class="cb-tnc-box">
                            <div class="cb-tnc-title">Syarat & Ketentuan</div>
                            <div class="cb-tnc-body">{{ \App\Services\ComputerValidationService::tncText() }}</div>
                        </div>

                        <div style="margin-top:14px;display:flex;flex-direction:column;gap:10px;">
                            <label class="cb-check-row">
                                <input type="checkbox" name="tnc" value="1" required>
                                <span class="cb-check-box">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.75 6 6 9-13.5"/></svg>
                                </span>
                                <span style="font-size:14px;color:#374151;line-height:1.45;">Saya setuju dengan Syarat & Ketentuan di atas.</span>
                            </label>

                            @php
                                $permitRequired = (bool) (\App\Models\Setting::get('computer_night_permit_required') ?? true);
                                $permitText = \App\Models\Setting::get('computer_night_permit_text') ?? 'Saya sudah memiliki perizinan menginap di kampus.';
                            @endphp
                            <template x-if="hasNightSlot">
                                <label class="cb-check-row">
                                    <input type="checkbox" name="permit" value="1" {{ $permitRequired ? 'required' : '' }}>
                                    <span class="cb-check-box">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.75 6 6 9-13.5"/></svg>
                                    </span>
                                    <span style="font-size:14px;color:#374151;line-height:1.45;white-space:pre-line;">{{ $permitText }}</span>
                                </label>
                            </template>
                        </div>
                    </div>
                </form>
                @else
                    <div class="cb-error" style="background:#eff6ff;border-color:#bfdbfe;color:#1e40af;">
                        <p style="font-weight:700;margin:0 0 6px 0;">Login diperlukan</p>
                        <p style="margin:0 0 12px 0;">Silakan login menggunakan akun warehouse untuk melanjutkan booking.</p>
                        <a href="{{ route('customer.login') }}" class="cb-btn cb-btn-primary" style="text-decoration:none;">Login</a>
                    </div>
                @endauth
            </div>

            {{-- Footer nav --}}
            <div class="cb-footer">
                <div>
                    <button type="button" x-show="step === 1" class="cb-btn cb-btn-ghost" @click="window.location.href='{{ $computer->room ? route('computers.rooms.show', $computer->room) : route('computers.index') }}'">Batal</button>
                    <button type="button" x-show="step === 2" class="cb-btn cb-btn-outline" @click="step = 1; scrollToTop()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                        Kembali
                    </button>
                </div>
                <div>
                    <button type="button" x-show="step === 1" class="cb-btn cb-btn-primary"
                            :disabled="!canNext"
                            @click="step = 2; scrollToTop()">
                        Lanjut
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </button>
                    @auth('customer')
                    <button type="button" x-show="step === 2" class="cb-btn cb-btn-primary"
                            @click="document.getElementById('cb-form').submit()">
                        Kirim booking
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m4.5 12.75 6 6 9-13.5"/></svg>
                    </button>
                    @endauth
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
function computerBookingWizard() {
    return {
        step: 1,
        date: null,
        slots: [],
        selected: [],
        loading: false,
        flatpickrInstance: null,

        init() {
            const today = new Date();
            today.setHours(0,0,0,0);

            this.flatpickrInstance = flatpickr('#booking-date', {
                inline: true,
                minDate: today,
                dateFormat: 'Y-m-d',
                onChange: (selectedDates, dateStr) => {
                    this.date = dateStr;
                    this.selected = [];
                    this.loadSlots();
                },
            });
        },

        async loadSlots() {
            if (!this.date) return;
            this.loading = true;
            try {
                const res = await fetch('{{ route('computers.availability', $computer) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ date: this.date }),
                });
                const data = await res.json();
                this.slots = data.slots || [];
            } finally {
                this.loading = false;
            }
        },

        toggleSlot(slot) {
            if (!slot.available) return;
            const idx = this.selected.findIndex(s => s.start === slot.start && s.end === slot.end);
            if (idx >= 0) {
                this.selected.splice(idx, 1);
            } else {
                this.selected.push({ start: slot.start, end: slot.end, is_night: slot.is_night });
                this.selected.sort((a, b) => a.start.localeCompare(b.start));
            }
        },

        isSelected(slot) {
            return this.selected.some(s => s.start === slot.start && s.end === slot.end);
        },

        slotPeriodLabel(slot) {
            if (slot.is_night) return 'Jam Malam';
            const h = parseInt((slot.start || '00:00').slice(0, 2), 10);
            if (h < 11) return 'Pagi';
            if (h < 14) return 'Siang';
            if (h < 17) return 'Sore';
            if (h < 20) return 'Petang';
            return 'Malam';
        },

        get hasNightSlot() {
            return this.selected.some(s => s.is_night);
        },

        get canNext() {
            return !!this.date && this.selected.length > 0;
        },

        get totalDurationLabel() {
            let total = 0;
            this.selected.forEach(s => {
                const [h1, m1] = s.start.split(':').map(Number);
                const [h2, m2] = s.end.split(':').map(Number);
                let mins = (h2 * 60 + m2) - (h1 * 60 + m1);
                if (mins < 0) mins += 24 * 60; // wraps midnight
                total += mins;
            });
            const hours = Math.floor(total / 60);
            const mins = total % 60;
            return mins ? `${hours}j ${mins}m` : `${hours} jam`;
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        },

        scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    };
}
</script>
@endpush
@endsection
