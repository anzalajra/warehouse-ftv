<x-filament-panels::page>
    @php
        $groups = $this->deliveryGroups();
        $summary = $this->summary();
        $users = $this->assignableUsers();
        $statusColors = [
            'draft' => 'gray',
            'pending' => 'warning',
            'completed' => 'success',
            'cancelled' => 'danger',
        ];
        $statusLabels = \App\Models\Delivery::getStatusOptions();
    @endphp

    <div class="space-y-5">

        {{-- ===================== Toolbar ===================== --}}
        <x-filament::section>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-2">
                    <x-filament::button color="gray" size="sm" icon="heroicon-o-chevron-left"
                        wire:click="shiftDay(-1)" />
                    <input type="date" wire:model.live="date"
                        class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm" />
                    <x-filament::button color="gray" size="sm" icon="heroicon-o-chevron-right"
                        wire:click="shiftDay(1)" />
                    <x-filament::button color="primary" size="sm" wire:click="goToday">Hari ini</x-filament::button>
                    <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ \Carbon\Carbon::parse($date)->translatedFormat('l, d M Y') }}
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    @foreach (['all' => 'Semua', 'out' => 'Keluar (SJK)', 'in' => 'Masuk (SJM)'] as $key => $label)
                        <button type="button" wire:click="$set('direction', '{{ $key }}')"
                            @class([
                                'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                                'bg-primary-600 text-white' => $direction === $key,
                                'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' => $direction !== $key,
                            ])>
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- KPI row --}}
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                @foreach ([
                    ['Total', $summary['total'], 'text-gray-900 dark:text-white'],
                    ['Ada Driver', $summary['assigned'], 'text-primary-600'],
                    ['Ambil Sendiri', $summary['self_pickup'], 'text-sky-600'],
                    ['Perlu Driver', $summary['unassigned'], 'text-amber-600'],
                    ['Selesai', $summary['completed'], 'text-green-600'],
                ] as [$label, $value, $tone])
                    <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 p-3">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="text-xl font-bold {{ $tone }}">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        {{-- ===================== Groups by driver ===================== --}}
        @forelse ($groups as $driverName => $deliveries)
            <x-filament::section x-data="{ open: true }">
                <x-slot name="heading">
                    <button type="button" @click="open = !open" class="flex items-center gap-2 text-left">
                        <x-filament::icon icon="heroicon-o-user-circle" class="h-5 w-5 text-gray-400" />
                        <span>{{ $driverName }}</span>
                        <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                            {{ $deliveries->count() }}
                        </span>
                    </button>
                </x-slot>

                <div x-show="open" class="space-y-3">
                    @foreach ($deliveries as $delivery)
                        @php
                            $rental = $delivery->rental;
                            $isOut = $delivery->type === \App\Models\Delivery::TYPE_OUT;
                            $selfPickup = $delivery->isSelfPickup();
                        @endphp
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span @class([
                                            'rounded px-2 py-0.5 text-xs font-semibold',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $isOut,
                                            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' => ! $isOut,
                                        ])>{{ $isOut ? 'Keluar' : 'Masuk' }}</span>
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $delivery->delivery_number }}</span>
                                        <x-filament::badge :color="$statusColors[$delivery->status] ?? 'gray'">
                                            {{ $statusLabels[$delivery->status] ?? ucfirst($delivery->status) }}
                                        </x-filament::badge>
                                        <x-filament::badge :color="$selfPickup ? 'info' : 'primary'"
                                            :icon="$selfPickup ? 'heroicon-o-building-storefront' : 'heroicon-o-truck'">
                                            {{ $selfPickup ? 'Ambil sendiri' : 'Antar' }}
                                        </x-filament::badge>
                                    </div>

                                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                        {{ $rental?->rental_code ?? '—' }}
                                        &middot; {{ $rental?->user?->name ?? 'Tanpa customer' }}
                                    </div>

                                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center gap-1">
                                            <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4" />
                                            {{ $delivery->scheduled_at?->format('H:i') ?? '—' }}
                                        </span>
                                        <span class="inline-flex items-center gap-1">
                                            <x-filament::icon icon="heroicon-o-cube" class="h-4 w-4" />
                                            {{ $delivery->items->count() }} item
                                        </span>
                                        @if ($delivery->escort)
                                            <span class="inline-flex items-center gap-1">
                                                <x-filament::icon icon="heroicon-o-shield-check" class="h-4 w-4" />
                                                Pengawal: {{ $delivery->escort->name }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($delivery->address)
                                        <div class="mt-1 flex items-start gap-1 text-xs text-gray-500 dark:text-gray-400">
                                            <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4 shrink-0" />
                                            <span>{{ $delivery->address }}</span>
                                        </div>
                                    @elseif ($selfPickup)
                                        <div class="mt-1 flex items-start gap-1 text-xs text-gray-500 dark:text-gray-400">
                                            <x-filament::icon icon="heroicon-o-building-storefront" class="h-4 w-4 shrink-0" />
                                            <span>Diambil di gudang — driver tidak wajib. Assign pengawal bila perlu.</span>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-col items-end gap-2">
                                    <div class="flex items-center gap-1">
                                        <x-filament::button size="xs" color="gray" icon="heroicon-o-user-plus"
                                            wire:click="openAssign({{ $delivery->id }})">
                                            Tugaskan
                                        </x-filament::button>
                                        @if ($rental)
                                            <x-filament::button size="xs" color="gray" tag="a"
                                                :href="\App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $rental])">
                                                Buka
                                            </x-filament::button>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-1">
                                        @if ($delivery->status !== \App\Models\Delivery::STATUS_PENDING)
                                            <x-filament::button size="xs" color="warning"
                                                wire:click="setStatus({{ $delivery->id }}, 'pending')">
                                                Pending
                                            </x-filament::button>
                                        @endif
                                        @if ($delivery->status !== \App\Models\Delivery::STATUS_COMPLETED)
                                            <x-filament::button size="xs" color="success"
                                                wire:click="setStatus({{ $delivery->id }}, 'completed')">
                                                Selesai
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="py-10 text-center text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-truck" class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                    <p class="mt-2 text-sm">Tidak ada pengiriman terjadwal pada tanggal ini.</p>
                </div>
            </x-filament::section>
        @endforelse

    </div>

    {{-- ===================== Assignment modal ===================== --}}
    @if ($editingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            wire:click.self="closeAssign">
            <div class="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-5 shadow-2xl">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Tugaskan Pengiriman</h3>

                @if ($editSelfPickup)
                    <div class="mt-3 flex items-start gap-2 rounded-lg bg-sky-50 dark:bg-sky-900/20 p-3 text-xs text-sky-700 dark:text-sky-300">
                        <x-filament::icon icon="heroicon-o-building-storefront" class="h-4 w-4 shrink-0 mt-0.5" />
                        <span>Rental ini <strong>diambil sendiri</strong> — driver tidak wajib. Isi <strong>pengawal / escort</strong> saja bila alat perlu didampingi.</span>
                    </div>
                @endif

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Driver</label>
                        <select wire:model="editDriverId"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                            <option value="">— Belum ditugaskan —</option>
                            @foreach ($users as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Pengawal / Escort</label>
                        <select wire:model="editEscortId"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                            <option value="">— Tidak ada —</option>
                            @foreach ($users as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Jadwal</label>
                        <input type="datetime-local" wire:model="editScheduledAt"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Alamat</label>
                        <textarea wire:model="editAddress" rows="3"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm"></textarea>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <x-filament::button color="gray" wire:click="closeAssign">Batal</x-filament::button>
                    <x-filament::button color="primary" wire:click="saveAssign">Simpan</x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
