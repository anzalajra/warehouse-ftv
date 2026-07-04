<x-filament-panels::page>
    <div class="space-y-4">
        {{-- Header & Navigation --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-200 dark:border-white/10 shadow-sm">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $record->name }}</h2>
                <p class="text-xs text-gray-500">Rental schedule for all units</p>
            </div>
            
            <div class="flex items-center gap-2 bg-gray-100 dark:bg-white/5 p-1 rounded-lg">
                <x-filament::button wire:click="previousMonth" icon="heroicon-m-chevron-left" color="gray" size="sm" variant="ghost" />
                <span class="text-sm font-bold px-4 text-gray-700 dark:text-gray-200">
                    {{ $startDate->format('M Y') }} - {{ $endDate->format('M Y') }}
                </span>
                <x-filament::button wire:click="nextMonth" icon="heroicon-m-chevron-right" color="gray" size="sm" variant="ghost" />
            </div>
        </div>

        {{-- Timeline (hour-aware: same-day rentals split within the day column instead of overlapping) --}}
        @php
            $DAY_W = 58;
            $NAME_W = 190;
            $ROW_H = 44;
            $daysCount = count($days);
            $gridStart = $daysCount ? $days[0] : now()->startOfDay();
            $units = $this->getUnitsWithRentals();

            // Group consecutive days by month for the merged month-label header row.
            $monthGroups = [];
            foreach ($days as $d) {
                $key = $d->format('Y-m');
                if (empty($monthGroups) || $monthGroups[count($monthGroups) - 1]['key'] !== $key) {
                    $monthGroups[] = ['key' => $key, 'label' => $d->format('F Y'), 'count' => 1];
                } else {
                    $monthGroups[count($monthGroups) - 1]['count']++;
                }
            }
        @endphp
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-white/10 shadow-sm overflow-hidden">
            <div class="overflow-x-auto overflow-y-hidden">
                <div style="min-width: {{ $NAME_W + $DAY_W * $daysCount }}px;">

                    {{-- Sticky header: month row + day row --}}
                    <div style="position:sticky; top:0; z-index:20;">
                        {{-- Month label row --}}
                        <div style="display:flex;">
                            <div style="width: {{ $NAME_W }}px; position:sticky; left:0; z-index:30;"
                                 class="bg-gray-50 dark:bg-gray-800 border-b border-r border-gray-200 dark:border-white/10"></div>
                            @foreach($monthGroups as $mg)
                                <div style="width: {{ $DAY_W * $mg['count'] }}px;"
                                     class="px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-wide text-primary-600 dark:text-primary-300 bg-gray-50 dark:bg-gray-800 border-b border-r border-gray-200 dark:border-white/10">
                                    {{ $mg['label'] }}
                                </div>
                            @endforeach
                        </div>
                        {{-- Day row --}}
                        <div style="display:flex;">
                            <div style="width: {{ $NAME_W }}px; position:sticky; left:0; z-index:30;"
                                 class="p-3 bg-gray-50 dark:bg-gray-800 border-b border-r border-gray-200 dark:border-white/10 text-[10px] font-extrabold uppercase tracking-wide text-gray-400 flex items-center">
                                Unit Serial Number
                            </div>
                            @foreach($days as $day)
                                <div style="width: {{ $DAY_W }}px;"
                                     class="pt-1.5 pb-2 border-b border-r border-gray-100 dark:border-white/5 text-center {{ $day->isToday() ? 'bg-primary-50 dark:bg-primary-900/20' : ($day->isWeekend() ? 'bg-gray-50/60 dark:bg-white/5' : 'bg-white dark:bg-gray-900') }}">
                                    <div class="text-[9px] font-semibold text-gray-400">{{ strtoupper($day->format('D')) }}</div>
                                    <div class="mx-auto mt-0.5 flex h-[26px] w-[26px] items-center justify-center rounded-full text-[11px] font-medium
                                        {{ $day->isToday() ? 'bg-primary-600 text-white font-bold' : ($day->isWeekend() ? 'text-gray-400' : 'text-gray-700 dark:text-gray-100') }}">
                                        {{ str_pad((string) $day->day, 2, '0', STR_PAD_LEFT) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Unit rows --}}
                    @forelse($units as $data)
                        <div style="display:flex;" wire:key="unit-{{ $data['unit']->id }}">
                            <div style="width: {{ $NAME_W }}px; position:sticky; left:0; z-index:10;"
                                 class="px-3 bg-white dark:bg-gray-900 border-b border-r border-gray-200 dark:border-white/10 flex items-center">
                                <span class="font-mono text-[10px] font-bold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-white/10 px-2 py-1 rounded truncate">{{ $data['unit']->serial_number }}</span>
                            </div>
                            <div style="position:relative; flex:1; height: {{ $ROW_H }}px;">
                                {{-- Day grid background --}}
                                @foreach($days as $day)
                                    <div style="position:absolute; top:0; bottom:0; left:{{ $loop->index * $DAY_W }}px; width:{{ $DAY_W }}px;"
                                         class="border-r border-b border-gray-100 dark:border-white/5 {{ $day->isToday() ? 'bg-primary-50/30 dark:bg-primary-900/10' : ($day->isWeekend() ? 'bg-gray-50/40 dark:bg-white/[.02]' : '') }}"></div>
                                @endforeach

                                {{-- Rental bars --}}
                                @foreach($data['rentals'] as $rental)
                                    @php
                                        $rStart = $rental['start'];
                                        $rEnd = $rental['end'];
                                        // Fractional day-units from grid start (hour-aware) so two rentals
                                        // sharing a calendar day split within the column, not overlap.
                                        $startDay = (int) $gridStart->diffInDays($rStart->copy()->startOfDay(), false);
                                        $endDay = (int) $gridStart->diffInDays($rEnd->copy()->startOfDay(), false);
                                        $startPos = $startDay + ($rStart->hour * 60 + $rStart->minute) / 1440;
                                        $endPos = $endDay + ($rEnd->hour * 60 + $rEnd->minute) / 1440;
                                        if ($startPos < 0) $startPos = 0;
                                        if ($endPos > $daysCount) $endPos = $daysCount;
                                        $skipRental = $endPos <= $startPos;
                                        $status = $rental['status'] ?? '';
                                        $hex = \App\Models\Rental::getStatusHexColor($status);
                                        $left = $startPos * $DAY_W + 1;
                                        $width = ($endPos - $startPos) * $DAY_W - 2;
                                        if ($width < 6) $width = 6;
                                    @endphp
                                    @if(! $skipRental)
                                        <div
                                            wire:click="mountAction('viewRentalDetails', { rentalId: {{ $rental['id'] }} })"
                                            style="position:absolute; top:6px; bottom:6px; left:{{ $left }}px; width:{{ $width }}px; background:{{ $hex }}; z-index:10;"
                                            class="flex items-center px-1 shadow-sm cursor-pointer hover:opacity-80 transition-opacity rounded-sm overflow-hidden"
                                            title="{{ $rental['code'] }} - {{ $rental['customer'] }} ({{ ucfirst(str_replace('_', ' ', $status)) }}) — {{ $rStart->format('j M H:i') }} → {{ $rEnd->format('j M H:i') }}"
                                        >
                                            <span class="text-[9px] font-bold text-white truncate whitespace-nowrap leading-none px-1">
                                                {{ $rental['customer'] }}
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-sm text-gray-400">No units for this product.</div>
                    @endforelse
                </div>
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
            <div class="flex items-center gap-1"><div class="w-3 h-3 rounded bg-yellow-500"></div> Partial Return</div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>

