@php
    // Parent unit (always serial-bearing) + kits that carry a serial number.
    $rows = collect();
    $rows->push([
        'name' => $record->product->name ?? 'Unit',
        'serial' => $record->serial_number,
        'is_unit' => true,
    ]);

    foreach ($record->kits as $kit) {
        if (filled($kit->serial_number)) {
            $rows->push([
                'name' => $kit->name,
                'serial' => $kit->serial_number,
                'is_unit' => false,
            ]);
        }
    }
@endphp

<div class="space-y-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Setiap kode meng-encode <span class="font-mono">PREFIX:serial</span> (sistem tertutup — kamera HP hanya menampilkan teks, tidak membuka website).
        Cetak ulang menghasilkan gambar yang identik. <strong>Kit kecil? Disarankan pakai QR.</strong>
    </p>

    <a href="{{ \App\Filament\Pages\LabelPrinter::getUrl(['unit' => $record->id]) }}"
       class="fi-btn inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 hover:bg-primary-500 px-3.5 py-2 text-sm font-semibold text-white">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Z" />
        </svg>
        Print via Bluetooth (Luck Printer)
    </a>
    <p class="text-[11px] text-gray-400 -mt-1">Cetak langsung ke printer label Bluetooth (Chrome/Edge, butuh HTTPS). Unit + semua kit ber-serial masuk antrian.</p>

    <div class="divide-y divide-gray-100 dark:divide-white/10 rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden">
        @foreach ($rows as $row)
            <div class="flex items-center gap-3 p-3">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $row['name'] }}</span>
                        <span @class([
                            'text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded-full',
                            'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' => $row['is_unit'],
                            'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400' => ! $row['is_unit'],
                        ])>{{ $row['is_unit'] ? 'Unit' : 'Kit' }}</span>
                    </div>
                    <div class="font-mono text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $row['serial'] }}</div>
                </div>
                <div class="flex items-center gap-2 flex-none">
                    <a href="{{ route('admin.unit-label', ['serial' => $row['serial'], 'type' => 'qr']) }}"
                       download
                       class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 px-2.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/10">
                        QR
                    </a>
                    <a href="{{ route('admin.unit-label', ['serial' => $row['serial'], 'type' => 'barcode']) }}"
                       download
                       class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 px-2.5 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-white/10">
                        Barcode
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
