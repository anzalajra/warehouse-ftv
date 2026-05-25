<x-filament-panels::page>
    @php
        /** @var \Illuminate\Support\Collection $computers */
        /** @var \Illuminate\Support\Collection $rooms */
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400">Total Komputer</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400">Online</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $stats['online'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400">Sedang Dipakai</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ $stats['in_use'] }}</p>
        </div>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400">Maintenance</p>
            <p class="text-2xl font-bold text-yellow-600 mt-1">{{ $stats['maintenance'] }}</p>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-5 p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Ruangan</label>
            <select wire:model.live="roomFilter"
                    class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                <option value="">Semua</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}">{{ $room->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-2">
            <label class="text-xs font-medium text-gray-600 dark:text-gray-300">Status</label>
            <select wire:model.live="statusFilter"
                    class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white focus:ring-primary-500 focus:border-primary-500">
                <option value="">Semua</option>
                <option value="{{ \App\Models\Computer::STATUS_AVAILABLE }}">Available</option>
                <option value="{{ \App\Models\Computer::STATUS_MAINTENANCE }}">Maintenance</option>
                <option value="{{ \App\Models\Computer::STATUS_RETIRED }}">Retired</option>
            </select>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
            <input type="checkbox" wire:model.live="onlineOnly"
                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
            Online saja
        </label>
    </div>

    @if ($computers->isEmpty())
        <div class="bg-white dark:bg-gray-900 border border-dashed border-gray-300 dark:border-gray-700 rounded-xl p-10 text-center text-gray-500">
            Tidak ada komputer yang cocok dengan filter.
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach ($computers as $computer)
                @php
                    $currentUser = $computer->currentBookingUser();
                    $editUrl = \App\Filament\Clusters\Computers\Resources\ComputerResource::getUrl('edit', ['record' => $computer]);
                    $isMaintenance = $computer->status === \App\Models\Computer::STATUS_MAINTENANCE;
                    $isRetired = $computer->status === \App\Models\Computer::STATUS_RETIRED;
                @endphp
                <a href="{{ $editUrl }}"
                   class="group block bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm hover:shadow-lg hover:border-primary-400 transition overflow-hidden">
                    <div class="relative aspect-video bg-gray-100 dark:bg-gray-800">
                        @if ($computer->image_path)
                            <img src="{{ asset('storage/'.$computer->image_path) }}"
                                 alt="{{ $computer->name }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-300 dark:text-gray-600">
                                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25"/>
                                </svg>
                            </div>
                        @endif

                        <div class="absolute top-2 left-2 flex items-center gap-1">
                            @if ($isMaintenance)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-yellow-100 text-yellow-800">Maintenance</span>
                            @elseif ($isRetired)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-200 text-gray-700">Retired</span>
                            @elseif ($computer->is_online)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-800">
                                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1 animate-pulse"></span>Online
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600">Offline</span>
                            @endif
                        </div>

                        @if ($currentUser)
                            <div class="absolute bottom-2 right-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-600 text-white shadow">
                                Dipakai
                            </div>
                        @endif
                    </div>

                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <h3 class="font-semibold text-gray-900 dark:text-white truncate group-hover:text-primary-600">
                                {{ $computer->name }}
                            </h3>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ $computer->room?->name ?? '—' }}
                            @if ($computer->code)
                                · {{ $computer->code }}
                            @endif
                        </p>

                        @if ($currentUser)
                            <div class="mt-3 px-2 py-1.5 rounded-md bg-blue-50 dark:bg-blue-900/30 text-xs text-blue-800 dark:text-blue-200">
                                <span class="font-medium">Sedang dipakai:</span> {{ $currentUser->name }}
                            </div>
                        @elseif ($computer->is_online)
                            <div class="mt-3 px-2 py-1.5 rounded-md bg-gray-50 dark:bg-gray-800 text-xs text-gray-500">
                                Idle (tidak ada sesi aktif)
                            </div>
                        @endif

                        @if ($computer->brand)
                            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">{{ $computer->brand }}</p>
                        @endif

                        @if (! empty($computer->specs))
                            <ul class="mt-2 text-[11px] text-gray-600 dark:text-gray-400 space-y-0.5">
                                @foreach (array_slice($computer->specs, 0, 2, true) as $key => $value)
                                    <li class="truncate"><span class="font-medium">{{ $key }}:</span> {{ $value }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($computer->last_seen_at)
                            <p class="mt-3 text-[11px] text-gray-400">Last seen {{ $computer->last_seen_at->diffForHumans() }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
