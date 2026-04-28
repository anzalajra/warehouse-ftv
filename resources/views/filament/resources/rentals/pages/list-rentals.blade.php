<x-filament-panels::page>
    @livewire(\App\Filament\Resources\Rentals\Widgets\RentalStatsOverview::class)

    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-lg w-fit">
            <button 
                wire:click="setView('list')" 
                class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $currentView === 'list' ? 'bg-white dark:bg-gray-700 shadow text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-300' }}"
            >
                <div class="flex items-center gap-2">
                    <x-heroicon-o-list-bullet class="w-4 h-4" />
                    <span>List View</span>
                </div>
            </button>
            <button 
                wire:click="setView('kanban')" 
                class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $currentView === 'kanban' ? 'bg-white dark:bg-gray-700 shadow text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-300' }}"
            >
                <div class="flex items-center gap-2">
                    <x-heroicon-o-view-columns class="w-4 h-4" />
                    <span>Kanban View</span>
                </div>
            </button>
        </div>
    </div>

    @if($currentView === 'list')
        {{ $this->table }}
    @else
        <div 
            x-data="{ 
                statuses: (function() {
                    let saved = JSON.parse(localStorage.getItem('kanban_view_settings') || '{}');
                    let defaults = {
                        @foreach($this->getStatuses() as $status => $label)
                            '{{ $status }}': { 
                                visible: true, 
                                minimized: false,
                                label: '{{ $label }}'
                            },
                        @endforeach
                    };
                    
                    // Merge saved state into defaults
                    Object.keys(defaults).forEach(key => {
                        if (saved[key]) {
                            if (saved[key].hasOwnProperty('visible')) defaults[key].visible = saved[key].visible;
                            if (saved[key].hasOwnProperty('minimized')) defaults[key].minimized = saved[key].minimized;
                        }
                    });
                    
                    return defaults;
                })(),
                init() {
                    this.$watch('statuses', (val) => localStorage.setItem('kanban_view_settings', JSON.stringify(val)));
                }
            }"
            class="h-full"
        >
            <!-- View Filter Dropdown -->
            <div class="mb-4 flex justify-end">
                <x-filament::dropdown placement="bottom-end">
                    <x-slot name="trigger">
                        <x-filament::button icon="heroicon-o-adjustments-horizontal" color="gray">
                            View Filter
                        </x-filament::button>
                    </x-slot>
                    
                    <x-filament::dropdown.list>
                        <template x-for="(config, status) in statuses" :key="status">
                            <x-filament::dropdown.list.item
                                x-on:click.stop="config.visible = !config.visible"
                            >
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" :checked="config.visible" class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                                    <span x-text="config.label"></span>
                                </div>
                            </x-filament::dropdown.list.item>
                        </template>
                    </x-filament::dropdown.list>
                </x-filament::dropdown>
            </div>

            <div class="flex flex-col md:flex-row gap-4 overflow-x-auto pb-4 h-[calc(100vh-12rem)] items-start">
                @foreach($this->getStatuses() as $status => $label)
                    @php
                        $records = $this->getKanbanRecords()->get($status, collect());
                        $color = match($status) {
                            'quotation' => 'bg-orange-50 border-orange-200',
                            'confirmed' => 'bg-cyan-50 border-cyan-200',
                            'active' => 'bg-green-50 border-green-200',
                            'late_pickup', 'late_return' => 'bg-red-50 border-red-200',
                            'completed' => 'bg-blue-50 border-blue-200',
                            'cancelled' => 'bg-gray-50 border-gray-200',
                            default => 'bg-gray-50 border-gray-200',
                        };
                        $headerColor = match($status) {
                            'quotation' => 'text-orange-700 bg-orange-200',
                            'confirmed' => 'text-cyan-700 bg-cyan-200',
                            'active' => 'text-green-700 bg-green-200',
                            'late_pickup', 'late_return' => 'text-red-700 bg-red-200',
                            'completed' => 'text-blue-700 bg-blue-200',
                            'cancelled' => 'text-gray-700 bg-gray-200',
                            default => 'text-gray-700 bg-gray-200',
                        };
                    @endphp
                    
                    <div 
                        x-show="statuses['{{ $status }}'].visible"
                        :class="statuses['{{ $status }}'].minimized ? 'w-12 items-center' : 'w-80'"
                        class="flex-shrink-0 flex flex-col {{ $color }} rounded-lg border h-fit max-h-full transition-all duration-300"
                        style="display: none;" 
                        x-show.important="statuses['{{ $status }}'].visible"
                    >
                        <!-- Column Header -->
                        <div 
                            class="p-3 font-semibold text-sm flex justify-between items-center rounded-t-lg {{ $headerColor }} cursor-pointer"
                            :class="statuses['{{ $status }}'].minimized ? 'flex-col gap-4 h-full py-4 justify-start' : ''"
                            @click="statuses['{{ $status }}'].minimized = !statuses['{{ $status }}'].minimized"
                        >
                            <template x-if="!statuses['{{ $status }}'].minimized">
                                <div class="flex justify-between items-center w-full">
                                    <span>{{ $label }}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="bg-white/50 px-2 py-0.5 rounded text-xs">{{ $records->count() }}</span>
                                        <x-heroicon-m-chevron-left class="w-4 h-4 text-gray-500 hover:text-gray-700" />
                                    </div>
                                </div>
                            </template>
                            
                            <template x-if="statuses['{{ $status }}'].minimized">
                                <div class="flex flex-col items-center gap-2 h-full">
                                    <span class="bg-white/50 px-2 py-0.5 rounded text-xs">{{ $records->count() }}</span>
                                    <div class="writing-vertical-rl transform rotate-180 whitespace-nowrap">{{ $label }}</div>
                                    <x-heroicon-m-chevron-right class="w-4 h-4 text-gray-500 hover:text-gray-700 mt-auto" />
                                </div>
                            </template>
                        </div>

                        <!-- Cards Container -->
                        <div 
                            x-show="!statuses['{{ $status }}'].minimized"
                            class="p-2 flex-1 overflow-y-auto space-y-3 custom-scrollbar"
                        >
                            @foreach($records as $record)
                                <a href="{{ \App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $record]) }}" 
                                   class="block bg-white p-3 rounded shadow-sm border border-gray-100 hover:shadow-md transition cursor-pointer hover:border-primary-500">
                                    
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="font-medium text-sm text-gray-900">{{ $record->rental_code }}</span>
                                        <span class="text-xs text-gray-500">{{ $record->created_at->format('d M') }}</span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <div class="text-sm font-semibold text-gray-800">{{ $record->customer->name ?? 'Unknown Customer' }}</div>
                                    </div>

                                    <div class="text-xs text-gray-600 space-y-1">
                                        <div class="flex items-center gap-1">
                                            <x-heroicon-m-calendar class="w-3 h-3"/>
                                            <span>{{ $record->start_date?->format('d M H:i') }} - {{ $record->end_date?->format('d M H:i') }}</span>
                                        </div>
                                        
                                        @if($record->items->count() > 0)
                                            <div class="flex items-center gap-1">
                                                <x-heroicon-m-shopping-bag class="w-3 h-3"/>
                                                <span>{{ $record->items->count() }} Items</span>
                                            </div>
                                        @endif
                                        
                                        <div class="font-medium text-gray-900 mt-1">
                                            Rp {{ number_format($record->total, 0, ',', '.') }}
                                        </div>
                                    </div>
                                </a>
                            @endforeach

                            @if($records->isEmpty())
                                <div class="text-center py-4 text-gray-400 text-sm italic">
                                    No rentals
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        
        <style>
            .writing-vertical-rl {
                writing-mode: vertical-rl;
            }
        </style>
    @endif
</x-filament-panels::page>
