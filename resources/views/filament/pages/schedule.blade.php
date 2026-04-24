@php
    /** @var \App\Filament\Pages\Schedule $this */
    $range = $this->getVisibleRange();
    $statuses = [
        'quotation'      => ['#f97316', 'Quotation'],
        'confirmed'      => ['#3b82f6', 'Confirmed'],
        'active'         => ['#22c55e', 'Active'],
        'completed'      => ['#a855f7', 'Done'],
        'cancelled'      => ['#6b7280', 'Cancel'],
        'late_pickup'    => ['#ef4444', 'Late'],
        'late_return'    => ['#ef4444', 'Late'],
        'partial_return' => ['#eab308', 'Partial'],
    ];
    $legend = [
        ['#f97316', 'Quotation'],
        ['#3b82f6', 'Confirmed'],
        ['#22c55e', 'Active'],
        ['#a855f7', 'Done'],
        ['#6b7280', 'Cancel'],
        ['#ef4444', 'Late'],
        ['#eab308', 'Partial'],
    ];
@endphp

<x-filament-panels::page>
    <style>
        .gr-shell { background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; }
        .dark .gr-shell { background:#111827; border-color:rgba(255,255,255,.08); }

        /* FilterBar */
        .gr-filterbar { display:flex; align-items:center; gap:8px; padding:10px 14px; border-bottom:1px solid #f3f4f6; background:#fff; flex-wrap:wrap; }
        .dark .gr-filterbar { background:#111827; border-color:rgba(255,255,255,.06); }
        .gr-pills { display:inline-flex; background:#f3f4f6; border-radius:10px; padding:3px; }
        .dark .gr-pills { background:rgba(255,255,255,.06); }
        .gr-pill { padding:7px 16px; border-radius:7px; border:none; cursor:pointer; background:transparent; color:#6b7280; font-size:12px; font-weight:700; font-family:inherit; }
        .gr-pill.on { background:#fff; color:#111827; box-shadow:0 1px 3px rgba(0,0,0,.08); }
        .dark .gr-pill.on { background:#1f2937; color:#fff; }
        .gr-dd { position:relative; }
        .gr-dd-btn { display:inline-flex; align-items:center; gap:5px; padding:8px 12px; border-radius:8px; background:#fff; border:1px solid #e5e7eb; font-size:12px; font-weight:700; color:#1f2937; cursor:pointer; font-family:inherit; }
        .dark .gr-dd-btn { background:#1f2937; border-color:rgba(255,255,255,.08); color:#f3f4f6; }
        .gr-dd-menu { position:absolute; top:calc(100% + 4px); left:0; z-index:30; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,.12); overflow:hidden; min-width:130px; display:none; }
        .dark .gr-dd-menu { background:#1f2937; border-color:rgba(255,255,255,.08); }
        .gr-dd-menu.open { display:block; }
        .gr-dd-item { width:100%; text-align:left; padding:10px 14px; border:none; background:#fff; color:#111827; font-size:13px; font-weight:500; cursor:pointer; font-family:inherit; display:flex; align-items:center; gap:8px; border-bottom:1px solid #f3f4f6; }
        .gr-dd-item:last-child { border-bottom:none; }
        .dark .gr-dd-item { background:#1f2937; color:#f3f4f6; border-color:rgba(255,255,255,.06); }
        .gr-dd-item.on { background:rgb(var(--primary-50)); color:rgb(var(--primary-700)); font-weight:700; }
        .dark .gr-dd-item.on { background:rgb(var(--primary-500) / .15); color:rgb(var(--primary-300)); }
        .gr-new { display:inline-flex; align-items:center; gap:5px; padding:8px 16px; border-radius:8px; background:rgb(var(--primary-600)); color:#fff; border:none; font-size:12px; font-weight:700; cursor:pointer; font-family:inherit; text-decoration:none; }
        .gr-new:hover { background:rgb(var(--primary-700)); }

        /* GC toolbar */
        .gr-toolbar { display:flex; align-items:center; gap:10px; padding:10px 14px; border-bottom:1px solid #f3f4f6; background:#fff; }
        .dark .gr-toolbar { background:#111827; border-color:rgba(255,255,255,.06); }
        .gr-today { height:30px; padding:0 14px; border:1px solid #d1d5db; border-radius:999px; background:#fff; color:#374151; font-size:12px; font-weight:600; cursor:pointer; font-family:inherit; }
        .dark .gr-today { background:#1f2937; border-color:rgba(255,255,255,.1); color:#f3f4f6; }
        .gr-nav { width:30px; height:30px; border-radius:999px; border:none; background:transparent; display:grid; place-items:center; cursor:pointer; color:#4b5563; }
        .gr-nav:hover { background:#f3f4f6; }
        .dark .gr-nav { color:#d1d5db; }
        .dark .gr-nav:hover { background:rgba(255,255,255,.06); }
        .gr-label { font-size:17px; font-weight:700; color:#111827; letter-spacing:-.3px; }
        .dark .gr-label { color:#f9fafb; }

        /* Legend */
        .gr-legend { display:flex; align-items:center; flex-wrap:wrap; gap:6px 14px; padding:9px 16px; border-bottom:1px solid #f3f4f6; background:#fff; }
        .dark .gr-legend { background:#111827; border-color:rgba(255,255,255,.06); }
        .gr-legend-item { display:flex; align-items:center; gap:5px; }
        .gr-legend-dot { width:10px; height:10px; border-radius:999px; flex-shrink:0; }
        .gr-legend-text { font-size:11px; font-weight:600; color:#374151; }
        .dark .gr-legend-text { color:#d1d5db; }

        /* Event bar */
        .gr-event { border-radius:5px; padding:3px 8px; display:flex; flex-direction:column; justify-content:center; overflow:hidden; cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,.12); color:#fff; }
        .gr-event:hover { filter: brightness(1.05); }
        .gr-event-title { font-size:10px; font-weight:700; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.2; }
        .gr-event-sub { font-size:9px; color:rgba(255,255,255,.8); margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        /* Month grid */
        .gr-month-grid { flex:1; display:flex; flex-direction:column; overflow:auto; background:#fff; }
        .dark .gr-month-grid { background:#111827; }
        .gr-dow-row { display:grid; grid-template-columns:repeat(7,1fr); position:sticky; top:0; z-index:3; background:#fff; border-bottom:1px solid #f3f4f6; }
        .dark .gr-dow-row { background:#111827; border-color:rgba(255,255,255,.06); }
        .gr-dow { padding:7px 8px; font-size:10px; font-weight:700; color:#6b7280; text-align:center; letter-spacing:.3px; text-transform:uppercase; }
        .gr-week-row { position:relative; display:grid; grid-template-columns:repeat(7,1fr); border-bottom:1px solid #f3f4f6; min-height:108px; }
        .dark .gr-week-row { border-color:rgba(255,255,255,.06); }
        .gr-day-cell { border-right:1px solid #f3f4f6; padding:5px 7px 2px; min-height:108px; }
        .dark .gr-day-cell { border-color:rgba(255,255,255,.06); }
        .gr-day-cell:last-child { border-right:none; }
        .gr-day-cell.other { background:#fafafa; }
        .dark .gr-day-cell.other { background:rgba(255,255,255,.02); }
        .gr-day-cell.today { background:rgb(var(--primary-50)); }
        .dark .gr-day-cell.today { background:rgb(var(--primary-500) / .12); }
        .gr-date { display:flex; justify-content:flex-start; padding-left:2px; }
        .gr-date-num { min-width:22px; height:22px; padding:0 4px; display:grid; place-items:center; font-size:11px; font-weight:500; color:#374151; }
        .dark .gr-date-num { color:#d1d5db; }
        .gr-date-num.today { color:rgb(var(--primary-700)); font-weight:800; }
        .dark .gr-date-num.today { color:rgb(var(--primary-300)); }
        .gr-date-num.other { color:#d1d5db; }
        .gr-more-btn { position:absolute; border:none; background:transparent; font-size:10px; color:rgb(var(--primary-600)); font-weight:700; cursor:pointer; padding:1px 3px; border-radius:4px; font-family:inherit; display:flex; align-items:center; gap:2px; text-decoration:none; }
        .gr-more-btn:hover { background:rgb(var(--primary-50)); }
        .dark .gr-more-btn { color:rgb(var(--primary-300)); }
        .dark .gr-more-btn:hover { background:rgb(var(--primary-500) / .15); }

        /* Week gantt */
        .gr-gantt-head { display:flex; position:sticky; top:0; z-index:3; background:#fff; border-bottom:1px solid #e5e7eb; }
        .dark .gr-gantt-head { background:#111827; border-color:rgba(255,255,255,.08); }
        .gr-gantt-hd-booking { width:120px; flex-shrink:0; border-right:1px solid #f3f4f6; padding:8px 12px; font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; align-self:center; }
        .dark .gr-gantt-hd-booking { border-color:rgba(255,255,255,.06); }
        .gr-gantt-days { display:flex; flex:1; }
        .gr-gantt-day { flex:1; padding:6px 0 4px; text-align:center; border-right:1px solid #f3f4f6; }
        .dark .gr-gantt-day { border-color:rgba(255,255,255,.06); }
        .gr-gantt-day:last-child { border-right:none; }
        .gr-gantt-day.today { background:rgb(var(--primary-50)); }
        .dark .gr-gantt-day.today { background:rgb(var(--primary-500) / .12); }
        .gr-gantt-dow { font-size:9px; color:#9ca3af; font-weight:700; letter-spacing:.3px; }
        .gr-gantt-date { font-size:13px; font-weight:600; color:#374151; margin-top:1px; }
        .dark .gr-gantt-date { color:#f3f4f6; }
        .gr-gantt-date.today { color:rgb(var(--primary-600)); font-weight:800; }
        .dark .gr-gantt-date.today { color:rgb(var(--primary-300)); }
        .gr-gantt-body { flex:1; overflow-y:auto; }
        .gr-gantt-row { display:flex; min-height:54px; border-bottom:1px solid #f3f4f6; background:#fff; }
        .dark .gr-gantt-row { background:#111827; border-color:rgba(255,255,255,.06); }
        .gr-gantt-row-label { width:120px; flex-shrink:0; padding:8px 12px; border-right:1px solid #f3f4f6; display:flex; flex-direction:column; justify-content:center; }
        .dark .gr-gantt-row-label { border-color:rgba(255,255,255,.06); }
        .gr-gantt-row-customer { font-size:11px; font-weight:700; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .dark .gr-gantt-row-customer { color:#f3f4f6; }
        .gr-gantt-row-dates { font-size:9px; color:#6b7280; margin-top:1px; }
        .gr-gantt-area { flex:1; position:relative; display:flex; }
        .gr-gantt-area-cell { flex:1; border-right:1px solid #f3f4f6; }
        .dark .gr-gantt-area-cell { border-color:rgba(255,255,255,.06); }
        .gr-gantt-area-cell:last-child { border-right:none; }
        .gr-gantt-area-cell.today { background:rgb(var(--primary-500) / .06); }
        .dark .gr-gantt-area-cell.today { background:rgb(var(--primary-500) / .1); }

        /* Day timeline */
        .gr-day-weekstrip { display:grid; grid-template-columns:repeat(7,1fr); padding:6px 6px 8px; background:#fff; border-bottom:1px solid #f3f4f6; }
        .dark .gr-day-weekstrip { background:#111827; border-color:rgba(255,255,255,.06); }
        .gr-day-ws-btn { border:none; background:transparent; cursor:pointer; padding:4px 0; border-radius:8px; display:flex; flex-direction:column; align-items:center; gap:3px; font-family:inherit; color:inherit; text-decoration:none; }
        .gr-day-ws-dow { font-size:9px; color:#9ca3af; font-weight:600; letter-spacing:.3px; text-transform:uppercase; }
        .gr-day-ws-date { width:30px; height:30px; border-radius:999px; display:grid; place-items:center; font-size:13px; font-weight:600; color:#374151; }
        .dark .gr-day-ws-date { color:#f3f4f6; }
        .gr-day-ws-date.on { background:rgb(var(--primary-600)); color:#fff; font-weight:700; }
        .gr-day-ws-date.today { background:rgb(var(--primary-50)); color:rgb(var(--primary-700)); }
        .dark .gr-day-ws-date.today { background:rgb(var(--primary-500) / .15); color:rgb(var(--primary-300)); }
        .gr-day-heading { padding:8px 14px 6px; border-bottom:1px solid #f3f4f6; }
        .dark .gr-day-heading { border-color:rgba(255,255,255,.06); }
        .gr-day-heading-eyebrow { font-size:11px; color:rgb(var(--primary-600)); font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
        .dark .gr-day-heading-eyebrow { color:rgb(var(--primary-300)); }
        .gr-day-heading-title { font-size:15px; font-weight:700; color:#111827; margin-top:1px; }
        .dark .gr-day-heading-title { color:#f3f4f6; }
        .gr-day-allday { padding:6px 14px 6px 54px; border-bottom:1px solid #f3f4f6; display:flex; flex-direction:column; gap:3px; }
        .dark .gr-day-allday { border-color:rgba(255,255,255,.06); }
        .gr-day-timeline-wrap { flex:1; overflow-y:auto; }
        .gr-day-timeline { position:relative; }
        .gr-hour { position:absolute; left:0; right:0; border-top:1px solid #f3f4f6; }
        .dark .gr-hour { border-color:rgba(255,255,255,.06); }
        .gr-hour-label { position:absolute; left:0; top:-7px; width:46px; font-size:10px; color:#9ca3af; font-weight:500; text-align:right; padding-right:6px; }
        .gr-now-line { position:absolute; left:46px; right:0; z-index:3; pointer-events:none; }
        .gr-now-dot { position:absolute; left:-5px; top:-5px; width:10px; height:10px; border-radius:999px; background:#ef4444; }
        .gr-now-bar { height:2px; background:#ef4444; }

        /* By product */
        .gr-prod-toolbar { display:flex; align-items:center; gap:10px; padding:12px 18px; border-bottom:1px solid #f3f4f6; background:#fff; flex-wrap:wrap; }
        .dark .gr-prod-toolbar { background:#111827; border-color:rgba(255,255,255,.06); }
        .gr-prod-title { font-size:18px; font-weight:800; color:#111827; letter-spacing:-.3px; }
        .dark .gr-prod-title { color:#f9fafb; }
        .gr-prod-search { display:flex; align-items:center; gap:10px; height:36px; padding:0 12px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; min-width:200px; }
        .dark .gr-prod-search { background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.08); }
        .gr-prod-search input { flex:1; border:none; outline:none; font-size:13px; color:#374151; background:transparent; font-family:inherit; }
        .dark .gr-prod-search input { color:#f3f4f6; }
        .gr-prod-scroll { flex:1; overflow:auto; position:relative; background:#fff; }
        .dark .gr-prod-scroll { background:#111827; }
        .gr-prod-stickyhead { position:sticky; top:0; z-index:10; background:#fff; }
        .dark .gr-prod-stickyhead { background:#111827; }
        .gr-prod-monthrow { display:flex; border-bottom:1px solid #f3f4f6; }
        .dark .gr-prod-monthrow { border-color:rgba(255,255,255,.06); }
        .gr-prod-corner { flex-shrink:0; position:sticky; left:0; z-index:11; background:#fff; border-right:1px solid #e5e7eb; }
        .dark .gr-prod-corner { background:#111827; border-color:rgba(255,255,255,.08); }
        .gr-prod-month { flex-shrink:0; padding:6px 0 6px 10px; border-right:1px solid #f3f4f6; font-size:11px; font-weight:800; color:rgb(var(--primary-700)); letter-spacing:.3px; text-transform:uppercase; }
        .dark .gr-prod-month { color:rgb(var(--primary-300)); border-color:rgba(255,255,255,.06); }
        .gr-prod-dayrow { display:flex; border-bottom:1px solid #e5e7eb; }
        .dark .gr-prod-dayrow { border-color:rgba(255,255,255,.08); }
        .gr-prod-labelhead { flex-shrink:0; padding:8px 12px; font-size:10px; font-weight:800; color:#9ca3af; text-transform:uppercase; letter-spacing:.4px; position:sticky; left:0; z-index:11; background:#fff; border-right:1px solid #e5e7eb; display:flex; align-items:center; }
        .dark .gr-prod-labelhead { background:#111827; border-color:rgba(255,255,255,.08); }
        .gr-prod-daycell-head { flex-shrink:0; text-align:center; padding:5px 0 6px; border-right:1px solid #f3f4f6; }
        .dark .gr-prod-daycell-head { border-color:rgba(255,255,255,.06); }
        .gr-prod-daycell-head.weekend { background:#f9fafb; }
        .dark .gr-prod-daycell-head.weekend { background:rgba(255,255,255,.02); }
        .gr-prod-daycell-head.today { background:rgb(var(--primary-50)); }
        .dark .gr-prod-daycell-head.today { background:rgb(var(--primary-500) / .12); }
        .gr-prod-daycell-dow { font-size:9px; color:#9ca3af; font-weight:600; }
        .gr-prod-daycell-num { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:999px; margin-top:2px; font-size:11px; font-weight:500; color:#1f2937; }
        .dark .gr-prod-daycell-num { color:#f3f4f6; }
        .gr-prod-daycell-num.today { background:rgb(var(--primary-600)); color:#fff; font-weight:700; }
        .gr-prod-daycell-num.weekend { color:#6b7280; }
        .gr-prod-productrow { display:flex; border-bottom:1px solid #f3f4f6; background:#fafafa; }
        .dark .gr-prod-productrow { background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.06); }
        .gr-prod-productname { flex-shrink:0; padding:9px 12px; font-size:12px; font-weight:800; color:#ea580c; position:sticky; left:0; z-index:5; background:#fafafa; border-right:1px solid #e5e7eb; display:flex; align-items:center; letter-spacing:-.1px; }
        .dark .gr-prod-productname { background:rgba(255,255,255,.03); border-color:rgba(255,255,255,.08); color:#fb923c; }
        .gr-prod-productcell { flex-shrink:0; border-right:1px solid #f3f4f6; }
        .dark .gr-prod-productcell { border-color:rgba(255,255,255,.06); }
        .gr-prod-productcell.weekend { background:rgba(0,0,0,.015); }
        .gr-prod-productcell.today { background:rgb(var(--primary-500) / .06); }
        .gr-prod-unitrow { display:flex; min-height:44px; border-bottom:1px solid #f3f4f6; background:#fff; }
        .dark .gr-prod-unitrow { background:#111827; border-color:rgba(255,255,255,.06); }
        .gr-prod-unitlabel { flex-shrink:0; padding:8px 12px; border-right:1px solid #e5e7eb; position:sticky; left:0; z-index:4; background:#fff; display:flex; align-items:center; }
        .dark .gr-prod-unitlabel { background:#111827; border-color:rgba(255,255,255,.08); }
        .gr-prod-sku { font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace; font-size:10px; font-weight:700; color:#374151; background:#f3f4f6; padding:3px 8px; border-radius:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .dark .gr-prod-sku { background:rgba(255,255,255,.06); color:#e5e7eb; }
        .gr-prod-unitarea { display:flex; position:relative; flex:1; }
        .gr-prod-unitcell { flex-shrink:0; border-right:1px solid #f3f4f6; }
        .dark .gr-prod-unitcell { border-color:rgba(255,255,255,.06); }
        .gr-prod-unitcell.weekend { background:rgba(0,0,0,.015); }
        .gr-prod-unitcell.today { background:rgb(var(--primary-500) / .06); }
        .gr-prod-booking { position:absolute; top:6px; border-radius:5px; padding:0 8px; font-size:10px; font-weight:600; color:#fff; display:flex; align-items:center; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,.12); }

        /* Mobile */
        @media (max-width: 640px) {
            .gr-filterbar { padding:8px 10px; }
            .gr-pill { padding:7px 10px; font-size:11px; }
            .gr-new.mobile-hide { display:none; }
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

    {{-- FilterBar --}}
    <div
        x-data="{ ddOpen:false }"
        class="gr-shell"
        style="min-height: 78vh"
    >
        <div class="gr-filterbar">
            {{-- By Order / By Product pills --}}
            <div class="gr-pills" role="tablist">
                <button class="gr-pill {{ $filter === 'order' ? 'on' : '' }}" wire:click="setFilter('order')">By Order</button>
                <button class="gr-pill {{ $filter === 'product' ? 'on' : '' }}" wire:click="setFilter('product')">By Product</button>
            </div>

            {{-- View dropdown --}}
            @if($filter === 'order')
                <div class="gr-dd" @click.outside="ddOpen=false">
                    <button type="button" class="gr-dd-btn" @click="ddOpen=!ddOpen">
                        {{ ucfirst($view_mode) }}
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div class="gr-dd-menu" :class="{ open: ddOpen }">
                        @foreach(['month','week','day'] as $v)
                            <button type="button" class="gr-dd-item {{ $view_mode === $v ? 'on' : '' }}"
                                wire:click="setViewMode('{{ $v }}')"
                                @click="ddOpen=false">
                                @if($view_mode === $v)
                                    <span style="width:6px;height:6px;border-radius:999px;background:rgb(var(--primary-600));"></span>
                                @endif
                                {{ ucfirst($v) }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div style="flex:1"></div>

            {{-- New Booking (desktop/tablet) --}}
            <a href="{{ url('/admin/rentals/create') }}" class="gr-new mobile-hide">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                New Booking
            </a>
        </div>

        @if($filter === 'order')
            @if($view_mode === 'month')
                @include('filament.pages.schedule.month', ['statuses' => $statuses, 'legend' => $legend, 'range' => $range])
            @elseif($view_mode === 'week')
                @include('filament.pages.schedule.week', ['statuses' => $statuses, 'legend' => $legend, 'range' => $range])
            @else
                @include('filament.pages.schedule.day', ['statuses' => $statuses, 'legend' => $legend, 'range' => $range])
            @endif
        @else
            @include('filament.pages.schedule.product', ['statuses' => $statuses, 'legend' => $legend, 'range' => $range])
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
