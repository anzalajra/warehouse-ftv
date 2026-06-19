<x-filament-panels::page>
    <div class="flex flex-col md:flex-row gap-4 md:overflow-x-auto pb-4 h-auto md:h-[calc(100vh-12rem)]">
        @foreach($this->getStatuses() as $status => $label)
            @php
                $records = $this->getRecords()->get($status, collect());
                $color = match($status) {
                    'quotation' => 'bg-orange-50 border-orange-200',
                    'active' => 'bg-green-50 border-green-200',
                    'late_pickup', 'late_return' => 'bg-red-50 border-red-200',
                    'completed' => 'bg-blue-50 border-blue-200',
                    'cancelled' => 'bg-gray-50 border-gray-200',
                    default => 'bg-gray-50 border-gray-200',
                };
                $headerColor = match($status) {
                    'quotation' => 'text-orange-700 bg-orange-200',
                    'active' => 'text-green-700 bg-green-200',
                    'late_pickup', 'late_return' => 'text-red-700 bg-red-200',
                    'completed' => 'text-blue-700 bg-blue-200',
                    'cancelled' => 'text-gray-700 bg-gray-200',
                    default => 'text-gray-700 bg-gray-200',
                };
            @endphp
            
            <div class="flex-shrink-0 w-full md:w-80 flex flex-col {{ $color }} rounded-lg border md:h-full">
                <!-- Column Header -->
                <div class="p-3 font-semibold text-sm flex justify-between items-center rounded-t-lg {{ $headerColor }}">
                    <span>{{ $label }}</span>
                    <span class="bg-white/50 px-2 py-0.5 rounded text-xs">{{ $records->count() }}</span>
                </div>

                <!-- Cards Container -->
                <div class="p-2 flex-1 overflow-y-auto space-y-3">
                    @foreach($records as $record)
                        <a href="{{ \App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $record]) }}" 
                           class="block bg-white p-3 rounded shadow-sm border border-gray-100 hover:shadow-md transition cursor-pointer hover:border-primary-500">
                            
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-medium text-sm text-gray-900">{{ $record->rental_code }}</span>
                                <span class="text-xs text-gray-500">{{ $record->created_at->format('d M') }}</span>
                            </div>
                            
                            <div class="mb-2">
                                <div class="text-sm font-semibold text-gray-800">{{ $record->customer->name ?? 'Unknown Customer' }}</div>
                                @if($record->hasPendingPartialReturn())
                                    <span class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded text-[10px] font-medium text-amber-700 bg-amber-100 border border-amber-200">
                                        <x-heroicon-m-arrow-path-rounded-square class="w-3 h-3"/>
                                        Partial return
                                    </span>
                                @endif
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
                        </div>
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
    </x-filament-panels::page>
