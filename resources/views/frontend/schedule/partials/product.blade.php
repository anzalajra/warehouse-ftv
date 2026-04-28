@php
    $DAY_W = 58;
    $NAME_W = 190;
    $ROW_H = 44;
    $daysCount = count($dayHeaders);
    $gridStart = $daysCount ? $dayHeaders[0]['date'] : now();
@endphp

<div class="gr-prod-toolbar">
    <div class="gr-prod-title">Product Schedule</div>
    <div style="flex:1"></div>
    <form method="GET" action="{{ route('frontend.schedule') }}" class="gr-prod-search">
        <input type="hidden" name="filter" value="product">
        <input type="hidden" name="anchor" value="{{ $anchor }}">
        <input type="hidden" name="perPage" value="{{ $perPage }}">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <input name="search" value="{{ $search }}" type="search" placeholder="Search products or units..." />
    </form>
    <div style="display:flex; gap:2px;">
        <a href="{{ $buildUrl(['anchor' => $navAnchor(-1)]) }}" class="gr-nav">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <a href="{{ $buildUrl(['anchor' => now()->toDateString()]) }}" class="gr-today">Today</a>
        <a href="{{ $buildUrl(['anchor' => $navAnchor(1)]) }}" class="gr-nav">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
    </div>
</div>

<div class="gr-legend">
    @foreach($legend as [$color, $label])
        <div class="gr-legend-item">
            <span class="gr-legend-dot" style="background: {{ $color }}"></span>
            <span class="gr-legend-text">{{ $label }}</span>
        </div>
    @endforeach
</div>

<div class="gr-prod-scroll">
    <div style="min-width: {{ $NAME_W + $DAY_W * $daysCount }}px;">

        <div class="gr-prod-stickyhead">
            <div class="gr-prod-monthrow">
                <div class="gr-prod-corner" style="width: {{ $NAME_W }}px;"></div>
                @foreach($monthGroups as $mg)
                    <div class="gr-prod-month" style="width: {{ $DAY_W * $mg['count'] }}px;">
                        {{ $mg['label'] }}
                    </div>
                @endforeach
            </div>

            <div class="gr-prod-dayrow">
                <div class="gr-prod-labelhead" style="width: {{ $NAME_W }}px;">Product / Unit</div>
                @foreach($dayHeaders as $h)
                    @php $cls = $h['is_today'] ? 'today' : ($h['is_weekend'] ? 'weekend' : ''); @endphp
                    <div class="gr-prod-daycell-head {{ $cls }}" style="width: {{ $DAY_W }}px;">
                        <div class="gr-prod-daycell-dow">{{ strtoupper($h['date']->format('D')) }}</div>
                        <div class="gr-prod-daycell-num {{ $h['is_today'] ? 'today' : ($h['is_weekend'] ? 'weekend' : '') }}">
                            {{ str_pad((string) $h['date']->day, 2, '0', STR_PAD_LEFT) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @forelse($products as $group)
            <div class="gr-prod-productrow">
                <div class="gr-prod-productname" style="width: {{ $NAME_W }}px;">
                    {{ $group['product']->name }}
                </div>
                @foreach($dayHeaders as $h)
                    @php $cls = $h['is_today'] ? 'today' : ($h['is_weekend'] ? 'weekend' : ''); @endphp
                    <div class="gr-prod-productcell {{ $cls }}" style="width: {{ $DAY_W }}px;"></div>
                @endforeach
            </div>

            @foreach($group['units'] as $data)
                <div class="gr-prod-unitrow">
                    <div class="gr-prod-unitlabel" style="width: {{ $NAME_W }}px;">
                        <span class="gr-prod-sku">{{ $data['unit']->serial_number }}</span>
                    </div>
                    <div class="gr-prod-unitarea">
                        @foreach($dayHeaders as $h)
                            @php $cls = $h['is_today'] ? 'today' : ($h['is_weekend'] ? 'weekend' : ''); @endphp
                            <div class="gr-prod-unitcell {{ $cls }}" style="width: {{ $DAY_W }}px;"></div>
                        @endforeach

                        @foreach($data['rentals'] as $rental)
                            @php
                                $rStart = $rental['start']->copy()->startOfDay();
                                $rEnd = $rental['end']->copy()->startOfDay();
                                $startIdx = (int) $gridStart->diffInDays($rStart, false);
                                $endIdx = (int) $gridStart->diffInDays($rEnd, false);
                                if ($startIdx < 0) $startIdx = 0;
                                if ($endIdx >= $daysCount) $endIdx = $daysCount - 1;
                                $skipRental = $endIdx < $startIdx;
                                $status = strtolower($rental['status'] ?? '');
                                $color = $statuses[$status][0] ?? '#6b7280';
                                $left = $startIdx * $DAY_W + 2;
                                $width = ($endIdx - $startIdx + 1) * $DAY_W - 4;
                            @endphp
                            @if(! $skipRental)
                                <button type="button" class="gr-prod-booking"
                                    style="height: {{ $ROW_H - 12 }}px; left: {{ $left }}px; width: {{ $width }}px; background: {{ $color }};"
                                    @click="loadRental({{ $rental['id'] }})"
                                    title="{{ $rental['customer'] }}"
                                >
                                    {{ $rental['customer'] }}
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        @empty
            <div style="padding:40px 20px; text-align:center; color:#9ca3af; font-size:13px;">
                No products found.
            </div>
        @endforelse

        <div style="height:24px"></div>
    </div>
</div>

<div class="gr-pagination">
    <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:12px; color:#6b7280;">Per page</span>
        <select onchange="window.location.href=this.value" style="font-size:12px; padding:4px 8px; border:1px solid #e5e7eb; border-radius:6px; background:#fff;">
            @foreach([15, 35, 55, 75, 95] as $pp)
                <option value="{{ $buildUrl(['perPage' => $pp]) }}" {{ $perPage === $pp ? 'selected' : '' }}>{{ $pp }}</option>
            @endforeach
        </select>
    </div>
    <div>
        {{ $products->links() }}
    </div>
</div>
