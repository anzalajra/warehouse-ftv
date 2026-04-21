@php
    /** @var \App\Filament\Pages\Home $this */
    $stats = $this->getStats();
    $menu = $this->getMenuItems();
    $todays = $this->getTodaysSchedule();
    $recent = $this->getRecentBookings();
    $greeting = $this->getGreeting();

    $statusBadge = [
        'quotation'      => ['bg' => '#fff7ed', 'fg' => '#c2410c', 'label' => 'Quotation'],
        'confirmed'      => ['bg' => '#eff6ff', 'fg' => '#1d4ed8', 'label' => 'Confirmed'],
        'active'         => ['bg' => '#f0fdf4', 'fg' => '#15803d', 'label' => 'Active'],
        'completed'      => ['bg' => '#faf5ff', 'fg' => '#7e22ce', 'label' => 'Done'],
        'cancelled'      => ['bg' => '#f9fafb', 'fg' => '#374151', 'label' => 'Cancel'],
        'late_pickup'    => ['bg' => '#fef2f2', 'fg' => '#b91c1c', 'label' => 'Late'],
        'late_return'    => ['bg' => '#fef2f2', 'fg' => '#b91c1c', 'label' => 'Late'],
        'partial_return' => ['bg' => '#fefce8', 'fg' => '#854d0e', 'label' => 'Partial'],
    ];

    $statusDot = [
        'quotation' => '#f97316', 'confirmed' => '#3b82f6', 'active' => '#22c55e',
        'completed' => '#a855f7', 'cancelled' => '#6b7280', 'late_pickup' => '#ef4444',
        'late_return' => '#ef4444', 'partial_return' => '#eab308',
    ];

    $statCards = [
        ['label' => 'Active',     'value' => $stats['active'],     'sub' => 'rentals',    'color' => '#22c55e', 'icon' => 'bookings'],
        ['label' => 'Quotations', 'value' => $stats['quotations'], 'sub' => 'pending',    'color' => '#a855f7', 'icon' => 'invoice'],
        ['label' => 'Pickups',    'value' => $stats['pickups'],    'sub' => 'today',      'color' => '#f97316', 'icon' => 'truck'],
        ['label' => 'Returns',    'value' => $stats['returns'],    'sub' => 'today',      'color' => '#3b82f6', 'icon' => 'box'],
        ['label' => 'Overdue',    'value' => $stats['overdue'],    'sub' => 'bookings',   'color' => '#ef4444', 'icon' => 'bell'],
        ['label' => 'Revenue',    'value' => 'Rp ' . ($stats['revenue'] >= 1000000 ? number_format($stats['revenue'] / 1000000, 1, ',', '.') . 'jt' : number_format($stats['revenue'], 0, ',', '.')), 'sub' => 'this month', 'color' => '#0284c7', 'icon' => 'invoice'],
    ];

    $svgIcons = [
        'bookings' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 13h6M9 17h4"/>',
        'invoice'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/>',
        'truck'    => '<rect x="1" y="6" width="14" height="11" rx="1"/><path d="M15 9h4l3 3v5h-7"/><circle cx="6" cy="19" r="2"/><circle cx="18" cy="19" r="2"/>',
        'box'      => '<path d="M21 8l-9-5-9 5v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5M12 13v10"/>',
        'bell'     => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'document' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
    ];
@endphp

<x-filament-panels::page>
    <style>
        .hm-wrap { display:flex; flex-direction:column; gap:18px; }

        .hm-greet { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:18px 22px; }
        .dark .hm-greet { background:#111827; border-color:rgba(255,255,255,.08); }
        .hm-greet-date { font-size:12px; color:#6b7280; font-weight:600; }
        .hm-greet-title { font-size:22px; font-weight:800; color:#111827; letter-spacing:-.4px; margin-top:2px; }
        .dark .hm-greet-title { color:#f9fafb; }
        .hm-greet-sub { font-size:13px; color:#6b7280; margin-top:4px; }

        .hm-section-title { font-size:13px; font-weight:800; color:#374151; letter-spacing:-.1px; margin:0 0 10px; }
        .dark .hm-section-title { color:#d1d5db; }

        /* Stats grid */
        .hm-stats { display:grid; grid-template-columns:repeat(6,1fr); gap:10px; }
        @media (max-width: 1024px) { .hm-stats { grid-template-columns:repeat(3,1fr); } }
        @media (max-width: 640px)  { .hm-section.stats { display:none; } }
        .hm-stat-card { background:#fff; border-radius:12px; border:1px solid #f3f4f6; padding:14px 16px; box-shadow:0 1px 2px rgba(0,0,0,.03); }
        .dark .hm-stat-card { background:#111827; border-color:rgba(255,255,255,.06); }
        .hm-stat-icon { width:30px; height:30px; border-radius:8px; display:grid; place-items:center; margin-bottom:8px; }
        .hm-stat-value { font-size:22px; font-weight:800; color:#111827; letter-spacing:-.5px; line-height:1; }
        .dark .hm-stat-value { color:#f9fafb; }
        .hm-stat-value.text { font-size:15px; }
        .hm-stat-label { font-size:11px; font-weight:600; color:#6b7280; margin-top:4px; }
        .hm-stat-sub { display:block; color:#9ca3af; font-weight:500; }

        /* Menu grid */
        .hm-menu { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
        @media (max-width: 1024px) { .hm-menu { grid-template-columns:repeat(4,1fr); } }
        @media (max-width: 640px)  { .hm-menu { grid-template-columns:repeat(2,1fr); gap:12px; } }
        .hm-menu-item { background:#fff; border:1px solid #f3f4f6; border-radius:16px; padding:16px 14px 14px; display:flex; flex-direction:column; align-items:center; gap:8px; cursor:pointer; font-family:inherit; position:relative; box-shadow:0 1px 3px rgba(0,0,0,.04); text-align:center; text-decoration:none; color:inherit; transition:transform .15s; }
        .hm-menu-item:hover { transform:translateY(-1px); }
        .dark .hm-menu-item { background:#111827; border-color:rgba(255,255,255,.06); color:#f3f4f6; }
        .hm-menu-ico { width:40px; height:40px; flex-shrink:0; border-radius:12px; background:#f0f9ff; display:grid; place-items:center; color:#0284c7; }
        .hm-menu-ico.primary { background:#0284c7; color:#fff; }
        .hm-menu-label { font-size:11px; font-weight:700; color:#111827; line-height:1.3; }
        .dark .hm-menu-label { color:#f3f4f6; }
        .hm-menu-desc { display:none; font-size:11px; color:#6b7280; margin-top:2px; }
        .hm-menu-badge { position:absolute; top:8px; right:12px; min-width:20px; height:20px; padding:0 6px; border-radius:99px; background:#0284c7; color:#fff; font-size:10px; font-weight:700; display:grid; place-items:center; }

        @media (max-width: 640px) {
            .hm-menu-item { flex-direction:row; padding:18px 14px 16px; gap:14px; text-align:left; align-items:center; }
            .hm-menu-ico { width:46px; height:46px; border-radius:14px; }
            .hm-menu-label { font-size:14px; }
            .hm-menu-desc { display:block; }
            .hm-menu-badge { top:12px; }
        }

        /* Today's schedule */
        .hm-section.today { }
        @media (max-width: 640px) { .hm-section.today { display:none; } }
        .hm-schedule-card { background:#fff; border:1px solid #f3f4f6; border-radius:12px; overflow:hidden; }
        .dark .hm-schedule-card { background:#111827; border-color:rgba(255,255,255,.06); }
        .hm-schedule-row { display:flex; align-items:center; gap:12px; padding:11px 14px; border-top:1px solid #f3f4f6; cursor:pointer; text-decoration:none; color:inherit; }
        .hm-schedule-row:first-child { border-top:none; }
        .hm-schedule-row:hover { background:#f9fafb; }
        .dark .hm-schedule-row { border-color:rgba(255,255,255,.06); }
        .dark .hm-schedule-row:hover { background:rgba(255,255,255,.03); }
        .hm-schedule-time { font-size:12px; font-weight:700; color:#6b7280; width:50px; text-align:right; flex-shrink:0; }
        .hm-schedule-bar { width:3px; height:32px; border-radius:99px; flex-shrink:0; }
        .hm-schedule-body { flex:1; min-width:0; }
        .hm-schedule-customer { font-size:13px; font-weight:700; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .dark .hm-schedule-customer { color:#f3f4f6; }
        .hm-schedule-sub { font-size:11px; color:#6b7280; margin-top:1px; }
        .hm-badge { padding:3px 10px; border-radius:99px; font-size:10px; font-weight:700; white-space:nowrap; }

        .hm-section-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .hm-section-link { border:none; background:transparent; color:#0284c7; font-size:12px; font-weight:700; cursor:pointer; font-family:inherit; text-decoration:none; }

        /* Recent bookings — desktop only */
        .hm-section.recent { display:none; }
        @media (min-width: 1024px) { .hm-section.recent { display:block; } }
        .hm-table { background:#fff; border:1px solid #f3f4f6; border-radius:12px; overflow:hidden; }
        .dark .hm-table { background:#111827; border-color:rgba(255,255,255,.06); }
        .hm-table-head, .hm-table-row { display:grid; grid-template-columns:1fr 1.4fr 1.2fr 0.8fr 0.8fr; gap:10px; padding:11px 16px; align-items:center; }
        .hm-table-head { background:#f9fafb; padding:10px 16px; }
        .dark .hm-table-head { background:rgba(255,255,255,.02); }
        .hm-table-row { border-top:1px solid #f3f4f6; text-decoration:none; color:inherit; }
        .dark .hm-table-row { border-color:rgba(255,255,255,.06); }
        .hm-table-row:hover { background:#f9fafb; }
        .hm-th { font-size:10px; font-weight:800; color:#6b7280; text-transform:uppercase; letter-spacing:.3px; }
        .hm-td-id { font-size:11px; font-family:ui-monospace,Menlo,Monaco,monospace; color:#0369a1; font-weight:700; }
        .hm-td-customer { font-size:12px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .dark .hm-td-customer { color:#f3f4f6; }
        .hm-td-meta { font-size:11px; color:#4b5563; }
        .hm-td-total { font-size:11px; font-weight:700; color:#1f2937; }
        .dark .hm-td-total { color:#f3f4f6; }
    </style>

    <div class="hm-wrap">

        {{-- Greeting --}}
        <div class="hm-greet">
            <div class="hm-greet-date">{{ now()->translatedFormat('l, j F Y') }}</div>
            <div class="hm-greet-title">{{ $greeting }}</div>
            <div class="hm-greet-sub">
                Ada {{ $stats['pickups'] }} pickup dan {{ $stats['returns'] }} return hari ini
            </div>
        </div>

        {{-- Overview stats (hidden on mobile) --}}
        <div class="hm-section stats">
            <div class="hm-section-title">Overview</div>
            <div class="hm-stats">
                @foreach($statCards as $s)
                    @php $isText = !is_numeric($s['value']); @endphp
                    <div class="hm-stat-card">
                        <div class="hm-stat-icon" style="background: {{ $s['color'] }}18;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="{{ $s['color'] }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                {!! $svgIcons[$s['icon']] ?? '' !!}
                            </svg>
                        </div>
                        <div class="hm-stat-value {{ $isText ? 'text' : '' }}">{{ $s['value'] }}</div>
                        <div class="hm-stat-label">
                            {{ $s['label'] }}
                            <span class="hm-stat-sub">{{ $s['sub'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Menu grid --}}
        <div class="hm-section">
            <div class="hm-section-title">Menu</div>
            <div class="hm-menu">
                @foreach($menu as $m)
                    <a href="{{ $m['url'] }}" class="hm-menu-item">
                        <div class="hm-menu-ico {{ $m['id'] === 'schedule' ? 'primary' : '' }}">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                {!! $svgIcons[$m['icon']] ?? '' !!}
                            </svg>
                        </div>
                        <div>
                            <div class="hm-menu-label">{{ $m['label'] }}</div>
                            <div class="hm-menu-desc">{{ $m['desc'] }}</div>
                        </div>
                        @if(!empty($m['badge']))
                            <span class="hm-menu-badge">{{ $m['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Today's Schedule (hidden on mobile) --}}
        @if(count($todays) > 0)
            <div class="hm-section today">
                <div class="hm-section-head">
                    <div class="hm-section-title" style="margin:0;">Today's Schedule</div>
                    <a href="{{ url('/admin/schedule') }}" class="hm-section-link">View all →</a>
                </div>
                <div class="hm-schedule-card">
                    @foreach($todays as $e)
                        @php
                            $dot = $statusDot[$e['status']] ?? '#6b7280';
                            $b = $statusBadge[$e['status']] ?? ['bg' => '#f3f4f6', 'fg' => '#374151', 'label' => ucfirst($e['status'])];
                        @endphp
                        <a href="{{ url('/admin/rentals/' . $e['id'] . '/view') }}" class="hm-schedule-row">
                            <div class="hm-schedule-time">{{ $e['kind'] === 'Pickup' ? $e['start_time'] : $e['end_time'] }}</div>
                            <div class="hm-schedule-bar" style="background: {{ $dot }};"></div>
                            <div class="hm-schedule-body">
                                <div class="hm-schedule-customer">{{ $e['customer'] }}</div>
                                <div class="hm-schedule-sub">{{ $e['kind'] }} · {{ $e['code'] }}</div>
                            </div>
                            <span class="hm-badge" style="background: {{ $b['bg'] }}; color: {{ $b['fg'] }};">{{ $b['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recent Bookings (desktop only) --}}
        @if(count($recent) > 0)
            <div class="hm-section recent">
                <div class="hm-section-title">Recent Bookings</div>
                <div class="hm-table">
                    <div class="hm-table-head">
                        @foreach(['Booking ID', 'Customer', 'Status', 'Start', 'Total'] as $h)
                            <div class="hm-th">{{ $h }}</div>
                        @endforeach
                    </div>
                    @foreach($recent as $b)
                        @php $bd = $statusBadge[$b['status']] ?? ['bg' => '#f3f4f6', 'fg' => '#374151', 'label' => ucfirst($b['status'])]; @endphp
                        <a href="{{ url('/admin/rentals/' . $b['id'] . '/view') }}" class="hm-table-row">
                            <div class="hm-td-id">{{ $b['code'] }}</div>
                            <div class="hm-td-customer">{{ $b['customer'] }}</div>
                            <span class="hm-badge" style="background: {{ $bd['bg'] }}; color: {{ $bd['fg'] }}; width:fit-content; display:inline-flex; align-items:center; gap:5px;">
                                <span style="width:5px; height:5px; border-radius:99px; background: currentColor;"></span>
                                {{ $bd['label'] }}
                            </span>
                            <div class="hm-td-meta">{{ $b['start'] }}</div>
                            <div class="hm-td-total">{{ $b['total'] }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
