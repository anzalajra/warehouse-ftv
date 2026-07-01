<x-filament-panels::page>
    @php
        $recCounts = $this->getRecommendationCounts();
        $recTotal = array_sum($recCounts);
        $priorityBadge = ['high' => 'danger', 'medium' => 'warning', 'low' => 'gray'];
        $priorityLabel = ['high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah'];
    @endphp

    <div x-data="{ tab: @entangle('mainTab'), rentalSub: 'summary', invSub: 'stock' }" class="space-y-5">

        {{-- Date range filter --}}
        <x-filament::section>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Dari Tanggal</label>
                        <input type="date" wire:model.live="startDate"
                            class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sampai Tanggal</label>
                        <input type="date" wire:model.live="endDate"
                            class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm" />
                    </div>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400" wire:loading.remove>
                    Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} – {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                </div>
                <div class="text-xs text-primary-600 dark:text-primary-400" wire:loading>Memuat…</div>
            </div>
        </x-filament::section>

        {{-- Main tabs --}}
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Tabs">
                @foreach ([
                    'recommendations' => 'Rekomendasi',
                    'rental' => 'Rental',
                    'inventory' => 'Inventory',
                    'finance' => 'Finance',
                ] as $key => $label)
                    <button @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}'
                            ? 'border-primary-500 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                        class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors flex items-center gap-2">
                        {{ $label }}
                        @if ($key === 'recommendations' && $recTotal > 0)
                            <span class="inline-flex items-center justify-center rounded-full bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300 text-xs font-semibold h-5 min-w-5 px-1.5">{{ $recTotal }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- ============================ RECOMMENDATIONS ============================ --}}
        <div x-show="tab === 'recommendations'" x-cloak class="space-y-4">
            @php $recs = $this->getRecommendations(); @endphp
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Rekomendasi Bisnis</h3>
                @if (count($recs))
                    <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-down-tray" wire:click="export('recommendations', 'csv')">CSV</x-filament::button>
                @endif
            </div>

            @if (empty($recs))
                <x-filament::section>
                    <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-10 w-10 mx-auto mb-2 text-success-500" />
                        Tidak ada rekomendasi untuk periode ini. Semua indikator dalam batas wajar.
                    </div>
                </x-filament::section>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    @foreach ($recs as $rec)
                        <div @class([
                            'rounded-xl p-4 ring-1 shadow-sm bg-white dark:bg-gray-800',
                            'ring-danger-300 dark:ring-danger-500/40' => $rec['priority'] === 'high',
                            'ring-warning-300 dark:ring-warning-500/40' => $rec['priority'] === 'medium',
                            'ring-gray-200 dark:ring-white/10' => $rec['priority'] === 'low',
                        ])>
                            <div class="flex items-start justify-between gap-3">
                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $rec['title'] }}</h4>
                                <x-filament::badge :color="$priorityBadge[$rec['priority']] ?? 'gray'">
                                    {{ $priorityLabel[$rec['priority']] ?? $rec['priority'] }}
                                </x-filament::badge>
                            </div>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $rec['reason'] }}</p>
                            <p class="mt-2 text-sm text-gray-800 dark:text-gray-100"><span class="font-medium">Tindakan:</span> {{ $rec['action'] }}</p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $rec['metric'] }}</span>
                                @if (!empty($rec['link']))
                                    <a href="{{ $rec['link']['url'] }}" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">{{ $rec['link']['label'] }} →</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ============================ RENTAL ============================ --}}
        <div x-show="tab === 'rental'" x-cloak class="space-y-4">
            {{-- rental sub-tabs --}}
            <div class="flex flex-wrap gap-2">
                @foreach ([
                    'summary' => 'Ringkasan', 'customers' => 'Top Pelanggan', 'products' => 'Top Produk',
                    'late' => 'Keterlambatan', 'discounts' => 'Diskon', 'deposits' => 'Deposit & Bayar',
                    'duration' => 'Durasi & Konversi', 'logistics' => 'Serah-terima', 'revenue' => 'Revenue',
                ] as $key => $label)
                    <button @click="rentalSub = '{{ $key }}'"
                        :class="rentalSub === '{{ $key }}' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium">{{ $label }}</button>
                @endforeach
            </div>

            {{-- Ringkasan --}}
            <div x-show="rentalSub === 'summary'" x-cloak>
                @php $sum = $this->getRentalSummary(); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <x-reports.stat label="Total Rental" :value="$sum['total_count']" />
                    <x-reports.stat label="Terealisasi" :value="$sum['realized_count']" />
                    <x-reports.stat label="Nilai Kotor" :value="$this->money($sum['gross'])" />
                    <x-reports.stat label="Nilai Bersih" :value="$this->money($sum['net'])" />
                </div>
                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Per Status</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('rental_summary','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['Status', 'Jumlah', 'Subtotal', 'Total']">
                        @foreach ($sum['by_status'] as $row)
                            @if ($row['count'] > 0)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-2 px-2">{{ $row['label'] }}</td>
                                    <td class="py-2 px-2 text-right">{{ $row['count'] }}</td>
                                    <td class="py-2 px-2 text-right">{{ $this->money($row['subtotal']) }}</td>
                                    <td class="py-2 px-2 text-right">{{ $this->money($row['total']) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </x-reports.table>
                </x-filament::section>
            </div>

            {{-- Top customers --}}
            <div x-show="rentalSub === 'customers'" x-cloak>
                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Top Pelanggan</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('top_customers','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['#', 'Pelanggan', 'Jumlah', 'Total', 'Rata-rata']">
                        @foreach ($this->getTopCustomers() as $i => $c)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $i + 1 }}</td>
                                <td class="py-2 px-2">{{ $c['name'] }}<div class="text-xs text-gray-400">{{ $c['email'] }}</div></td>
                                <td class="py-2 px-2 text-right">{{ $c['rental_count'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($c['total_value']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($c['avg_value']) }}</td>
                            </tr>
                        @endforeach
                    </x-reports.table>
                </x-filament::section>
            </div>

            {{-- Top products --}}
            <div x-show="rentalSub === 'products'" x-cloak>
                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Top Produk</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('top_products','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['#', 'Produk', 'Baris Sewa', 'Total Hari', 'Pendapatan']">
                        @foreach ($this->getTopProducts() as $i => $p)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $i + 1 }}</td>
                                <td class="py-2 px-2">{{ $p['name'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $p['line_count'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $p['unit_days'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($p['revenue']) }}</td>
                            </tr>
                        @endforeach
                    </x-reports.table>
                </x-filament::section>
            </div>

            {{-- Late & penalty --}}
            <div x-show="rentalSub === 'late'" x-cloak>
                @php $late = $this->getLatePenalty(); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <x-reports.stat label="Total Denda" :value="$this->money($late['total_fee'])" />
                    <x-reports.stat label="Kena Denda" :value="$late['count_charged']" />
                    <x-reports.stat label="Status Telat" :value="$late['count_late_status']" />
                    <x-reports.stat label="Rata-rata Denda" :value="$this->money($late['avg_fee'])" />
                </div>
                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Rincian Keterlambatan</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('late','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['Kode', 'Pelanggan', 'Status', 'Selesai', 'Hari Telat', 'Denda']">
                        @foreach ($late['rows'] as $r)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $r['rental_code'] }}</td>
                                <td class="py-2 px-2">{{ $r['customer'] }}</td>
                                <td class="py-2 px-2">{{ $r['status'] }}</td>
                                <td class="py-2 px-2">{{ $r['end_date'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $r['days_late'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($r['late_fee']) }}</td>
                            </tr>
                        @endforeach
                    </x-reports.table>
                </x-filament::section>
            </div>

            {{-- Discounts --}}
            <div x-show="rentalSub === 'discounts'" x-cloak>
                @php $disc = $this->getDiscounts(); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
                    <x-reports.stat label="Nilai Kotor" :value="$this->money($disc['gross'])" />
                    <x-reports.stat label="Total Diskon" :value="$this->money($disc['total_discount'])" />
                    <x-reports.stat label="Rasio Diskon" :value="$disc['discount_ratio'].'%'" />
                </div>
                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Per Layer Diskon</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('discounts','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['Layer', 'Jumlah']">
                        @foreach ($disc['layers'] as $l)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $l['label'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($l['amount']) }}</td>
                            </tr>
                        @endforeach
                    </x-reports.table>
                </x-filament::section>
            </div>

            {{-- Deposits & payments --}}
            <div x-show="rentalSub === 'deposits'" x-cloak>
                @php $dp = $this->getDepositsPayments(); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <x-reports.stat label="Deposit Ditahan" :value="$this->money($dp['deposit_held'])" />
                    <x-reports.stat label="Outstanding (invoice)" :value="$this->money($dp['outstanding'])" />
                    <x-reports.stat label="Sudah Ditagih" :value="$dp['invoiced_count']" />
                    <x-reports.stat label="Belum Ditagih" :value="$dp['uninvoiced_count']" />
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <x-filament::section>
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">Status Security Deposit</h4>
                        <x-reports.table :head="['Status', 'Jumlah', 'Nilai']">
                            @foreach ($dp['deposit_buckets'] as $status => $b)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-2 px-2">{{ ucfirst($status) }}</td>
                                    <td class="py-2 px-2 text-right">{{ $b['count'] }}</td>
                                    <td class="py-2 px-2 text-right">{{ $this->money($b['amount']) }}</td>
                                </tr>
                            @endforeach
                        </x-reports.table>
                    </x-filament::section>
                    <x-filament::section>
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">Status Down Payment</h4>
                        <x-reports.table :head="['Status', 'Jumlah', 'Nilai']">
                            @foreach ($dp['dp_buckets'] as $status => $b)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-2 px-2">{{ ucfirst($status) }}</td>
                                    <td class="py-2 px-2 text-right">{{ $b['count'] }}</td>
                                    <td class="py-2 px-2 text-right">{{ $this->money($b['amount']) }}</td>
                                </tr>
                            @endforeach
                        </x-reports.table>
                    </x-filament::section>
                </div>
            </div>

            {{-- Duration & conversion --}}
            <div x-show="rentalSub === 'duration'" x-cloak>
                @php $dc = $this->getDurationConversion(); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <x-reports.stat label="Rata-rata Durasi" :value="$dc['avg_days'].' hari'" />
                    <x-reports.stat label="Konversi" :value="$dc['conversion_rate'].'%'" />
                    <x-reports.stat label="Selesai" :value="$dc['completion_rate'].'%'" />
                    <x-reports.stat label="Batal/Expired" :value="$dc['lost_ratio'].'%'" />
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <x-filament::section>
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">Distribusi Durasi (hari)</h4>
                        <x-reports.table :head="['Rentang', 'Jumlah']">
                            @foreach ($dc['distribution'] as $range => $count)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-2 px-2">{{ $range }}</td>
                                    <td class="py-2 px-2 text-right">{{ $count }}</td>
                                </tr>
                            @endforeach
                        </x-reports.table>
                    </x-filament::section>
                    <x-filament::section>
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">Funnel Konversi</h4>
                        <x-reports.table :head="['Tahap', 'Jumlah']">
                            <tr class="border-t border-gray-100 dark:border-gray-700"><td class="py-2 px-2">Penawaran (dibuat)</td><td class="py-2 px-2 text-right">{{ $dc['funnel']['quotation'] }}</td></tr>
                            <tr class="border-t border-gray-100 dark:border-gray-700"><td class="py-2 px-2">Terkonfirmasi</td><td class="py-2 px-2 text-right">{{ $dc['funnel']['confirmed'] }}</td></tr>
                            <tr class="border-t border-gray-100 dark:border-gray-700"><td class="py-2 px-2">Selesai</td><td class="py-2 px-2 text-right">{{ $dc['funnel']['completed'] }}</td></tr>
                        </x-reports.table>
                    </x-filament::section>
                </div>
            </div>

            {{-- Logistics --}}
            <div x-show="rentalSub === 'logistics'" x-cloak>
                @php $log = $this->getLogistics(); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <x-reports.stat label="SJ Keluar" :value="$log['out_count']" />
                    <x-reports.stat label="SJ Masuk" :value="$log['in_count']" />
                    <x-reports.stat label="Item Bermasalah" :value="$log['issues']" />
                    <x-reports.stat label="Masih di Luar" :value="$log['still_out']" />
                </div>
                <x-filament::section>
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">Kondisi Barang Saat Kembali</h4>
                    <x-reports.table :head="['Kondisi', 'Jumlah']">
                        @forelse ($log['condition_distribution'] as $cond => $count)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ ucfirst($cond) }}</td>
                                <td class="py-2 px-2 text-right">{{ $count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-4 px-2 text-center text-gray-400">Belum ada data pengembalian.</td></tr>
                        @endforelse
                    </x-reports.table>
                </x-filament::section>
            </div>

            {{-- Revenue over time --}}
            <div x-show="rentalSub === 'revenue'" x-cloak>
                @php $rev = $this->getRevenueOverTime(); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <x-reports.stat label="Total Kotor" :value="$this->money($rev['total_gross'])" />
                    <x-reports.stat label="Total Bersih" :value="$this->money($rev['total_net'])" />
                    <x-reports.stat label="Total PPN" :value="$this->money($rev['total_ppn'])" />
                    <x-reports.stat label="Total PPh" :value="$this->money($rev['total_pph'])" />
                </div>
                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Per Bulan</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('revenue','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['Bulan', 'Kotor', 'Bersih', 'PPN', 'PPh']">
                        @forelse ($rev['rows'] as $m)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $m['month'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($m['gross']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($m['net']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($m['ppn']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($m['pph']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 px-2 text-center text-gray-400">Belum ada revenue di periode ini.</td></tr>
                        @endforelse
                    </x-reports.table>
                </x-filament::section>
            </div>
        </div>

        {{-- ============================ INVENTORY ============================ --}}
        <div x-show="tab === 'inventory'" x-cloak class="space-y-4">
            <div class="flex flex-wrap items-center gap-2 justify-between">
                <div class="flex flex-wrap gap-2">
                    @foreach (['stock' => 'Status & Stok', 'utilization' => 'Utilisasi', 'maintenance' => 'Maintenance', 'depreciation' => 'Depresiasi'] as $key => $label)
                        <button @click="invSub = '{{ $key }}'"
                            :class="invSub === '{{ $key }}' ? 'bg-primary-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'"
                            class="rounded-lg px-3 py-1.5 text-xs font-medium">{{ $label }}</button>
                    @endforeach
                </div>
                <input type="text" wire:model.live.debounce.400ms="inventorySearch" placeholder="Cari unit / serial…"
                    class="fi-input rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm"
                    x-show="invSub !== 'stock'" x-cloak />
            </div>

            {{-- Stock status --}}
            <div x-show="invSub === 'stock'" x-cloak>
                @php $stock = $this->getStockStatus(); $labels = \App\Models\ProductUnit::getStatusOptions(); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
                    <x-reports.stat label="Total Unit" :value="$stock['total_units']" />
                    @foreach ($stock['totals'] as $status => $count)
                        <x-reports.stat :label="$labels[$status] ?? ucfirst($status)" :value="$count" />
                    @endforeach
                </div>
                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Per Produk</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('stock','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['Produk', 'Total', 'Tersedia', 'Disewa', 'Terjadwal', 'Maint.', 'Pensiun']">
                        @foreach ($stock['by_product'] as $p)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $p['product'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $p['total'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $p['available'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $p['rented'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $p['scheduled'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $p['maintenance'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $p['retired'] }}</td>
                            </tr>
                        @endforeach
                    </x-reports.table>
                </x-filament::section>
            </div>

            {{-- Utilization --}}
            <div x-show="invSub === 'utilization'" x-cloak>
                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Utilisasi Unit</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('utilization','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['Unit', 'Hari Tersewa', 'Utilisasi', 'Pendapatan']">
                        @foreach ($this->getUtilizationRows() as $u)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $u['name'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $u['days_rented'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $u['utilization_rate'] }}%</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($u['period_revenue']) }}</td>
                            </tr>
                        @endforeach
                    </x-reports.table>
                </x-filament::section>
            </div>

            {{-- Maintenance --}}
            <div x-show="invSub === 'maintenance'" x-cloak>
                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Maintenance & Kerusakan</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('maintenance','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['Unit', 'Frekuensi', 'Biaya Periode', 'Biaya Total', 'Profitabilitas']">
                        @forelse ($this->getMaintenanceRows() as $u)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $u['name'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $u['maintenance_freq'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($u['period_maintenance']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($u['lifetime_maintenance']) }}</td>
                                <td class="py-2 px-2 text-right {{ $u['profitability'] < 0 ? 'text-danger-600' : '' }}">{{ $this->money($u['profitability']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 px-2 text-center text-gray-400">Tidak ada maintenance di periode ini.</td></tr>
                        @endforelse
                    </x-reports.table>
                </x-filament::section>
                @php $lost = $this->getLostDamaged(); @endphp
                @if ($lost->count())
                    <x-filament::section class="mt-4">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">Unit Hilang / Rusak (Pensiun)</h4>
                        <x-reports.table :head="['Unit', 'Kondisi', 'Dilaporkan', 'Harga Beli', 'Kerugian (Nilai Buku)']">
                            @foreach ($lost as $l)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-2 px-2">{{ $l['name'] }}</td>
                                    <td class="py-2 px-2">{{ $l['condition'] }}</td>
                                    <td class="py-2 px-2">{{ $l['date_reported'] }}</td>
                                    <td class="py-2 px-2 text-right">{{ $this->money($l['purchase_price']) }}</td>
                                    <td class="py-2 px-2 text-right text-danger-600">{{ $this->money($l['book_value_loss']) }}</td>
                                </tr>
                            @endforeach
                        </x-reports.table>
                    </x-filament::section>
                @endif
            </div>

            {{-- Depreciation --}}
            <div x-show="invSub === 'depreciation'" x-cloak>
                @php $dep = $this->getDepreciationTotals(); @endphp
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                    <x-reports.stat label="Total Harga Beli" :value="$this->money($dep['total_cost'])" />
                    <x-reports.stat label="Akumulasi Depresiasi" :value="$this->money($dep['accumulated_depreciation'])" />
                    <x-reports.stat label="Total Nilai Buku" :value="$this->money($dep['total_book_value'])" />
                    <x-reports.stat label="Unit Aktif" :value="$dep['unit_count']" />
                </div>
                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Depresiasi per Unit</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('depreciation','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['Unit', 'Harga Beli', 'Akumulasi', 'Nilai Buku', 'Residu']">
                        @foreach ($this->getDepreciationRows() as $u)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $u['name'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($u['purchase_price']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($u['accumulated_depreciation']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($u['book_value']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($u['residual_value']) }}</td>
                            </tr>
                        @endforeach
                    </x-reports.table>
                </x-filament::section>
            </div>
        </div>

        {{-- ============================ FINANCE ============================ --}}
        <div x-show="tab === 'finance'" x-cloak class="space-y-4">
            @php $fk = $this->getFinanceKpis(); @endphp
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                <x-reports.stat label="Revenue Rental (bersih)" :value="$this->money($fk['rental_net'])" />
                <x-reports.stat label="Piutang Outstanding (AR)" :value="$this->money($fk['ar_outstanding'])" />
                <x-reports.stat label="Deposit Ditahan" :value="$this->money($fk['deposit_held'])" />
                <x-reports.stat label="Pemasukan (invoice)" :value="$this->money($fk['income'])" />
                <x-reports.stat label="Pengeluaran (bill+expense)" :value="$this->money($fk['expense'])" />
                <x-reports.stat label="Selisih" :value="$this->money($fk['net'])" />
            </div>
            <x-filament::section>
                <h4 class="font-medium text-gray-900 dark:text-white mb-1">Laporan Keuangan Lengkap</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Laporan finance detail tersedia di modul Finance agar tidak terduplikasi. Buka:</p>
                <div class="flex flex-col gap-2">
                    @foreach ($this->getFinanceLinks() as $link)
                        <a href="{{ $link['url'] }}" class="flex items-center gap-3 rounded-lg p-3 ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                            <x-filament::icon :icon="$link['icon']" class="h-5 w-5 text-primary-500" />
                            <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $link['label'] }}</span>
                            <span class="ml-auto text-primary-500">→</span>
                        </a>
                    @endforeach
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
