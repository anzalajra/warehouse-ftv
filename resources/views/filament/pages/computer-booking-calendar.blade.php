<x-filament-panels::page>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
        <div class="flex flex-wrap gap-2 sm:gap-4 text-xs sm:text-sm">
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="w-3 h-3 sm:w-4 sm:h-4 rounded" style="background: #3b82f6;"></div>
                <span>Confirmed</span>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="w-3 h-3 sm:w-4 sm:h-4 rounded" style="background: #22c55e;"></div>
                <span>Active</span>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="w-3 h-3 sm:w-4 sm:h-4 rounded" style="background: #a855f7;"></div>
                <span>Completed</span>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="w-3 h-3 sm:w-4 sm:h-4 rounded" style="background: #6b7280;"></div>
                <span>Cancelled</span>
            </div>
            <div class="flex items-center gap-1.5 sm:gap-2">
                <div class="w-3 h-3 sm:w-4 sm:h-4 rounded" style="background: #ef4444;"></div>
                <span>No Show</span>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
