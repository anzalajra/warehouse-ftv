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
