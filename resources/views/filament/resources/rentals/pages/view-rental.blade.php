<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Rental Information
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <tbody>
                    <tr class="border-b border-gray-200">
                        <td class="py-3 pr-6 font-medium text-gray-500" style="width: 15%;">Rental Code</td>
                        <td class="py-3 pr-6 font-semibold" style="width: 35%;">{{ $rental->rental_code }}</td>
                        <td class="py-3 pr-6 font-medium text-gray-500" style="width: 15%;">Start Date</td>
                        <td class="py-3 font-semibold" style="width: 35%;">{{ $rental->start_date->format('d M Y H:i') }}</td>
                    </tr>
                    <tr class="border-b border-gray-200">
                        <td class="py-3 pr-6 font-medium text-gray-500">Customer</td>
                        <td class="py-3 pr-6 font-semibold">
                            <span>{{ $rental->customer->name }}</span>
                            @if($rental->customer && $rental->customer->isRedNotice())
                                <x-filament::badge color="danger" class="ml-2">Red Notice</x-filament::badge>
                            @endif
                        </td>
                        <td class="py-3 pr-6 font-medium text-gray-500">End Date</td>
                        <td class="py-3 font-semibold">{{ $rental->end_date->format('d M Y H:i') }}</td>
                    </tr>
                    <tr class="border-b border-gray-200">
                        <td class="py-3 pr-6 font-medium text-gray-500">Phone</td>
                        <td class="py-3 pr-6 font-semibold">{{ $rental->customer->phone ?? '-' }}</td>
                        <td class="py-3 pr-6 font-medium text-gray-500">Returned Date</td>
                        <td class="py-3 font-semibold">{{ $rental->returned_date ? $rental->returned_date->format('d M Y H:i') : '-' }}</td>
                    </tr>
                    <tr class="border-b border-gray-200">
                        <td class="py-3 pr-6 font-medium text-gray-500">Status</td>
                        <td class="py-3 pr-6">
                            <x-filament::badge :color="\App\Models\Rental::getStatusColor($rental->getRealTimeStatus())">
                                {{ ucfirst(str_replace('_', ' ', $rental->getRealTimeStatus())) }}
                            </x-filament::badge>
                        </td>
                        <td class="py-3 pr-6 font-medium text-gray-500">Total</td>
                        <td class="py-3 font-semibold">Rp {{ number_format($rental->total, 0, ',', '.') }}</td>
                    </tr>
                    @if($rental->notes)
                    <tr class="border-b border-gray-200">
                        <td class="py-3 pr-6 font-medium text-gray-500">Notes</td>
                        <td class="py-3" colspan="3">{{ $rental->notes }}</td>
                    </tr>
                    @endif
                    @if($rental->status === 'cancelled' && $rental->cancel_reason)
                    <tr>
                        <td class="py-3 pr-6 font-medium text-gray-500">Cancel Reason</td>
                        <td class="py-3 text-danger-600" colspan="3">{{ $rental->cancel_reason }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Rental Items
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b-2 border-gray-200">
                        <th class="text-left py-3 pr-4 font-semibold">Product</th>
                        <th class="text-left py-3 pr-4 font-semibold">Serial Number</th>
                        <th class="text-left py-3 pr-4 font-semibold">Kits</th>
                        <th class="text-left py-3 pr-4 font-semibold">Days</th>
                        <th class="text-right py-3 font-semibold">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rental->items as $item)
                    <tr class="border-b border-gray-100">
                        <td class="py-3 pr-4">{{ $item->productUnit->product->name }}</td>
                        <td class="py-3 pr-4">{{ $item->productUnit->serial_number }}</td>
                        <td class="py-3 pr-4">{{ $item->rentalItemKits->count() }} kits</td>
                        <td class="py-3 pr-4">{{ $item->days }}</td>
                        <td class="py-3 text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-gray-300">
                        <td colspan="4" class="py-3 pr-4 text-right font-semibold text-gray-500">Subtotal:</td>
                        <td class="py-3 text-right font-semibold text-gray-500">Rp {{ number_format($rental->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @php
                        $baseDiscount = 0;
                        if ($rental->discount_type === 'percent') {
                            $baseDiscount = ($rental->subtotal ?? 0) * (($rental->discount ?? 0) / 100);
                        } else {
                            $baseDiscount = $rental->discount ?? 0;
                        }
                    @endphp
                    @if($baseDiscount > 0)
                    <tr>
                        <td colspan="4" class="py-3 pr-4 text-right font-medium text-gray-500">Kupon Diskon:</td>
                        <td class="py-3 text-right font-medium text-gray-500">- Rp {{ number_format($baseDiscount, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($rental->daily_discount_amount > 0)
                    <tr>
                        <td colspan="4" class="py-3 pr-4 text-right font-medium text-gray-500">Diskon Promo Harian:</td>
                        <td class="py-3 text-right font-medium text-gray-500">- Rp {{ number_format($rental->daily_discount_amount, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    @if($rental->date_promotion_amount > 0)
                    <tr>
                        <td colspan="4" class="py-3 pr-4 text-right font-medium text-gray-500">Diskon Promo Tanggal:</td>
                        <td class="py-3 text-right font-medium text-gray-500">- Rp {{ number_format($rental->date_promotion_amount, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                    <tr class="border-t border-gray-200">
                        <td colspan="4" class="py-3 pr-4 text-right font-bold text-gray-900">Total:</td>
                        <td class="py-3 text-right font-bold text-gray-900">Rp {{ number_format($rental->total, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>