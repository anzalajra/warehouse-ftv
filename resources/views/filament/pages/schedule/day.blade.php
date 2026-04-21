@php
    $data = $this->getDayData();
    $events = $data['events'];
    $allDay = $data['allDay'];
    $weekDays = $data['weekDays'];
    $anchor = $data['anchor'];
    $today = now()->startOfDay();

    $START_HOUR = 7;
    $END_HOUR = 21;
    $HOUR_H = 54;

    // Lane packing for overlap
    $placed = [];
    foreach ($events as $idx => $e) {
        $e['col'] = 0;
        $usedCols = [];
        foreach ($placed as $p) {
            if (!($p['end_h'] <= $e['start_h'] || $p['start_h'] >= $e['end_h'])) {
                $usedCols[$p['col']] = true;
            }
        }
        $c = 0;
        while (isset($usedCols[$c])) $c++;
        $e['col'] = $c;
        $placed[] = $e;
    }
    $maxCols = 1;
    foreach ($placed as $p) {
        $grp = array_filter($placed, fn($o) => !($o['end_h'] <= $p['start_h'] || $o['start_h'] >= $p['end_h']));
        $maxLocal = 0;
        foreach ($grp as $g) $maxLocal = max($maxLocal, $g['col'] + 1);
        $maxCols = max($maxCols, $maxLocal);
    }

    $hours = range($START_HOUR, $END_HOUR);
    $nowHour = now()->hour + now()->minute / 60;
    $showNow = $anchor->isSameDay($today) && $nowHour >= $START_HOUR && $nowHour <= $END_HOUR;
@endphp

{{-- Google Calendar style toolbar --}}
<div class="gr-toolbar">
    <button class="gr-today" wire:click="goToday">Today</button>
    <div style="display:flex; gap:2px;">
        <button class="gr-nav" wire:click="navigate(-1)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </button>
        <button class="gr-nav" wire:click="navigate(1)">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </button>
    </div>
    <div class="gr-label">{{ $anchor->format('F Y') }}</div>
</div>

<div class="gr-legend">
    @foreach($legend as [$color, $label])
        <div class="gr-legend-item">
            <span class="gr-legend-dot" style="background: {{ $color }}"></span>
            <span class="gr-legend-text">{{ $label }}</span>
        </div>
    @endforeach
</div>

{{-- Week strip --}}
<div class="gr-day-weekstrip">
    @foreach($weekDays as $d)
        @php
            $isSelected = $d->isSameDay($anchor);
            $isToday = $d->isSameDay($today);
        @endphp
        <button type="button" class="gr-day-ws-btn" wire:click="$set('anchor', '{{ $d->toDateString() }}')">
            <div class="gr-day-ws-dow">{{ $d->format('D') }}</div>
            <div class="gr-day-ws-date {{ $isSelected ? 'on' : ($isToday ? 'today' : '') }}">{{ $d->format('j') }}</div>
            <div style="display:flex; gap:2px; height:4px;"></div>
        </button>
    @endforeach
</div>

<div class="gr-day-heading">
    <div class="gr-day-heading-eyebrow">
        {{ $anchor->isSameDay($today) ? 'Today' : $anchor->format('l') }}
    </div>
    <div class="gr-day-heading-title">{{ $anchor->format('l, j F') }}</div>
</div>

{{-- All-day events --}}
@if(count($allDay) > 0)
    <div class="gr-day-allday">
        @foreach($allDay as $e)
            @php $r = $e['rental']; $color = $statuses[$r->status][0] ?? '#6b7280'; @endphp
            <div class="gr-event"
                style="height:20px; background: {{ $color }};"
                wire:click="mountAction('viewRentalDetails', { rentalId: {{ $r->id }} })"
                title="{{ $r->rental_code }} — {{ $r->customer?->name }}"
            >
                <div class="gr-event-title">All-day · {{ $r->customer?->name ?? '—' }}</div>
            </div>
        @endforeach
    </div>
@endif

{{-- Timeline --}}
<div class="gr-day-timeline-wrap">
    <div class="gr-day-timeline" style="height: {{ count($hours) * $HOUR_H + 24 }}px;">
        @foreach($hours as $i => $h)
            @php
                $hLabel = $h === 12 ? '12 PM' : ($h > 12 ? ($h - 12) . ' PM' : $h . ' AM');
            @endphp
            <div class="gr-hour" style="top: {{ $i * $HOUR_H }}px; height: {{ $HOUR_H }}px;">
                <div class="gr-hour-label">{{ $hLabel }}</div>
            </div>
        @endforeach

        @if($showNow)
            <div class="gr-now-line" style="top: {{ ($nowHour - $START_HOUR) * $HOUR_H }}px;">
                <div class="gr-now-dot"></div>
                <div class="gr-now-bar"></div>
            </div>
        @endif

        @foreach($placed as $e)
            @php
                $r = $e['rental'];
                $color = $statuses[$r->status][0] ?? '#6b7280';
                $top = ($e['start_h'] - $START_HOUR) * $HOUR_H;
                $height = ($e['end_h'] - $e['start_h']) * $HOUR_H - 4;
                if ($height < 20) $height = 20;
                $gutter = 50;
                $colPct = $maxCols > 0 ? ($e['col'] / $maxCols) * 100 : 0;
                $widthPct = $maxCols > 0 ? (100 / $maxCols) : 100;
            @endphp
            <div style="position:absolute; top:{{ $top }}px; height:{{ $height }}px; left:calc({{ $gutter }}px + (100% - {{ $gutter + 8 }}px) * {{ $e['col'] }} / {{ $maxCols }}); width:calc((100% - {{ $gutter + 8 }}px) / {{ $maxCols }} - 4px);">
                <div class="gr-event"
                    style="height:100%; background: {{ $color }};"
                    wire:click="mountAction('viewRentalDetails', { rentalId: {{ $r->id }} })"
                    title="{{ $r->rental_code }} — {{ $r->customer?->name }}"
                >
                    <div class="gr-event-title">{{ $r->customer?->name ?? '—' }}</div>
                    <div class="gr-event-sub">{{ $r->rental_code }}</div>
                </div>
            </div>
        @endforeach

        @if(count($events) === 0 && count($allDay) === 0)
            <div style="position:absolute; top:40%; left:50%; transform:translate(-50%,-50%); color:#9ca3af; font-size:13px;">
                No bookings for this day.
            </div>
        @endif
    </div>
</div>
