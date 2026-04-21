@php
    $dayHeaders = $this->getProductDayHeaders();
    $monthGroups = $this->getProductMonthGroups($dayHeaders);
    $products = $this->getProductsWithUnitsAndRentals();

    $DAY_W = 58;
    $NAME_W = 190;
    $ROW_H = 44;
    $daysCount = count($dayHeaders);
    $gridStart = count($dayHeaders) ? $dayHeaders[0]['date'] : now();
@endphp

{{-- Toolbar --}}
<div class="gr-prod-toolbar">
    <div class="gr-prod-title">Global Product Schedule</div>
    <div style="flex:1"></div>
    <div class="gr-prod-search">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <input wire:model.live.debounce.500ms="search" type="search" placeholder="Search products or units..." />
    </div>
    <div style="display:flex; gap:2px;">
        <button class="gr-nav" wire:click="navigate(-1)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <button class="gr-today" wire:click="goToday">Today</button>
        <button class="gr-nav" wire:click="navigate(1)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>
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

<div class="gr-prod-scroll" wire:loading.class="opacity-50" wire:target="search, navigate, goToday, perPage">
    <div style="min-width: {{ $NAME_W + $DAY_W * $daysCount }}px;">

        {{-- Sticky header: month row + day row --}}
        <div class="gr-prod-stickyhead">
            {{-- Month label row --}}
            <div class="gr-prod-monthrow">
                <div class="gr-prod-corner" style="width: {{ $NAME_W }}px;"></div>
                @foreach($monthGroups as $mg)
                    <div class="gr-prod-month" style="width: {{ $DAY_W * $mg['count'] }}px;">
                        {{ $mg['label'] }}
                    </div>
                @endforeach
            </div>

            {{-- Day row --}}
            <div class="gr-prod-dayrow">
                <div class="gr-prod-labelhead" style="width: {{ $NAME_W }}px;">Product / Unit</div>
                @foreach($dayHeaders as $h)
                    @php
                        $cls = $h['is_today'] ? 'today' : ($h['is_weekend'] ? 'weekend' : '');
                    @endphp
                    <div class="gr-prod-daycell-head {{ $cls }}" style="width: {{ $DAY_W }}px;">
                        <div class="gr-prod-daycell-dow">{{ strtoupper($h['date']->format('D')) }}</div>
                        <div class="gr-prod-daycell-num {{ $h['is_today'] ? 'today' : ($h['is_weekend'] ? 'weekend' : '') }}">
                            {{ str_pad((string) $h['date']->day, 2, '0', STR_PAD_LEFT) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Body --}}
        @forelse($products as $group)
            {{-- Product name row --}}
            <div class="gr-prod-productrow" wire:key="product-{{ $group['product']->id }}">
                <div class="gr-prod-productname" style="width: {{ $NAME_W }}px;">
                    {{ $group['product']->name }}
                </div>
                @foreach($dayHeaders as $h)
                    @php
                        $cls = $h['is_today'] ? 'today' : ($h['is_weekend'] ? 'weekend' : '');
                    @endphp
                    <div class="gr-prod-productcell {{ $cls }}" style="width: {{ $DAY_W }}px;"></div>
                @endforeach
            </div>

            {{-- Unit rows --}}
            @foreach($group['units'] as $data)
                <div class="gr-prod-unitrow" wire:key="unit-{{ $data['unit']->id }}">
                    <div class="gr-prod-unitlabel" style="width: {{ $NAME_W }}px;">
                        <span class="gr-prod-sku">{{ $data['unit']->serial_number }}</span>
                    </div>
                    <div class="gr-prod-unitarea">
                        @foreach($dayHeaders as $h)
                            @php
                                $cls = $h['is_today'] ? 'today' : ($h['is_weekend'] ? 'weekend' : '');
                            @endphp
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
                                <div class="gr-prod-booking"
                                    style="height: {{ $ROW_H - 12 }}px; left: {{ $left }}px; width: {{ $width }}px; background: {{ $color }};"
                                    wire:click="mountAction('viewRentalDetails', { rentalId: {{ $rental['id'] }} })"
                                    title="{{ $rental['code'] }} — {{ $rental['customer'] }}"
                                >
                                    {{ $rental['customer'] }}
                                </div>
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

{{-- Pagination --}}
<div style="padding: 12px 16px; border-top: 1px solid #f3f4f6; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; background:#fff;" class="dark:!bg-gray-900 dark:!border-white/10">
    <div style="display:flex; align-items:center; gap:8px;">
        <span style="font-size:12px; color:#6b7280;">Per page</span>
        <select wire:model.live="perPage" style="font-size:12px; padding:4px 8px; border:1px solid #e5e7eb; border-radius:6px; background:#fff;">
            <option value="15">15</option>
            <option value="35">35</option>
            <option value="55">55</option>
            <option value="75">75</option>
            <option value="95">95</option>
        </select>
    </div>
    <div>
        {{ $products->links() }}
    </div>
</div>
