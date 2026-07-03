<x-filament-panels::page>
    @php
        // Only compute recommendations on their own tab — they touch every aggregate,
        // so eagerly running them for the badge on other tabs would negate the lazy
        // per-tab rendering below.
        $recTotal = $mainTab === 'recommendations' ? count($this->getRecommendations()) : null;
        $priorityBadge = ['high' => 'danger', 'medium' => 'warning', 'low' => 'gray'];
        $priorityLabel = ['high' => 'Tinggi', 'medium' => 'Sedang', 'low' => 'Rendah'];
    @endphp

    <div x-data="{ rentalSub: 'summary', invSub: 'stock' }" class="space-y-5">

        {{-- Date range filter (preset buttons + custom range) --}}
        <x-filament::section>
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    @foreach ([
                        'this_month' => 'Bulan Ini',
                        'last_month' => 'Bulan Lalu',
                        '3_month' => '3 Bulan',
                        '6_month' => '6 Bulan',
                        'yearly' => 'Tahun Ini',
                        'all_time' => 'Semua Waktu',
                        'custom' => 'Kustom',
                    ] as $key => $label)
                        <button type="button" wire:click="setPreset('{{ $key }}')" wire:loading.attr="disabled"
                            @class([
                                'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                                'bg-primary-600 text-white' => $datePreset === $key,
                                'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' => $datePreset !== $key,
                            ])>{{ $label }}</button>
                    @endforeach
                </div>

                @if ($datePreset === 'custom')
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
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
                @endif

                <div class="flex items-center gap-3 text-xs">
                    <span class="text-gray-500 dark:text-gray-400" wire:loading.remove wire:target="startDate,endDate,mainTab,datePreset,setPreset">
                        Periode: {{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} – {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}
                    </span>
                    <span class="text-primary-600 dark:text-primary-400" wire:loading wire:target="startDate,endDate,mainTab,datePreset,setPreset">Memuat…</span>
                </div>
            </div>
        </x-filament::section>

        {{-- Main tabs (server-driven so only the active tab's data is computed) --}}
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex gap-6 overflow-x-auto" aria-label="Tabs">
                @foreach ([
                    'recommendations' => 'Rekomendasi',
                    'rental' => 'Rental',
                    'inventory' => 'Inventory',
                    'finance' => 'Finance',
                ] as $key => $label)
                    <button wire:click="$set('mainTab', '{{ $key }}')" wire:loading.attr="disabled"
                        @class([
                            'whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium transition-colors flex items-center gap-2',
                            'border-primary-500 text-primary-600 dark:border-primary-400 dark:text-primary-400' => $mainTab === $key,
                            'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400' => $mainTab !== $key,
                        ])>
                        {{ $label }}
                        @if ($key === 'recommendations' && $recTotal > 0)
                            <span class="inline-flex items-center justify-center rounded-full bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300 text-xs font-semibold h-5 min-w-5 px-1.5">{{ $recTotal }}</span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- loading shimmer while a tab is fetched --}}
        <div wire:loading.flex wire:target="startDate,endDate,mainTab,setPreset" class="items-center justify-center py-8 text-sm text-primary-600 dark:text-primary-400">
            <x-filament::loading-indicator class="h-6 w-6 mr-2" /> Memuat data…
        </div>

        <div wire:loading.remove wire:target="startDate,endDate,mainTab,setPreset">

        {{-- ============================ RECOMMENDATIONS ============================ --}}
        @if ($mainTab === 'recommendations')
        <div x-data="{ showAll: false }" class="space-y-4">
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
                    @foreach ($recs as $i => $rec)
                        <div
                            @if ($i >= 6) x-show="showAll" x-cloak @endif
                            @class([
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

                @if (count($recs) > 6)
                    <div class="text-center pt-1">
                        <button type="button" x-show="!showAll" @click="showAll = true"
                            class="inline-flex items-center gap-1 rounded-lg px-4 py-2 text-sm font-medium text-primary-600 dark:text-primary-400 ring-1 ring-primary-200 dark:ring-primary-500/40 hover:bg-primary-50 dark:hover:bg-primary-500/10">
                            Lihat {{ count($recs) - 6 }} rekomendasi lainnya
                        </button>
                        <button type="button" x-show="showAll" x-cloak @click="showAll = false"
                            class="inline-flex items-center gap-1 rounded-lg px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-gray-800">
                            Tampilkan lebih sedikit
                        </button>
                    </div>
                @endif
            @endif
        </div>
        @endif

        {{-- ============================ RENTAL ============================ --}}
        @if ($mainTab === 'rental')
        <div class="space-y-4">
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
                @php $customers = $this->getTopCustomers(); @endphp
                <x-filament::section>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Top Pelanggan <span class="text-xs font-normal text-gray-400">({{ $customers->total() }})</span></h4>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Urut:</span>
                            @foreach (['total' => 'Total', 'count' => 'Jumlah Rental', 'avg' => 'Rata-rata'] as $key => $label)
                                <button type="button" wire:click="$set('custSort', '{{ $key }}')"
                                    @class([
                                        'rounded-md px-2.5 py-1 text-xs font-medium',
                                        'bg-primary-600 text-white' => $custSort === $key,
                                        'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' => $custSort !== $key,
                                    ])>{{ $label }}</button>
                            @endforeach
                            <x-filament::button size="xs" color="gray" wire:click="export('top_customers','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                        </div>
                    </div>
                    <x-reports.table :head="['#', 'Pelanggan', 'Jumlah Rental', 'Total', 'Rata-rata']">
                        @forelse ($customers as $i => $c)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $customers->firstItem() + $i }}</td>
                                <td class="py-2 px-2">{{ $c['name'] }}<div class="text-xs text-gray-400">{{ $c['email'] }}</div></td>
                                <td class="py-2 px-2 text-right">{{ $c['rental_count'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($c['total_value']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($c['avg_value']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 px-2 text-center text-gray-400">Belum ada data pelanggan di periode ini.</td></tr>
                        @endforelse
                    </x-reports.table>
                    @if ($customers->hasPages())
                        <div class="mt-3">{{ $customers->links() }}</div>
                    @endif
                </x-filament::section>
            </div>

            {{-- Top products --}}
            <div x-show="rentalSub === 'products'" x-cloak>
                @php $products = $this->getTopProducts(); @endphp
                <x-filament::section>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Top Produk <span class="text-xs font-normal text-gray-400">({{ $products->total() }})</span></h4>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Urut:</span>
                            @foreach (['revenue' => 'Pendapatan', 'count' => 'Total Sewa', 'days' => 'Total Hari'] as $key => $label)
                                <button type="button" wire:click="$set('prodSort', '{{ $key }}')"
                                    @class([
                                        'rounded-md px-2.5 py-1 text-xs font-medium',
                                        'bg-primary-600 text-white' => $prodSort === $key,
                                        'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' => $prodSort !== $key,
                                    ])>{{ $label }}</button>
                            @endforeach
                            <x-filament::button size="xs" color="gray" wire:click="export('top_products','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                        </div>
                    </div>
                    <x-reports.table :head="['#', 'Produk', 'Rental', 'Total Hari', 'Pendapatan']">
                        @forelse ($products as $i => $p)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $products->firstItem() + $i }}</td>
                                <td class="py-2 px-2">{{ $p['name'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $p['line_count'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $p['unit_days'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($p['revenue']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 px-2 text-center text-gray-400">Belum ada data produk di periode ini.</td></tr>
                        @endforelse
                    </x-reports.table>
                    @if ($products->hasPages())
                        <div class="mt-3">{{ $products->links() }}</div>
                    @endif
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
        @endif

        {{-- ============================ INVENTORY ============================ --}}
        @if ($mainTab === 'inventory')
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2 justify-between">
                <div class="flex flex-wrap gap-2">
                    @foreach (['stock' => 'Status & Stok', 'performance' => 'Performa Produk', 'utilization' => 'Utilisasi', 'maintenance' => 'Maintenance', 'depreciation' => 'Depresiasi'] as $key => $label)
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

            {{-- Product Performance (utilization & revenue per product / per unit) --}}
            <div x-show="invSub === 'performance'" x-cloak x-data="{ perfView: 'top' }" class="space-y-4">
                @php
                    $pk = $this->getPerformanceKpis();
                    $underThreshold = \App\Services\InventoryReportService::UNDERPERFORMING_UTILIZATION;
                @endphp

                {{-- KPI headline --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                    <div class="rounded-xl p-4 ring-1 ring-gray-200 dark:ring-white/10 bg-white dark:bg-gray-800">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Revenue Tertinggi</div>
                        @if ($pk['top_revenue'] && $pk['top_revenue']['period_revenue'] > 0)
                            <div class="mt-1 font-semibold text-gray-900 dark:text-white truncate">{{ $pk['top_revenue']['product'] }}</div>
                            <div class="text-sm text-primary-600 dark:text-primary-400">{{ $this->money($pk['top_revenue']['period_revenue']) }}</div>
                        @else
                            <div class="mt-1 text-sm text-gray-400">—</div>
                        @endif
                    </div>
                    <div class="rounded-xl p-4 ring-1 ring-gray-200 dark:ring-white/10 bg-white dark:bg-gray-800">
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Paling Sering Disewa</div>
                        @if ($pk['top_rented'] && $pk['top_rented']['rental_count'] > 0)
                            <div class="mt-1 font-semibold text-gray-900 dark:text-white truncate">{{ $pk['top_rented']['product'] }}</div>
                            <div class="text-sm text-primary-600 dark:text-primary-400">{{ $pk['top_rented']['rental_count'] }}× sewa</div>
                        @else
                            <div class="mt-1 text-sm text-gray-400">—</div>
                        @endif
                    </div>
                    <div @class([
                        'rounded-xl p-4 ring-1 bg-white dark:bg-gray-800',
                        'ring-danger-300 dark:ring-danger-500/40' => $pk['underperformer_count'] > 0,
                        'ring-gray-200 dark:ring-white/10' => $pk['underperformer_count'] === 0,
                    ])>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Produk Underperforming</div>
                        <div class="mt-1 font-semibold {{ $pk['underperformer_count'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">{{ $pk['underperformer_count'] }} produk</div>
                        <div class="text-xs text-gray-400">Utilisasi &lt; {{ rtrim(rtrim(number_format($underThreshold, 1), '0'), '.') }}%</div>
                    </div>
                </div>

                <x-filament::section>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <div class="flex items-center gap-3">
                            <h4 class="font-medium text-gray-900 dark:text-white">Performa per Produk</h4>
                            <div class="inline-flex rounded-lg ring-1 ring-gray-200 dark:ring-white/10 overflow-hidden text-xs font-medium">
                                <button type="button" @click="perfView='top'"
                                    :class="perfView==='top' ? 'bg-primary-600 text-white' : 'bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300'"
                                    class="px-2.5 py-1">Top performer</button>
                                <button type="button" @click="perfView='under'"
                                    :class="perfView==='under' ? 'bg-danger-600 text-white' : 'bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300'"
                                    class="px-2.5 py-1">Underperformer</button>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Urut:</span>
                            @foreach (['revenue' => 'Pendapatan', 'utilization' => 'Utilisasi', 'rental_count' => 'Jumlah Sewa', 'roi' => 'ROI'] as $key => $label)
                                <button type="button" wire:click="$set('perfSort', '{{ $key }}')"
                                    @class([
                                        'rounded-md px-2.5 py-1 text-xs font-medium',
                                        'bg-primary-600 text-white' => $perfSort === $key,
                                        'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' => $perfSort !== $key,
                                    ])>{{ $label }}</button>
                            @endforeach
                            <x-filament::button size="xs" color="gray" wire:click="export('performance_products','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                        </div>
                    </div>
                    <x-reports.table :head="['Produk', '#Sewa', 'Pendapatan', 'Pend./Unit', 'Utilisasi', 'ROI', 'Idle']">
                        @forelse ($this->getPerformanceProducts() as $p)
                            <tr x-show="perfView === 'top' || {{ $p['is_underperforming'] ? 'true' : 'false' }}"
                                @class([
                                    'border-t border-gray-100 dark:border-gray-700',
                                    'bg-danger-50/40 dark:bg-danger-900/10' => $p['is_underperforming'],
                                ])>
                                <td class="py-2 px-2">
                                    {{ $p['product'] }}
                                    @if ($p['is_underperforming'])
                                        <span class="ml-1 inline-flex items-center rounded-full bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300 px-1.5 py-0.5 text-[10px] font-semibold align-middle">underperform</span>
                                    @endif
                                </td>
                                <td class="py-2 px-2 text-right">{{ $p['rental_count'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($p['period_revenue']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($p['revenue_per_unit']) }}</td>
                                <td class="py-2 px-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-16 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                            <div class="h-full rounded-full {{ $p['is_underperforming'] ? 'bg-danger-500' : 'bg-primary-500' }}" style="width: {{ min(100, max(0, $p['avg_utilization'])) }}%"></div>
                                        </div>
                                        <span class="text-xs tabular-nums w-10 text-right">{{ $p['avg_utilization'] }}%</span>
                                    </div>
                                </td>
                                <td class="py-2 px-2 text-right">{{ $p['avg_roi'] }}%</td>
                                <td class="py-2 px-2 text-right {{ $p['idle_units'] > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-400' }}">{{ $p['idle_units'] }}/{{ $p['unit_count'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-4 px-2 text-center text-gray-400">Belum ada data produk.</td></tr>
                        @endforelse
                        @if ($pk['product_count'] > 0 && $pk['underperformer_count'] === 0)
                            <tr x-show="perfView === 'under'" x-cloak><td colspan="7" class="py-4 px-2 text-center text-gray-400">Tidak ada produk underperforming di periode ini. 🎉</td></tr>
                        @endif
                    </x-reports.table>
                </x-filament::section>

                <x-filament::section>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-gray-900 dark:text-white">Performa per Unit</h4>
                        <x-filament::button size="xs" color="gray" wire:click="export('performance_units','csv')" icon="heroicon-m-arrow-down-tray">CSV</x-filament::button>
                    </div>
                    <x-reports.table :head="['Unit', 'Utilisasi', 'Hari Tersewa', 'Pendapatan Periode', 'Pendapatan Total', 'ROI']">
                        @forelse ($this->getPerformanceUnits() as $u)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 px-2">{{ $u['name'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $u['utilization_rate'] }}%</td>
                                <td class="py-2 px-2 text-right">{{ $u['days_rented'] }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($u['period_revenue']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $this->money($u['lifetime_revenue']) }}</td>
                                <td class="py-2 px-2 text-right">{{ $u['roi'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-4 px-2 text-center text-gray-400">Belum ada data unit.</td></tr>
                        @endforelse
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
        @endif

        {{-- ============================ FINANCE ============================ --}}
        @if ($mainTab === 'finance')
        <div class="space-y-4">
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
        @endif

        </div> {{-- /wire:loading.remove wrapper --}}
    </div>
</x-filament-panels::page>
