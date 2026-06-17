@php
    use App\Models\Quotation;

    $quotation = $this->getQuotationData();
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');

    $statusColors = [
        Quotation::STATUS_ON_QUOTE => 'warning',
        Quotation::STATUS_SENT => 'info',
        Quotation::STATUS_ACCEPTED => 'success',
    ];
    $statusColor = $statusColors[$quotation->status] ?? 'gray';
    $statusLabel = Quotation::getStatusOptions()[$quotation->status] ?? ucfirst($quotation->status);

    $discountLines = [];
    foreach ($quotation->rentals as $rental) {
        foreach ($rental->discountBreakdown() as $line) {
            $discountLines[$line['label']] = ($discountLines[$line['label']] ?? 0) + $line['amount'];
        }
    }
@endphp

<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ===== Summary card ===== --}}
        <x-filament::section class="lg:col-span-1">
            <x-slot name="heading">Quotation {{ $quotation->number }}</x-slot>

            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Customer</dt>
                    <dd class="font-medium text-right">{{ $quotation->customer?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Date</dt>
                    <dd class="text-right">{{ $quotation->date?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Valid until</dt>
                    <dd class="text-right">{{ $quotation->valid_until?->format('d M Y') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4 items-center">
                    <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="text-right">
                        <x-filament::badge :color="$statusColor">{{ $statusLabel }}</x-filament::badge>
                    </dd>
                </div>
            </dl>

            <div class="mt-5 rounded-xl border border-gray-200 dark:border-white/10 p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Total</div>
                <div class="mt-1 text-2xl font-bold">{{ $rp($quotation->total) }}</div>
            </div>
        </x-filament::section>

        {{-- ===== Items + totals ===== --}}
        <x-filament::section class="lg:col-span-2">
            <x-slot name="heading">Items</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-white/10">
                            <th class="py-2 pr-2">#</th>
                            <th class="py-2 pr-2">Product</th>
                            <th class="py-2 pr-2">Serial</th>
                            <th class="py-2 pr-2 text-right">Rate</th>
                            <th class="py-2 pr-2 text-right">Days</th>
                            <th class="py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quotation->rentals as $rental)
                            <tr class="bg-gray-50 dark:bg-white/5">
                                <td colspan="6" class="py-1.5 px-2 text-xs font-semibold text-gray-600 dark:text-gray-300">
                                    {{ $rental->rental_code }}
                                    @if($rental->start_date && $rental->end_date)
                                        · {{ $rental->start_date->format('d M Y') }} – {{ $rental->end_date->format('d M Y') }}
                                    @endif
                                </td>
                            </tr>
                            @forelse($rental->items as $i => $item)
                                @php
                                    $product = $item->productUnit?->product ?? $item->product;
                                    $variation = $item->productVariation?->name;
                                    $serial = $item->productUnit?->serial_number;
                                @endphp
                                <tr class="border-b border-gray-100 dark:border-white/5">
                                    <td class="py-2 pr-2 align-top text-gray-400">{{ $i + 1 }}</td>
                                    <td class="py-2 pr-2 align-top">
                                        {{ $product?->name ?? '—' }}{{ $variation ? ' - ' . $variation : '' }}
                                    </td>
                                    <td class="py-2 pr-2 align-top {{ $serial ? '' : 'text-gray-400 italic' }}">
                                        {{ $serial ?: '(unassigned)' }}
                                    </td>
                                    <td class="py-2 pr-2 align-top text-right">{{ $rp($item->daily_rate) }}</td>
                                    <td class="py-2 pr-2 align-top text-right">{{ $item->days }}</td>
                                    <td class="py-2 align-top text-right">{{ $rp($item->subtotal) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-2 text-gray-400 italic">No items.</td></tr>
                            @endforelse
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Totals --}}
            <div class="mt-4 flex justify-end">
                <dl class="w-full max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Subtotal</dt>
                        <dd>{{ $rp($quotation->subtotal) }}</dd>
                    </div>
                    @foreach($discountLines as $label => $amount)
                        @if($amount > 0)
                            <div class="flex justify-between text-danger-600 dark:text-danger-400">
                                <dt>{{ $label }}</dt>
                                <dd>− {{ $rp($amount) }}</dd>
                            </div>
                        @endif
                    @endforeach
                    <div class="flex justify-between border-t border-gray-200 dark:border-white/10 pt-2 font-semibold text-base">
                        <dt>Total</dt>
                        <dd>{{ $rp($quotation->total) }}</dd>
                    </div>
                </dl>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
