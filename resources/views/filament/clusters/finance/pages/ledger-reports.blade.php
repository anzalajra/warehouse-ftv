<x-filament-panels::page>
    @php
        $tb = $this->trialBalance;
        $is = $this->incomeStatement;
        $bs = $this->balanceSheet;
        $fmt = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    @endphp

    {{-- Period filter --}}
    <form wire:submit.prevent class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Dari Tanggal</label>
                <input type="date" wire:model.live="startDate"
                    class="mt-1 rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700" />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Sampai Tanggal</label>
                <input type="date" wire:model.live="endDate"
                    class="mt-1 rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700" />
            </div>
            <div class="ml-auto">
                <x-filament::button wire:click="exportStatementsPdf" icon="heroicon-o-document-arrow-down" color="gray" size="sm">
                    Export PDF (Neraca & L/R)
                </x-filament::button>
            </div>
            <p class="w-full text-xs text-gray-400">Laba Rugi untuk rentang periode. Neraca & Neraca Saldo kumulatif s.d. "Sampai Tanggal".</p>
        </div>
    </form>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Income Statement --}}
        <div class="fi-section rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Laporan Laba Rugi</h3>

            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Pendapatan</p>
            <table class="w-full text-sm">
                @forelse ($is['revenue'] as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-1 text-gray-600 dark:text-gray-300">{{ $row['code'] }} · {{ $row['name'] }}</td>
                        <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($row['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-1 text-gray-400" colspan="2">Belum ada pendapatan.</td></tr>
                @endforelse
                <tr class="font-semibold">
                    <td class="py-1">Total Pendapatan</td>
                    <td class="py-1 text-right tabular-nums text-success-600">{{ $fmt($is['total_revenue']) }}</td>
                </tr>
            </table>

            <p class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400">Beban</p>
            <table class="w-full text-sm">
                @forelse ($is['expense'] as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-1 text-gray-600 dark:text-gray-300">{{ $row['code'] }} · {{ $row['name'] }}</td>
                        <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($row['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-1 text-gray-400" colspan="2">Belum ada beban.</td></tr>
                @endforelse
                <tr class="font-semibold">
                    <td class="py-1">Total Beban</td>
                    <td class="py-1 text-right tabular-nums text-danger-600">{{ $fmt($is['total_expense']) }}</td>
                </tr>
            </table>

            <div class="mt-4 flex justify-between border-t-2 border-gray-200 pt-2 text-base font-bold dark:border-gray-700">
                <span>Laba / (Rugi) Bersih</span>
                <span class="tabular-nums {{ $is['net_income'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">{{ $fmt($is['net_income']) }}</span>
            </div>
        </div>

        {{-- Balance Sheet --}}
        <div class="fi-section rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Neraca</h3>
                @if ($bs['balanced'])
                    <span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-400/10 dark:text-success-400">Balance ✓</span>
                @else
                    <span class="rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">Selisih {{ $fmt($bs['difference']) }}</span>
                @endif
            </div>

            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Aset</p>
            <table class="w-full text-sm">
                @forelse ($bs['assets'] as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-1 text-gray-600 dark:text-gray-300">{{ $row['code'] }} · {{ $row['name'] }}</td>
                        <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($row['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-1 text-gray-400" colspan="2">—</td></tr>
                @endforelse
                <tr class="font-semibold">
                    <td class="py-1">Total Aset</td>
                    <td class="py-1 text-right tabular-nums">{{ $fmt($bs['total_assets']) }}</td>
                </tr>
            </table>

            <p class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400">Liabilitas</p>
            <table class="w-full text-sm">
                @forelse ($bs['liabilities'] as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-1 text-gray-600 dark:text-gray-300">{{ $row['code'] }} · {{ $row['name'] }}</td>
                        <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($row['amount']) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-1 text-gray-400" colspan="2">—</td></tr>
                @endforelse
                <tr class="font-semibold">
                    <td class="py-1">Total Liabilitas</td>
                    <td class="py-1 text-right tabular-nums">{{ $fmt($bs['total_liabilities']) }}</td>
                </tr>
            </table>

            <p class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400">Ekuitas</p>
            <table class="w-full text-sm">
                @foreach ($bs['equity'] as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-1 text-gray-600 dark:text-gray-300">{{ $row['code'] }} · {{ $row['name'] }}</td>
                        <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($row['amount']) }}</td>
                    </tr>
                @endforeach
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <td class="py-1 text-gray-600 dark:text-gray-300">Laba / (Rugi) Berjalan</td>
                    <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($bs['net_income']) }}</td>
                </tr>
                <tr class="font-semibold">
                    <td class="py-1">Total Ekuitas</td>
                    <td class="py-1 text-right tabular-nums">{{ $fmt($bs['total_equity']) }}</td>
                </tr>
            </table>

            <div class="mt-4 flex justify-between border-t-2 border-gray-200 pt-2 text-base font-bold dark:border-gray-700">
                <span>Total Liabilitas + Ekuitas</span>
                <span class="tabular-nums">{{ $fmt($bs['total_liabilities'] + $bs['total_equity']) }}</span>
            </div>
        </div>
    </div>

    {{-- Trial Balance --}}
    <div class="fi-section rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Neraca Saldo (Trial Balance)</h3>
            @if ($tb['balanced'])
                <span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-400/10 dark:text-success-400">Balance ✓</span>
            @else
                <span class="rounded-full bg-danger-50 px-2 py-0.5 text-xs font-medium text-danger-700 dark:bg-danger-400/10 dark:text-danger-400">Tidak balance!</span>
            @endif
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-xs uppercase text-gray-400 dark:border-gray-700">
                    <th class="py-2 text-left font-medium">Akun</th>
                    <th class="py-2 text-right font-medium">Debit</th>
                    <th class="py-2 text-right font-medium">Kredit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tb['rows'] as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="py-1 text-gray-600 dark:text-gray-300">{{ $row['code'] }} · {{ $row['name'] }}</td>
                        <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $row['debit'] > 0 ? $fmt($row['debit']) : '' }}</td>
                        <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $row['credit'] > 0 ? $fmt($row['credit']) : '' }}</td>
                    </tr>
                @empty
                    <tr><td class="py-2 text-gray-400" colspan="3">Belum ada jurnal pada periode ini.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-200 font-bold dark:border-gray-700">
                    <td class="py-2">Total</td>
                    <td class="py-2 text-right tabular-nums">{{ $fmt($tb['total_debit']) }}</td>
                    <td class="py-2 text-right tabular-nums">{{ $fmt($tb['total_credit']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- General Ledger (Buku Besar) drill-down --}}
    <div class="fi-section rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Buku Besar (General Ledger)</h3>
            <select wire:model.live="ledgerAccountId"
                class="rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700">
                <option value="">— Pilih akun —</option>
                @foreach ($this->accountOptions() as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @php $gl = $this->generalLedger; @endphp
        @if ($gl && $gl['account'])
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-xs uppercase text-gray-400 dark:border-gray-700">
                        <th class="py-2 text-left font-medium">Tanggal</th>
                        <th class="py-2 text-left font-medium">No Jurnal</th>
                        <th class="py-2 text-left font-medium">Keterangan</th>
                        <th class="py-2 text-right font-medium">Debit</th>
                        <th class="py-2 text-right font-medium">Kredit</th>
                        <th class="py-2 text-right font-medium">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-gray-100 italic text-gray-500 dark:border-gray-800">
                        <td class="py-1" colspan="5">Saldo Awal</td>
                        <td class="py-1 text-right tabular-nums">{{ $fmt($gl['opening']) }}</td>
                    </tr>
                    @forelse ($gl['rows'] as $row)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-1 text-gray-500">{{ \Illuminate\Support\Carbon::parse($row['date'])->toDateString() }}</td>
                            <td class="py-1 text-gray-600 dark:text-gray-300">{{ $row['ref'] }}</td>
                            <td class="py-1 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($row['description'], 60) }}</td>
                            <td class="py-1 text-right tabular-nums">{{ $row['debit'] > 0 ? $fmt($row['debit']) : '' }}</td>
                            <td class="py-1 text-right tabular-nums">{{ $row['credit'] > 0 ? $fmt($row['credit']) : '' }}</td>
                            <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($row['balance']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-2 text-gray-400" colspan="6">Tidak ada mutasi pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 font-bold dark:border-gray-700">
                        <td class="py-2" colspan="3">Saldo Akhir ({{ $gl['account']['code'] }})</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($gl['total_debit']) }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($gl['total_credit']) }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($gl['closing']) }}</td>
                    </tr>
                </tfoot>
            </table>
        @else
            <p class="text-sm text-gray-400">Pilih akun untuk melihat mutasi & saldo berjalan.</p>
        @endif
    </div>

    {{-- AR / AP Aging --}}
    @php
        $buckets = \App\Services\AgingReportService::BUCKETS;
        $bucketLabels = \App\Services\AgingReportService::BUCKET_LABELS;
    @endphp
    @foreach (['Umur Piutang (AR Aging)' => $this->arAging, 'Umur Hutang (AP Aging)' => $this->apAging] as $title => $aging)
        <div class="fi-section rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">{{ $title }}</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs uppercase text-gray-400 dark:border-gray-700">
                            <th class="py-2 text-left font-medium">No</th>
                            <th class="py-2 text-left font-medium">Pihak</th>
                            <th class="py-2 text-left font-medium">Jatuh Tempo</th>
                            @foreach ($buckets as $b)
                                <th class="py-2 text-right font-medium">{{ $bucketLabels[$b] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($aging['rows'] as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-1 text-gray-700 dark:text-gray-200">{{ $row['number'] }}</td>
                                <td class="py-1 text-gray-600 dark:text-gray-300">{{ $row['party'] }}</td>
                                <td class="py-1 text-gray-500">{{ $row['due_date'] ?? '—' }}</td>
                                @foreach ($buckets as $b)
                                    <td class="py-1 text-right tabular-nums {{ $b === 'over_90' ? 'text-danger-600' : 'text-gray-900 dark:text-white' }}">
                                        {{ $row['bucket'] === $b ? $fmt($row['balance']) : '' }}
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td class="py-2 text-gray-400" colspan="{{ 3 + count($buckets) }}">Tidak ada saldo terbuka.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-200 font-bold dark:border-gray-700">
                            <td class="py-2" colspan="3">Total ({{ $fmt($aging['grand_total']) }})</td>
                            @foreach ($buckets as $b)
                                <td class="py-2 text-right tabular-nums">{{ $fmt($aging['totals'][$b]) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endforeach

    {{-- PPN Keluaran recap (Faktur Pajak / e-Faktur precursor) --}}
    @php $tax = $this->taxRecap; @endphp
    <div class="fi-section rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Rekap PPN Keluaran</h3>
            <x-filament::button wire:click="exportTaxCsv" icon="heroicon-o-arrow-down-tray" color="gray" size="sm">
                Export CSV
            </x-filament::button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-xs uppercase text-gray-400 dark:border-gray-700">
                        <th class="py-2 text-left font-medium">Tanggal</th>
                        <th class="py-2 text-left font-medium">No Invoice</th>
                        <th class="py-2 text-left font-medium">No Faktur Pajak</th>
                        <th class="py-2 text-left font-medium">Pelanggan</th>
                        <th class="py-2 text-right font-medium">DPP</th>
                        <th class="py-2 text-right font-medium">PPN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tax['lines'] as $line)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-1 text-gray-500">{{ $line['date'] }}</td>
                            <td class="py-1 text-gray-700 dark:text-gray-200">{{ $line['invoice_number'] }}</td>
                            <td class="py-1 text-gray-500">{{ $line['tax_invoice_number'] ?: '—' }}</td>
                            <td class="py-1 text-gray-600 dark:text-gray-300">{{ $line['customer'] }}</td>
                            <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($line['dpp']) }}</td>
                            <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($line['ppn']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-2 text-gray-400" colspan="6">Tidak ada faktur kena pajak pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 font-bold dark:border-gray-700">
                        <td class="py-2" colspan="4">Total</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($tax['total_dpp']) }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($tax['total_ppn']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- PPh 23 withheld recap (kredit pajak) --}}
    @php $pph = $this->pph23Recap; @endphp
    <div class="fi-section rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Rekap PPh 23 Dipotong (Kredit Pajak)</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-xs uppercase text-gray-400 dark:border-gray-700">
                        <th class="py-2 text-left font-medium">Tanggal</th>
                        <th class="py-2 text-left font-medium">No Invoice</th>
                        <th class="py-2 text-left font-medium">No Bukti Potong</th>
                        <th class="py-2 text-left font-medium">Pelanggan</th>
                        <th class="py-2 text-right font-medium">DPP</th>
                        <th class="py-2 text-right font-medium">PPh 23</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pph['lines'] as $line)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-1 text-gray-500">{{ $line['date'] }}</td>
                            <td class="py-1 text-gray-700 dark:text-gray-200">{{ $line['invoice_number'] }}</td>
                            <td class="py-1 text-gray-500">{{ $line['bukti_potong'] ?: '—' }}</td>
                            <td class="py-1 text-gray-600 dark:text-gray-300">{{ $line['customer'] }}</td>
                            <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($line['dpp']) }}</td>
                            <td class="py-1 text-right tabular-nums text-gray-900 dark:text-white">{{ $fmt($line['pph23']) }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-2 text-gray-400" colspan="6">Tidak ada PPh 23 dipotong pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-200 font-bold dark:border-gray-700">
                        <td class="py-2" colspan="4">Total</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($pph['total_dpp']) }}</td>
                        <td class="py-2 text-right tabular-nums">{{ $fmt($pph['total_pph23']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-filament-panels::page>
