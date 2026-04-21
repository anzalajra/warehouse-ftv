@php
    $data = $this->getWeekData();
    $days = $data['days'];
    $rows = $data['rows'];
    $today = now()->startOfDay();
    $rowH = 54;
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

<div style="display:flex; flex-direction:column; flex:1; background:#fff;" class="dark:!bg-gray-900">
    <div class="gr-gantt-head">
        <div class="gr-gantt-hd-booking">Booking</div>
        <div class="gr-gantt-days">
            @foreach($days as $d)
                @php $isToday = $d->isSameDay($today); @endphp
                <div class="gr-gantt-day {{ $isToday ? 'today' : '' }}">
                    <div class="gr-gantt-dow">{{ strtoupper($d->format('D')) }}</div>
                    <div class="gr-gantt-date {{ $isToday ? 'today' : '' }}">{{ $d->format('j') }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="gr-gantt-body">
        @forelse($rows as $row)
            @php
                $r = $row['rental'];
                $status = $r->status;
                $color = $statuses[$status][0] ?? '#6b7280';
                $startCol = $row['start_col'];
                $endCol = $row['end_col'];
                $lp = ($startCol / 7) * 100;
                $wp = (($endCol - $startCol + 1) / 7) * 100;
            @endphp
            <div class="gr-gantt-row">
                <div class="gr-gantt-row-label">
                    <div class="gr-gantt-row-customer">{{ $r->customer?->name ?? '—' }}</div>
                    <div class="gr-gantt-row-dates">
                        {{ $r->start_date->format('j M') }} → {{ $r->end_date->format('j M') }}
                    </div>
                </div>
                <div class="gr-gantt-area">
                    @foreach($days as $d)
                        <div class="gr-gantt-area-cell {{ $d->isSameDay($today) ? 'today' : '' }}"></div>
                    @endforeach
                    <div class="gr-event"
                        style="position:absolute; top:8px; height:{{ $rowH - 16 }}px; left:calc({{ $lp }}% + 3px); width:calc({{ $wp }}% - 6px); background: {{ $color }};"
                        wire:click="mountAction('viewRentalDetails', { rentalId: {{ $r->id }} })"
                        title="{{ $r->rental_code }} — {{ $r->customer?->name }}"
                    >
                        <div class="gr-event-title">{{ $r->customer?->name ?? '—' }}</div>
                        <div class="gr-event-sub">{{ $r->start_date->format('j M') }} → {{ $r->end_date->format('j M') }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div style="padding:40px 20px; text-align:center; color:#9ca3af; font-size:13px;">
                No bookings in this week.
            </div>
        @endforelse
        <div style="height:32px"></div>
    </div>
</div>
