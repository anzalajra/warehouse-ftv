@php
    /**
     * Activity log card — renders $rental->activity_log (newest first).
     * Self-contained inline styling so it looks identical inside the Livewire
     * rental editor and the Filament view page. Pass $rental.
     */
    $entries = collect($rental->activity_log ?? [])->reverse()->values();

    $logTone = [
        'move'   => ['bg' => '#eff6ff', 'fg' => '#1d4ed8', 'br' => '#bfdbfe', 'label' => 'Move'],
        'swap'   => ['bg' => '#faf5ff', 'fg' => '#7e22ce', 'br' => '#e9d5ff', 'label' => 'Swap'],
        'status' => ['bg' => '#f0fdf4', 'fg' => '#15803d', 'br' => '#bbf7d0', 'label' => 'Status'],
        'general'=> ['bg' => '#f8fafc', 'fg' => '#475569', 'br' => '#e2e8f0', 'label' => 'Log'],
    ];
@endphp

<div class="card">
    <div class="card-head">
        <h3>Log Aktivitas</h3>
        @if($entries->isNotEmpty())
            <span style="font-size:12px;color:var(--fg-3,#64748b);font-weight:500;">{{ $entries->count() }} entri</span>
        @endif
    </div>
    <div class="card-body" style="padding:14px 20px;">
        @forelse($entries as $entry)
            @php
                $tone = $logTone[$entry['type'] ?? 'general'] ?? $logTone['general'];
                $when = \Carbon\Carbon::parse($entry['at']);
            @endphp
            <div style="display:flex;gap:12px;padding:10px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border-1,#eef0f3);' : '' }}">
                <span style="flex:none;height:fit-content;margin-top:1px;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;line-height:1.5;
                             background:{{ $tone['bg'] }};color:{{ $tone['fg'] }};border:1px solid {{ $tone['br'] }};">{{ $tone['label'] }}</span>
                <div style="min-width:0;flex:1;">
                    <div style="font-size:13.5px;color:var(--fg-1,#1e293b);line-height:1.45;word-break:break-word;">{{ $entry['message'] ?? '' }}</div>
                    <div style="font-size:12px;color:var(--fg-3,#64748b);margin-top:2px;">
                        {{ $when->format('d M Y, H:i') }}
                        @if(! empty($entry['user']) && $entry['user'] !== 'system')
                            · {{ $entry['user'] }}
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div style="font-size:13px;color:var(--fg-3,#64748b);padding:6px 0;">Belum ada aktivitas tercatat.</div>
        @endforelse
    </div>
</div>
