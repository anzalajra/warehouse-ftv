<x-filament-panels::page>
    @livewire('admin.rental-editor', ['record' => $this->rental ?? $this->record ?? null])
</x-filament-panels::page>
