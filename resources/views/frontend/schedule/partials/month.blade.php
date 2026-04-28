@php
    $weeks = $monthData['weeks'];
    $weekSegments = $monthData['weekSegments'];
    $monthStart = $monthData['monthStart'];
    $today = now()->startOfDay();

    $maxLanes = 3;
    $barH = 20;
    $barGap = 2;
    $dateH = 26;
@endphp

<div class="gr-toolbar">
    <a href="{{ $buildUrl(['anchor' => now()->toDateString()]) }}" class="gr-today">Today</a>
    <div style="display:flex; gap:2px;">
        <a href="{{ $buildUrl(['anchor' => $navAnchor(-1)]) }}" class="gr-nav">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
        </a>
        <a href="{{ $buildUrl(['anchor' => $navAnchor(1)]) }}" class="gr-nav">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </a>
    </div>
    <div class="gr-label">{{ $range['label'] }}</div>
</div>

<div class="gr-legend">
    @foreach($legend as [$color, $label])
        <div class="gr-legend-item">
            <span class="gr-legend-dot" style="background: {{ $color }}"></span>
            <span class="gr-legend-text">{{ $label }}</span>
        </div>
    @endforeach
</div>

<div class="gr-month-grid">
    <div class="gr-dow-row">
        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
            <div class="gr-dow">{{ $d }}</div>
        @endforeach
    </div>

    @foreach($weeks as $wIdx => $week)
        @php
            $segs = $weekSegments[$wIdx] ?? [];
            $visible = array_filter($segs, fn($s) => ($s['lane'] ?? 0) < $maxLanes);
            $overflowPerCol = array_fill(0, 7, 0);
            foreach ($segs as $s) {
                if (($s['lane'] ?? 0) >= $maxLanes) {
                    for ($c = $s['start_col']; $c <= $s['end_col']; $c++) {
                        if ($c >= 0 && $c < 7) $overflowPerCol[$c]++;
                    }
                }
            }
        @endphp
        <div class="gr-week-row">
            @foreach($week as $dIdx => $date)
                @php
                    $inMonth = $date->month === $monthStart->month;
                    $isToday = $date->isSameDay($today);
                @endphp
                <div class="gr-day-cell {{ $inMonth ? '' : 'other' }} {{ $isToday ? 'today' : '' }}">
                    <div class="gr-date">
                        <div class="gr-date-num {{ $isToday ? 'today' : ($inMonth ? '' : 'other') }}">
                            {{ $date->day }}
                        </div>
                    </div>
                </div>
            @endforeach

            @foreach($visible as $s)
                @php
                    $r = $s['rental'];
                    $status = $r->status;
                    $color = $statuses[$status][0] ?? '#6b7280';
                    $lp = ($s['start_col'] / 7) * 100;
                    $wp = (($s['end_col'] - $s['start_col'] + 1) / 7) * 100;
                    $top = $dateH + ($s['lane'] ?? 0) * ($barH + $barGap);
                @endphp
                <button type="button"
                    class="gr-event"
                    style="position:absolute; top:{{ $top }}px; height:{{ $barH }}px; left:calc({{ $lp }}% + 2px); width:calc({{ $wp }}% - 4px); background: {{ $color }};"
                    @click="loadRental({{ $r->id }})"
                    title="{{ $r->customer?->name }}"
                >
                    <div class="gr-event-title">
                        {{ $r->start_date->format('g:ia') }} {{ $r->customer?->name ?? '—' }}
                    </div>
                </button>
            @endforeach

            @foreach($overflowPerCol as $col => $n)
                @if($n > 0)
                    @php
                        $topMore = $dateH + $maxLanes * ($barH + $barGap);
                        $dateIso = $week[$col]->toDateString();
                    @endphp
                    <button type="button"
                        class="gr-more-btn"
                        style="top:{{ $topMore }}px; left:calc({{ ($col / 7) * 100 }}% + 4px);"
                        @click="loadDay('{{ $dateIso }}')"
                    >
                        +{{ $n }} more
                    </button>
                @endif
            @endforeach
        </div>
    @endforeach
</div>
