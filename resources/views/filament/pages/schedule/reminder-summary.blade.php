@php
    /** @var \Illuminate\Support\Collection $pickups */
    /** @var \Illuminate\Support\Collection $returns */
@endphp

<div class="space-y-6">
    <div>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2 flex items-center gap-2">
            <x-filament::icon icon="heroicon-o-arrow-up-tray" class="w-4 h-4 text-amber-600" />
            Pickup ({{ $pickups->count() }})
        </h3>
        @if ($pickups->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">Tidak ada pickup pada tanggal ini.</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                @foreach ($pickups as $r)
                    <li class="flex items-center justify-between gap-3 px-3 py-2 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ $r->customer?->name ?? '-' }}
                                @if ($r->status === \App\Models\Rental::STATUS_QUOTATION)
                                    <span class="ml-1 text-xs text-amber-600">(belum konfirmasi)</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $r->rental_code }} · {{ optional($r->start_date)->format('H:i') }}
                            </div>
                        </div>
                        <a href="{{ url('/admin/rentals/' . $r->id . '/pickup') }}"
                           class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                            Pickup
                            <x-filament::icon icon="heroicon-o-arrow-right" class="w-3 h-3" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2 flex items-center gap-2">
            <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="w-4 h-4 text-emerald-600" />
            Return ({{ $returns->count() }})
        </h3>
        @if ($returns->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400 italic">Tidak ada return pada tanggal ini.</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-gray-700 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                @foreach ($returns as $r)
                    <li class="flex items-center justify-between gap-3 px-3 py-2 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                {{ $r->customer?->name ?? '-' }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $r->rental_code }} · {{ optional($r->end_date)->format('H:i') }}
                            </div>
                        </div>
                        <a href="{{ url('/admin/rentals/' . $r->id . '/return') }}"
                           class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">
                            Return
                            <x-filament::icon icon="heroicon-o-arrow-right" class="w-3 h-3" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
