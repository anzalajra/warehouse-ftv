<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $fmt = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    @endphp

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Akun Kas/Bank</label>
            <select wire:model.live="financeAccountId"
                class="mt-1 rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-700">
                @foreach ($this->getAccountOptions() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-xs text-gray-500">Saldo Buku</p>
            <p class="mt-1 text-lg font-bold tabular-nums text-gray-900 dark:text-white">{{ $fmt($summary['book_balance']) }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-xs text-gray-500">Total Statement (net)</p>
            <p class="mt-1 text-lg font-bold tabular-nums text-gray-900 dark:text-white">{{ $fmt($summary['statement_total']) }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-xs text-gray-500">Cocok</p>
            <p class="mt-1 text-lg font-bold text-success-600">{{ $summary['matched_count'] }}</p>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-xs text-gray-500">Belum Cocok</p>
            <p class="mt-1 text-lg font-bold text-warning-600">{{ $summary['unmatched_count'] }} <span class="text-xs font-normal text-gray-400">({{ $fmt($summary['unmatched_total']) }})</span></p>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
