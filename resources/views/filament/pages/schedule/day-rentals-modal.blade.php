@php
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
@endphp

<div style="display:flex; flex-direction:column; gap:6px;">
    @forelse($rentals as $r)
        @php
            $color = $statuses[$r['status']][0] ?? '#6b7280';
            $label = $statuses[$r['status']][1] ?? ucfirst($r['status']);
        @endphp
        <button type="button"
            wire:click="mountAction('viewRentalDetails', { rentalId: {{ $r['id'] }} })"
            style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; text-align:left; width:100%; font-family:inherit;"
            class="dark:!bg-gray-800 dark:!border-white/10 hover:!bg-gray-50 dark:hover:!bg-white/5"
        >
            <span style="width:8px; height:28px; border-radius:3px; background:{{ $color }}; flex-shrink:0;"></span>
            <div style="flex:1; min-width:0;">
                <div style="font-size:13px; font-weight:700; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" class="dark:!text-gray-100">
                    {{ $r['customer'] }}
                </div>
                <div style="font-size:11px; color:#6b7280; margin-top:2px;" class="dark:!text-gray-400">
                    {{ $r['code'] }} · {{ $r['start'] }} → {{ $r['end'] }}
                </div>
            </div>
            <span style="font-size:10px; font-weight:700; padding:3px 8px; border-radius:999px; background:{{ $color }}1a; color:{{ $color }}; flex-shrink:0;">
                {{ $label }}
            </span>
        </button>
    @empty
        <div style="padding:20px; text-align:center; color:#9ca3af; font-size:13px;">
            No bookings for this day.
        </div>
    @endforelse
</div>
