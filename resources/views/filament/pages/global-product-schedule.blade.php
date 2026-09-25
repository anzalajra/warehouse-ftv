<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Header & Navigation --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-white/10 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Global Product Schedule</h2>
                <p class="text-xs text-gray-500">Rental schedule for all products and units</p>
            </div>
            
            <div class="flex items-center gap-2 bg-gray-100 dark:bg-white/5 p-1 rounded-lg">
                <x-filament::button wire:click="previousMonth" icon="heroicon-m-chevron-left" color="gray" size="sm" variant="ghost" />
                <span class="text-sm font-bold px-4 text-gray-700 dark:text-gray-200">
                    {{ $startDate->format('M Y') }} - {{ $endDate->format('M Y') }}
                </span>
                <x-filament::button wire:click="nextMonth" icon="heroicon-m-chevron-right" color="gray" size="sm" variant="ghost" />
            </div>
        </div>

        {{-- Timeline Table --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-white/10 shadow-sm overflow-hidden">
            <div class="overflow-x-auto overflow-y-hidden">
                <table class="w-full text-left border-collapse table-fixed min-w-max border-spacing-0">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-30 p-3 bg-gray-50 dark:bg-gray-800 border-b border-r border-gray-200 dark:border-white/10 w-64 text-xs font-bold uppercase text-gray-600 dark:text-gray-400">
                                Product / Unit
                            </th>
                            @foreach($days as $day)
                                <th class="p-2 border-b border-r border-gray-200 dark:border-white/10 text-center min-w-[35px] {{ $day->isToday() ? 'bg-primary-50 dark:bg-primary-900/20' : 'bg-gray-50/50 dark:bg-white/5' }}">
                                    <div class="text-[9px] font-medium text-gray-400">{{ $day->format('D') }}</div>
                                    <div class="text-xs font-bold {{ $day->isToday() ? 'text-primary-600' : 'text-gray-700 dark:text-gray-200' }}">
                                        {{ $day->format('d') }}
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->getProductsWithUnitsAndRentals() as $group)
                            {{-- Product Header Row --}}
                            <tr class="bg-gray-50 dark:bg-gray-800/50">
                                <td colspan="{{ count($days) + 1 }}" class="sticky left-0 z-20 p-2 pl-3 border-b border-gray-200 dark:border-white/10 font-bold text-sm text-primary-600 dark:text-primary-400">
                                    {{ $group['product']->name }}
                                </td>
                                {{-- Fill remaining cells to maintain border structure if needed, or just colspan --}}
                            </tr>

                            @foreach($group['units'] as $data)
                                <tr class="h-12 border-b border-gray-100 dark:border-white/5 last:border-0">
                                    <td class="sticky left-0 z-20 p-3 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-white/10 text-sm text-gray-800 dark:text-gray-200 pl-6">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-mono bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded text-gray-600 dark:text-gray-400">
                                                {{ $data['unit']->serial_number }}
                                            </span>
                                        </div>
                                    </td>
                                    
                                    @php
                                        $occupiedDays = [];
                                        foreach($data['rentals'] as $rental) {
                                            $current = $rental['start']->copy()->startOfDay();
                                            $end = $rental['end']->copy()->startOfDay();
                                            while($current <= $end) {
                                                $occupiedDays[$current->format('Y-m-d')] = $rental;
                                                $current->addDay();
                                            }
                                        }
                                        $skipDays = 0;
                                    @endphp

                                    @foreach($days as $index => $day)
                                        @if($skipDays > 0)
                                            @php $skipDays--; @endphp
                                            @continue
                                        @endif

                                        @php
                                            $dateStr = $day->format('Y-m-d');
                                            $rental = $occupiedDays[$dateStr] ?? null;
                                            
                                            $colspan = 1;
                                            if ($rental) {
                                                // Calculate how many subsequent days have the same rental
                                                $remainingDays = count($days) - $index;
                                                for ($i = 1; $i < $remainingDays; $i++) {
                                                    $nextDateStr = $days[$index + $i]->format('Y-m-d');
                                                    $nextRental = $occupiedDays[$nextDateStr] ?? null;
                                                    if ($nextRental && $nextRental['id'] === $rental['id'] && $nextRental['status'] === $rental['status']) {
                                                        $colspan++;
                                                    } else {
                                                        break;
                                                    }
                                                }
                                                $skipDays = $colspan - 1;
                                            }
                                            
                                            $isStart = $rental && ($rental['start']->isSameDay($day) || $index == 0);

                                            $colorMap = [
                                                'quotation' => ['bg' => 'bg-orange-500', 'text' => 'text-white'],
                                                'confirmed' => ['bg' => 'bg-blue-500', 'text' => 'text-white'],
                                                'active' => ['bg' => 'bg-green-500', 'text' => 'text-white'],
                                                'completed' => ['bg' => 'bg-purple-500', 'text' => 'text-white'],
                                                'cancelled' => ['bg' => 'bg-gray-500', 'text' => 'text-white'],
                                                'late_pickup' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
                                                'late_return' => ['bg' => 'bg-red-600', 'text' => 'text-white'],
                                                'partial_return' => ['bg' => 'bg-yellow-500', 'text' => 'text-white'],
                                                'over_time' => ['bg' => 'bg-violet-500', 'text' => 'text-white'],
                                            ];
                                            $status = strtolower($rental['status'] ?? '');
                                            $colors = $colorMap[$status] ?? ['bg' => 'bg-gray-100 dark:bg-white/5', 'text' => 'text-transparent'];
                                        @endphp
                                        <td colspan="{{ $colspan }}" class="p-0 border-r border-gray-200 dark:border-white/10 relative {{ $day->isToday() && !$rental ? 'bg-primary-50/20' : '' }}">
                                            @if($rental)
                                                <div 
                                                    wire:click="mountAction('viewRentalDetails', { rentalId: {{ $rental['id'] }} })"
                                                    class="absolute inset-y-1 left-0 right-0 {{ $colors['bg'] }} z-10 flex items-center px-1 shadow-sm cursor-pointer hover:opacity-80 transition-opacity mx-0.5 rounded-sm"
                                                    title="{{ $rental['code'] }} - {{ $rental['customer'] }} ({{ ucfirst($status) }})"
                                                >
                                                    <span class="text-[9px] font-bold {{ $colors['text'] }} truncate whitespace-nowrap leading-none px-1">
                                                        {{ $status === 'over_time' ? 'Over-Time · ' : '' }}{{ $rental['customer'] }}
                                                    </span>
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 border-t border-gray-200 dark:border-white/10">
                {{ $this->getProductsWithUnitsAndRentals()->links() }}
            </div>
        </div>

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-4 text-[10px] font-bold uppercase tracking-wider text-gray-500 bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200 dark:border-white/10">
            <span class="mr-2">Status Legend:</span>
            <div class="flex items-center gap-1"><div class="w-3 h-3 rounded bg-orange-500"></div> Quotation</div>
            <div class="flex items-center gap-1"><div class="w-3 h-3 rounded bg-blue-500"></div> Confirmed</div>
            <div class="flex items-center gap-1"><div class="w-3 h-3 rounded bg-green-500"></div> Active</div>
            <div class="flex items-center gap-1"><div class="w-3 h-3 rounded bg-purple-500"></div> Completed</div>
            <div class="flex items-center gap-1"><div class="w-3 h-3 rounded bg-gray-500"></div> Cancelled</div>
            <div class="flex items-center gap-1"><div class="w-3 h-3 rounded bg-red-600"></div> Late Pickup/Return</div>
            <div class="flex items-center gap-1"><div class="w-3 h-3 rounded bg-violet-500"></div> Over-Time</div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
