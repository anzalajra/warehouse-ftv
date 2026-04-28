@extends('layouts.frontend')

@section('title', 'Rental Schedule')

@php
    $buildUrl = function (array $overrides = []) use ($filter, $view_mode, $anchor, $search, $perPage) {
        $params = array_filter([
            'filter' => $filter,
            'view_mode' => $view_mode,
            'anchor' => $anchor,
            'search' => $search !== '' ? $search : null,
            'perPage' => $perPage !== 15 ? $perPage : null,
        ], fn ($v) => $v !== null);
        $params = array_merge($params, $overrides);
        $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
        return route('frontend.schedule') . (count($params) ? '?' . http_build_query($params) : '');
    };

    $navAnchor = function (int $direction) use ($anchor, $filter, $view_mode) {
        $a = \Illuminate\Support\Carbon::parse($anchor);
        $unit = $filter === 'product' ? 'month' : match ($view_mode) {
            'day' => 'day',
            'week' => 'week',
            default => 'month',
        };
        return ($direction >= 0 ? $a->add($unit, 1) : $a->sub($unit, 1))->toDateString();
    };
@endphp

@push('styles')
<style>
    .gr-shell { background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; }

    /* FilterBar */
    .gr-filterbar { display:flex; align-items:center; gap:8px; padding:10px 14px; border-bottom:1px solid #f3f4f6; background:#fff; flex-wrap:wrap; }
    .gr-pills { display:inline-flex; background:#f3f4f6; border-radius:10px; padding:3px; }
    .gr-pill { padding:7px 16px; border-radius:7px; border:none; cursor:pointer; background:transparent; color:#6b7280; font-size:12px; font-weight:700; font-family:inherit; text-decoration:none; display:inline-block; }
    .gr-pill.on { background:#fff; color:#111827; box-shadow:0 1px 3px rgba(0,0,0,.08); }
    .gr-dd { position:relative; }
    .gr-dd-btn { display:inline-flex; align-items:center; gap:5px; padding:8px 12px; border-radius:8px; background:#fff; border:1px solid #e5e7eb; font-size:12px; font-weight:700; color:#1f2937; cursor:pointer; font-family:inherit; }
    .gr-dd-menu { position:absolute; top:calc(100% + 4px); left:0; z-index:30; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); overflow:hidden; min-width:130px; display:none; }
    .gr-dd-menu.open { display:block; }
    .gr-dd-item { width:100%; text-align:left; padding:10px 14px; border:none; background:#fff; color:#111827; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit; display:flex; align-items:center; gap:8px; border-bottom:1px solid #f3f4f6; text-decoration:none; }
    .gr-dd-item:last-child { border-bottom:none; }
    .gr-dd-item.on { background:#eff6ff; color:#1d4ed8; font-weight:700; }

    /* GC toolbar */
    .gr-toolbar { display:flex; align-items:center; gap:10px; padding:10px 14px; border-bottom:1px solid #f3f4f6; background:#fff; }
    .gr-today { height:30px; padding:0 14px; border:1px solid #d1d5db; border-radius:999px; background:#fff; color:#374151; font-size:12px; font-weight:600; cursor:pointer; font-family:inherit; text-decoration:none; display:inline-flex; align-items:center; }
    .gr-nav { width:30px; height:30px; border-radius:999px; border:none; background:transparent; display:grid; place-items:center; cursor:pointer; color:#4b5563; text-decoration:none; }
    .gr-nav:hover { background:#f3f4f6; }
    .gr-label { font-size:17px; font-weight:700; color:#111827; letter-spacing:-.3px; }

    /* Legend */
    .gr-legend { display:flex; align-items:center; flex-wrap:wrap; gap:6px 14px; padding:9px 16px; border-bottom:1px solid #f3f4f6; background:#fff; }
    .gr-legend-item { display:flex; align-items:center; gap:5px; }
    .gr-legend-dot { width:10px; height:10px; border-radius:999px; flex-shrink:0; }
    .gr-legend-text { font-size:11px; font-weight:600; color:#374151; }

    /* Event bar */
    .gr-event { border-radius:5px; padding:3px 8px; display:flex; flex-direction:column; justify-content:center; overflow:hidden; cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,.12); color:#fff; border:none; font-family:inherit; text-align:left; }
    .gr-event:hover { filter: brightness(1.05); }
    .gr-event-title { font-size:10px; font-weight:700; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.2; }
    .gr-event-sub { font-size:9px; color:rgba(255,255,255,.8); margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

    /* Month grid */
    .gr-month-grid { flex:1; display:flex; flex-direction:column; overflow:auto; background:#fff; }
    .gr-dow-row { display:grid; grid-template-columns:repeat(7,1fr); position:sticky; top:0; z-index:3; background:#fff; border-bottom:1px solid #f3f4f6; }
    .gr-dow { padding:7px 8px; font-size:10px; font-weight:700; color:#6b7280; text-align:center; letter-spacing:.3px; text-transform:uppercase; }
    .gr-week-row { position:relative; display:grid; grid-template-columns:repeat(7,1fr); border-bottom:1px solid #f3f4f6; min-height:108px; }
    .gr-day-cell { border-right:1px solid #f3f4f6; padding:5px 7px 2px; min-height:108px; }
    .gr-day-cell:last-child { border-right:none; }
    .gr-day-cell.other { background:#fafafa; }
    .gr-day-cell.today { background:rgba(59,130,246,.12); box-shadow:inset 0 2px 0 0 #3b82f6; }
    .gr-date { display:flex; justify-content:flex-start; padding-left:2px; }
    .gr-date-num { min-width:22px; height:22px; padding:0 4px; display:grid; place-items:center; font-size:11px; font-weight:500; color:#374151; }
    .gr-date-num.today { color:#1d4ed8; font-weight:800; }
    .gr-date-num.other { color:#d1d5db; }
    .gr-more-btn { position:absolute; border:none; background:transparent; font-size:10px; color:#2563eb; font-weight:700; cursor:pointer; padding:1px 3px; border-radius:4px; font-family:inherit; display:flex; align-items:center; gap:2px; text-decoration:none; }
    .gr-more-btn:hover { background:#eff6ff; }

    /* Week gantt */
    .gr-gantt-head { display:flex; position:sticky; top:0; z-index:3; background:#fff; border-bottom:1px solid #e5e7eb; }
    .gr-gantt-hd-booking { width:120px; flex-shrink:0; border-right:1px solid #f3f4f6; padding:8px 12px; font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; align-self:center; }
    .gr-gantt-days { display:flex; flex:1; }
    .gr-gantt-day { flex:1; padding:6px 0 4px; text-align:center; border-right:1px solid #f3f4f6; }
    .gr-gantt-day:last-child { border-right:none; }
    .gr-gantt-day.today { background:#eff6ff; }
    .gr-gantt-dow { font-size:9px; color:#9ca3af; font-weight:700; letter-spacing:.3px; }
    .gr-gantt-date { font-size:13px; font-weight:600; color:#374151; margin-top:1px; }
    .gr-gantt-date.today { color:#2563eb; font-weight:800; }
    .gr-gantt-body { flex:1; overflow-y:auto; }
    .gr-gantt-row { display:flex; min-height:54px; border-bottom:1px solid #f3f4f6; background:#fff; }
    .gr-gantt-row-label { width:120px; flex-shrink:0; padding:8px 12px; border-right:1px solid #f3f4f6; display:flex; flex-direction:column; justify-content:center; }
    .gr-gantt-row-customer { font-size:11px; font-weight:700; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .gr-gantt-row-dates { font-size:9px; color:#6b7280; margin-top:1px; }
    .gr-gantt-area { flex:1; position:relative; display:flex; }
    .gr-gantt-area-cell { flex:1; border-right:1px solid #f3f4f6; }
    .gr-gantt-area-cell:last-child { border-right:none; }
    .gr-gantt-area-cell.today { background:rgba(59,130,246,.08); }

    /* Day timeline */
    .gr-day-weekstrip { display:grid; grid-template-columns:repeat(7,1fr); padding:6px 6px 8px; background:#fff; border-bottom:1px solid #f3f4f6; }
    .gr-day-ws-btn { border:none; background:transparent; cursor:pointer; padding:4px 0; border-radius:8px; display:flex; flex-direction:column; align-items:center; gap:3px; font-family:inherit; color:inherit; text-decoration:none; }
    .gr-day-ws-dow { font-size:9px; color:#9ca3af; font-weight:600; letter-spacing:.3px; text-transform:uppercase; }
    .gr-day-ws-date { width:30px; height:30px; border-radius:999px; display:grid; place-items:center; font-size:13px; font-weight:600; color:#374151; }
    .gr-day-ws-date.on { background:#2563eb; color:#fff; font-weight:700; }
    .gr-day-ws-date.today { background:#eff6ff; color:#1d4ed8; }
    .gr-day-heading { padding:8px 14px 6px; border-bottom:1px solid #f3f4f6; }
    .gr-day-heading-eyebrow { font-size:11px; color:#2563eb; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
    .gr-day-heading-title { font-size:15px; font-weight:700; color:#111827; margin-top:1px; }
    .gr-day-allday { padding:6px 14px 6px 54px; border-bottom:1px solid #f3f4f6; display:flex; flex-direction:column; gap:3px; }
    .gr-day-timeline-wrap { flex:1; overflow-y:auto; }
    .gr-day-timeline { position:relative; }
    .gr-hour { position:absolute; left:0; right:0; border-top:1px solid #f3f4f6; }
    .gr-hour-label { position:absolute; left:0; top:-7px; width:46px; font-size:10px; color:#9ca3af; font-weight:500; text-align:right; padding-right:6px; }
    .gr-now-line { position:absolute; left:46px; right:0; z-index:3; pointer-events:none; }
    .gr-now-dot { position:absolute; left:-5px; top:-5px; width:10px; height:10px; border-radius:999px; background:#ef4444; }
    .gr-now-bar { height:2px; background:#ef4444; }

    /* By product */
    .gr-prod-toolbar { display:flex; align-items:center; gap:10px; padding:12px 18px; border-bottom:1px solid #f3f4f6; background:#fff; flex-wrap:wrap; }
    .gr-prod-title { font-size:18px; font-weight:800; color:#111827; letter-spacing:-.3px; }
    .gr-prod-search { display:flex; align-items:center; gap:10px; height:36px; padding:0 12px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; min-width:200px; }
    .gr-prod-search input { flex:1; border:none; outline:none; font-size:13px; color:#374151; background:transparent; font-family:inherit; }
    .gr-prod-scroll { flex:1; overflow:auto; position:relative; background:#fff; }
    .gr-prod-stickyhead { position:sticky; top:0; z-index:10; background:#fff; }
    .gr-prod-monthrow { display:flex; border-bottom:1px solid #f3f4f6; }
    .gr-prod-corner { flex-shrink:0; position:sticky; left:0; z-index:11; background:#fff; border-right:1px solid #e5e7eb; }
    .gr-prod-month { flex-shrink:0; padding:6px 0 6px 10px; border-right:1px solid #f3f4f6; font-size:11px; font-weight:800; color:#1d4ed8; letter-spacing:.3px; text-transform:uppercase; }
    .gr-prod-dayrow { display:flex; border-bottom:1px solid #e5e7eb; }
    .gr-prod-labelhead { flex-shrink:0; padding:8px 12px; font-size:10px; font-weight:800; color:#9ca3af; text-transform:uppercase; letter-spacing:.4px; position:sticky; left:0; z-index:11; background:#fff; border-right:1px solid #e5e7eb; display:flex; align-items:center; }
    .gr-prod-daycell-head { flex-shrink:0; text-align:center; padding:5px 0 6px; border-right:1px solid #f3f4f6; }
    .gr-prod-daycell-head.weekend { background:#f9fafb; }
    .gr-prod-daycell-head.today { background:#eff6ff; }
    .gr-prod-daycell-dow { font-size:9px; color:#9ca3af; font-weight:600; }
    .gr-prod-daycell-num { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:999px; margin-top:2px; font-size:11px; font-weight:500; color:#1f2937; }
    .gr-prod-daycell-num.today { background:#2563eb; color:#fff; font-weight:700; }
    .gr-prod-daycell-num.weekend { color:#6b7280; }
    .gr-prod-productrow { display:flex; border-bottom:1px solid #f3f4f6; background:#fafafa; }
    .gr-prod-productname { flex-shrink:0; padding:9px 12px; font-size:12px; font-weight:800; color:#ea580c; position:sticky; left:0; z-index:5; background:#fafafa; border-right:1px solid #e5e7eb; display:flex; align-items:center; letter-spacing:-.1px; }
    .gr-prod-productcell { flex-shrink:0; border-right:1px solid #f3f4f6; }
    .gr-prod-productcell.weekend { background:rgba(0,0,0,.015); }
    .gr-prod-productcell.today { background:rgba(59,130,246,.08); }
    .gr-prod-unitrow { display:flex; min-height:44px; border-bottom:1px solid #f3f4f6; background:#fff; }
    .gr-prod-unitlabel { flex-shrink:0; padding:8px 12px; border-right:1px solid #e5e7eb; position:sticky; left:0; z-index:4; background:#fff; display:flex; align-items:center; }
    .gr-prod-sku { font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:10px; font-weight:700; color:#374151; background:#f3f4f6; padding:3px 8px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .gr-prod-unitarea { display:flex; position:relative; flex:1; }
    .gr-prod-unitcell { flex-shrink:0; border-right:1px solid #f3f4f6; }
    .gr-prod-unitcell.weekend { background:rgba(0,0,0,.015); }
    .gr-prod-unitcell.today { background:rgba(59,130,246,.08); }
    .gr-prod-booking { position:absolute; top:6px; border-radius:5px; padding:0 8px; font-size:10px; font-weight:600; color:#fff; display:flex; align-items:center; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,.12); border:none; font-family:inherit; }

    /* Modal */
    .gr-modal-backdrop { position:fixed; inset:0; background:rgba(17,24,39,.55); z-index:100; display:flex; align-items:center; justify-content:center; padding:16px; }
    .gr-modal { background:#fff; border-radius:12px; max-width:480px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.3); overflow:hidden; }
    .gr-modal-head { padding:16px 20px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; justify-content:space-between; }
    .gr-modal-title { font-size:16px; font-weight:700; color:#111827; }
    .gr-modal-close { width:28px; height:28px; border-radius:999px; border:none; background:transparent; cursor:pointer; color:#6b7280; display:grid; place-items:center; }
    .gr-modal-close:hover { background:#f3f4f6; }
    .gr-modal-body { padding:18px 20px; }
    .gr-modal-field { display:flex; flex-direction:column; gap:3px; margin-bottom:14px; }
    .gr-modal-label { font-size:10px; color:#9ca3af; text-transform:uppercase; letter-spacing:.5px; font-weight:700; }
    .gr-modal-value { font-size:13px; color:#111827; font-weight:500; }
    .gr-modal-status { display:inline-block; padding:3px 10px; border-radius:999px; color:#fff; font-size:11px; font-weight:700; }

    /* Pagination */
    .gr-pagination { padding:12px 16px; border-top:1px solid #f3f4f6; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; background:#fff; }
    .gr-pagination nav { display:flex; gap:4px; }
    .gr-pagination a, .gr-pagination span { font-size:12px; padding:5px 10px; border-radius:6px; border:1px solid #e5e7eb; color:#374151; text-decoration:none; }
    .gr-pagination span[aria-current] { background:#2563eb; color:#fff; border-color:#2563eb; }

    /* Mobile */
    @media (max-width: 640px) {
        .gr-filterbar { padding:8px 10px; }
        .gr-pill { padding:7px 10px; font-size:11px; }
        .gr-label { font-size:15px; }
        .gr-week-row, .gr-day-cell { min-height:86px; }
        .gr-day-cell { padding:4px 5px 2px; }
        .gr-date-num { width:22px; height:22px; font-size:10px; }
        .gr-dow { padding:5px 4px; font-size:9px; }
        .gr-gantt-hd-booking, .gr-gantt-row-label { width:96px; padding:6px 8px; }
        .gr-gantt-row-customer { font-size:10px; }
        .gr-gantt-row-dates { font-size:8px; }
        .gr-prod-title { font-size:15px; }
    }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
     x-data="{ modal:null, loadingModal:false, loadRental(id) { this.loadingModal = true; this.modal = { loading:true }; fetch('{{ url('/schedule/rentals') }}/' + id).then(r => r.json()).then(d => { this.modal = d; this.loadingModal = false; }); }, dayModal:null, loadDay(date) { this.dayModal = { date: date, items: null }; fetch('{{ route('frontend.schedule.day-rentals') }}?date=' + date).then(r => r.json()).then(d => { this.dayModal = { date: date, items: d }; }); } }">
    <h1 class="text-2xl font-bold mb-6 text-gray-900">Rental Schedule</h1>

    <div class="gr-shell" style="min-height: 78vh">
        {{-- FilterBar --}}
        <div class="gr-filterbar">
            <div class="gr-pills" role="tablist">
                <a href="{{ $buildUrl(['filter' => 'order']) }}" class="gr-pill {{ $filter === 'order' ? 'on' : '' }}">By Order</a>
                <a href="{{ $buildUrl(['filter' => 'product']) }}" class="gr-pill {{ $filter === 'product' ? 'on' : '' }}">By Product</a>
            </div>

            @if($filter === 'order')
                <div class="gr-dd" x-data="{ ddOpen:false }" @click.outside="ddOpen=false">
                    <button type="button" class="gr-dd-btn" @click="ddOpen=!ddOpen">
                        {{ ucfirst($view_mode) }}
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="gr-dd-menu" :class="{ open: ddOpen }">
                        @foreach(['month','week','day'] as $v)
                            <a href="{{ $buildUrl(['view_mode' => $v]) }}" class="gr-dd-item {{ $view_mode === $v ? 'on' : '' }}">
                                @if($view_mode === $v)
                                    <span style="width:6px;height:6px;border-radius:999px;background:#2563eb;"></span>
                                @endif
                                {{ ucfirst($v) }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div style="flex:1"></div>
        </div>

        @if($filter === 'order' && $view_mode === 'month')
            @include('frontend.schedule.partials.month', ['buildUrl' => $buildUrl, 'navAnchor' => $navAnchor])
        @elseif($filter === 'order' && $view_mode === 'week')
            @include('frontend.schedule.partials.week', ['buildUrl' => $buildUrl, 'navAnchor' => $navAnchor])
        @elseif($filter === 'order' && $view_mode === 'day')
            @include('frontend.schedule.partials.day', ['buildUrl' => $buildUrl, 'navAnchor' => $navAnchor])
        @else
            @include('frontend.schedule.partials.product', ['buildUrl' => $buildUrl, 'navAnchor' => $navAnchor])
        @endif
    </div>

    {{-- Rental Details Modal --}}
    <template x-if="modal">
        <div class="gr-modal-backdrop" @click.self="modal=null" @keydown.escape.window="modal=null">
            <div class="gr-modal">
                <div class="gr-modal-head">
                    <div class="gr-modal-title">Rental Details</div>
                    <button class="gr-modal-close" @click="modal=null">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="gr-modal-body">
                    <template x-if="modal.loading">
                        <div style="padding:20px; text-align:center; color:#9ca3af; font-size:13px;">Loading…</div>
                    </template>
                    <template x-if="!modal.loading">
                        <div>
                            <div class="gr-modal-field">
                                <div class="gr-modal-label">Customer</div>
                                <div class="gr-modal-value" x-text="modal.customer"></div>
                            </div>
                            <div class="gr-modal-field">
                                <div class="gr-modal-label">Status</div>
                                <div><span class="gr-modal-status" :style="'background:' + modal.status_color" x-text="modal.status"></span></div>
                            </div>
                            <div class="gr-modal-field">
                                <div class="gr-modal-label">Period</div>
                                <div class="gr-modal-value"><span x-text="modal.start"></span> — <span x-text="modal.end"></span></div>
                            </div>
                            <template x-if="modal.items">
                                <div class="gr-modal-field">
                                    <div class="gr-modal-label">Items</div>
                                    <div class="gr-modal-value" x-text="modal.items"></div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    {{-- Day Rentals Modal (month "+N more") --}}
    <template x-if="dayModal">
        <div class="gr-modal-backdrop" @click.self="dayModal=null" @keydown.escape.window="dayModal=null">
            <div class="gr-modal">
                <div class="gr-modal-head">
                    <div class="gr-modal-title">Bookings</div>
                    <button class="gr-modal-close" @click="dayModal=null">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="gr-modal-body">
                    <template x-if="!dayModal.items">
                        <div style="padding:20px; text-align:center; color:#9ca3af; font-size:13px;">Loading…</div>
                    </template>
                    <template x-if="dayModal.items && dayModal.items.length === 0">
                        <div style="padding:20px; text-align:center; color:#9ca3af; font-size:13px;">No bookings.</div>
                    </template>
                    <template x-if="dayModal.items && dayModal.items.length > 0">
                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <template x-for="r in dayModal.items" :key="r.id">
                                <button type="button" @click="loadRental(r.id); dayModal=null;" style="text-align:left; border:1px solid #e5e7eb; background:#fff; border-radius:8px; padding:10px 12px; cursor:pointer; display:flex; align-items:center; gap:10px; font-family:inherit;">
                                    <span style="width:10px; height:10px; border-radius:999px; flex-shrink:0;" :style="'background:' + r.status_color"></span>
                                    <div style="flex:1; min-width:0;">
                                        <div style="font-size:13px; font-weight:700; color:#111827;" x-text="r.customer"></div>
                                        <div style="font-size:11px; color:#6b7280; margin-top:2px;"><span x-text="r.start"></span> — <span x-text="r.end"></span></div>
                                    </div>
                                    <span style="font-size:10px; color:#6b7280;" x-text="r.status"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
