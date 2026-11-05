<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">Simpan pengaturan</x-filament::button>
    </form>
    <div class="flex flex-wrap gap-3">
        <x-filament::button wire:click="testConnection" wire:loading.attr="disabled" color="gray">Tes koneksi tersimpan</x-filament::button>
        <a href="{{ \App\Services\Mufise\MufiseSettings::url() }}" target="_blank" rel="noopener noreferrer" class="text-sm underline">Buka Mufise → Organisasi → Koneksi eksternal ↗</a>
    </div>
    <p class="text-sm text-gray-500">Simpan perubahan sebelum tes. Izin kru, lokasi, call sheet dan fitur editor diatur admin organisasi di Mufise. Izin tambahan memerlukan pemasangan ulang akun pelanggan.</p>
    @if($connectionTest)
        <div role="status" class="rounded-lg border p-4 space-y-2">
            <p>{{ $connectionTest['ok'] ? 'Koneksi berhasil' : 'Koneksi perlu diperiksa' }}</p>
            <p>{{ $connectionTest['message'] }}</p>
            @if(!empty($connectionTest['reference']))<p class="text-xs">Referensi: {{ $connectionTest['reference'] }}</p>@endif
        </div>
    @endif
</x-filament-panels::page>
