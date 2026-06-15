@php
    use App\Models\MaintenanceRecord;

    $statusColors = [
        MaintenanceRecord::STATUS_PENDING => 'background:#f3f4f6;color:#374151;',
        MaintenanceRecord::STATUS_IN_PROGRESS => 'background:#fef3c7;color:#92400e;',
        MaintenanceRecord::STATUS_COMPLETED => 'background:#dcfce7;color:#166534;',
    ];
    $typeLabels = MaintenanceRecord::getTypeOptions();
    $statusLabels = MaintenanceRecord::getStatusOptions();
@endphp

<div class="fi-modal-content space-y-3">
    @forelse ($records as $rec)
        <div style="border:1px solid rgba(0,0,0,0.08);border-radius:0.5rem;padding:0.75rem 1rem;"
             class="dark:!border-white/10">
            <div style="display:flex;justify-content:space-between;gap:0.75rem;align-items:flex-start;flex-wrap:wrap;">
                <div>
                    <div style="font-weight:600;">{{ $rec->title }}</div>
                    <div class="text-sm" style="opacity:.7;">
                        {{ $typeLabels[$rec->type] ?? $rec->type }}
                        @if ($rec->unitKit)
                            · Kit: {{ $rec->unitKit->name }}
                        @endif
                    </div>
                </div>
                <span style="font-size:.75rem;font-weight:600;padding:.125rem .5rem;border-radius:9999px;{{ $statusColors[$rec->status] ?? 'background:#f3f4f6;color:#374151;' }}">
                    {{ $statusLabels[$rec->status] ?? $rec->status }}
                </span>
            </div>

            @if ($rec->description)
                <div class="text-sm" style="margin-top:.5rem;white-space:pre-line;opacity:.85;">{{ $rec->description }}</div>
            @endif

            <div class="text-sm" style="display:flex;gap:1.25rem;flex-wrap:wrap;margin-top:.5rem;opacity:.75;">
                <span>📅 {{ optional($rec->date)->format('d M Y') ?? '—' }}</span>
                <span>⏱ {{ $rec->downtime_days }} hari</span>
                <span>💸 Rp {{ number_format((float) $rec->cost, 0, ',', '.') }}</span>
                <span>👤 {{ $rec->technician->name ?? '—' }}</span>
            </div>

            @if ($rec->rental)
                <div class="text-sm" style="margin-top:.4rem;opacity:.85;">
                    📦 Dari rental
                    <a href="{{ \App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $rec->rental_id]) }}"
                       style="font-weight:600;text-decoration:underline;">{{ $rec->rental->rental_code }}</a>
                    · Customer: {{ $rec->rental->customer->name ?? '—' }}
                </div>
            @endif
        </div>
    @empty
        <div class="text-sm" style="opacity:.7;text-align:center;padding:1.5rem 0;">
            Belum ada riwayat maintenance untuk unit ini.
        </div>
    @endforelse
</div>
