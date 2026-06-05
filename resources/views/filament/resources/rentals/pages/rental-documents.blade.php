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
                        <td class="py-3 pr-6 font-medium text-gray-500" style="width: 15%;">Customer</td>
                        <td class="py-3 font-semibold" style="width: 35%;">{{ $rental->customer->name }}</td>
                    </tr>
                    <tr>
                        <td class="py-3 pr-6 font-medium text-gray-500">Start Date</td>
                        <td class="py-3 pr-6 font-semibold">{{ $rental->start_date->format('d M Y H:i') }}</td>
                        <td class="py-3 pr-6 font-medium text-gray-500">End Date</td>
                        <td class="py-3 font-semibold">{{ $rental->end_date->format('d M Y H:i') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">
            Surat Jalan (SJK / SJM)
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
