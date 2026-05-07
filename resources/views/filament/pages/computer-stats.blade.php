<x-filament-panels::page>
    @php
        $formatHours = fn ($s) => number_format($s / 3600, 1).' jam';
    @endphp

    <div class="flex flex-wrap gap-2 mb-4">
        @foreach (['7d' => '7 Hari', '30d' => '30 Hari', '90d' => '90 Hari', 'all' => 'Semua'] as $key => $label)
            <button wire:click="setPeriod('{{ $key }}')"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition
                           {{ $period === $key ? 'bg-primary-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white border rounded-xl p-5 shadow-sm">
            <p class="text-sm text-gray-500">Total Jam Pakai</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalHours }} <span class="text-base text-gray-500">jam</span></p>
        </div>
        <div class="bg-white border rounded-xl p-5 shadow-sm">
            <p class="text-sm text-gray-500">Total Sesi</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($sessionCount) }}</p>
        </div>
        <div class="bg-white border rounded-xl p-5 shadow-sm">
            <p class="text-sm text-gray-500">Unique User</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($uniqueUsers) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
        <div class="bg-white border rounded-xl p-5 shadow-sm">
            <h3 class="font-semibold mb-3">Komputer Paling Sering Dipakai</h3>
            @if($topComputers->isEmpty())
                <p class="text-sm text-gray-500">Belum ada data.</p>
            @else
                <div class="space-y-2">
                    @foreach($topComputers as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ $row->computer?->name ?? '—' }}</span>
                            <div class="flex items-center gap-3">
                                <span class="text-gray-500">{{ $row->session_count }} sesi</span>
                                <span class="font-mono text-gray-900">{{ $formatHours($row->total_seconds) }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white border rounded-xl p-5 shadow-sm">
            <h3 class="font-semibold mb-3">Status Booking</h3>
            @if(empty($statusBreakdown))
                <p class="text-sm text-gray-500">Belum ada data.</p>
            @else
                @php
                    $colors = [
                        'confirmed' => 'bg-blue-500',
                        'active' => 'bg-green-500',
                        'completed' => 'bg-emerald-500',
                        'cancelled' => 'bg-gray-400',
                        'no_show' => 'bg-red-500',
                        'overridden' => 'bg-amber-500',
                    ];
                    $total = array_sum($statusBreakdown);
                @endphp
                <div class="space-y-2">
                    @foreach($statusBreakdown as $status => $cnt)
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="capitalize">{{ str_replace('_', ' ', $status) }}</span>
                                <span class="text-gray-600">{{ $cnt }} ({{ round($cnt / max($total, 1) * 100) }}%)</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded h-2 mt-1">
                                <div class="{{ $colors[$status] ?? 'bg-gray-400' }} h-2 rounded" style="width: {{ round($cnt / max($total, 1) * 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white border rounded-xl p-5 shadow-sm mt-4">
        <h3 class="font-semibold mb-3">Top User (Total Jam Pakai)</h3>
        @if($topUsers->isEmpty())
            <p class="text-sm text-gray-500">Belum ada data.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium">#</th>
                            <th class="px-3 py-2 text-left font-medium">Nama</th>
                            <th class="px-3 py-2 text-left font-medium">Email</th>
                            <th class="px-3 py-2 text-right font-medium">Sesi</th>
                            <th class="px-3 py-2 text-right font-medium">Total Jam</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($topUsers as $i => $row)
                            <tr>
                                <td class="px-3 py-2 text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-3 py-2 font-medium">{{ $row->user?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $row->user?->email ?? '—' }}</td>
                                <td class="px-3 py-2 text-right">{{ $row->session_count }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ $formatHours($row->total_seconds) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if($dailyUsage->isNotEmpty())
        <div class="bg-white border rounded-xl p-5 shadow-sm mt-4">
            <h3 class="font-semibold mb-3">Penggunaan Harian</h3>
            @php
                $maxSec = max($dailyUsage->pluck('total_seconds')->toArray() ?: [1]);
            @endphp
            <div class="flex items-end gap-1 h-32">
                @foreach($dailyUsage as $d)
                    <div class="flex-1 bg-primary-500 hover:bg-primary-600 rounded-t transition relative group"
                         style="height: {{ max(2, round($d->total_seconds / $maxSec * 100)) }}%"
                         title="{{ $d->day }}: {{ $formatHours($d->total_seconds) }}">
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between text-xs text-gray-500 mt-2">
                <span>{{ $dailyUsage->first()->day ?? '' }}</span>
                <span>{{ $dailyUsage->last()->day ?? '' }}</span>
            </div>
        </div>
    @endif
</x-filament-panels::page>
