@extends('pdf.layout')

@section('title', 'Quotation - ' . $quotation->number)

@section('content')
    <div class="document-title text-center">QUOTATION</div>
    <div class="text-center mb-4" style="color: #666;">Penawaran Harga Sewa Alat</div>

    <div class="row mb-4">
        <div class="col-6">
            <div class="meta-box" style="margin-right: 10px;">
                <div class="meta-title">Customer Info</div>
                <p class="mb-1"><strong>{{ $quotation->user->name }}</strong></p>
                <p class="mb-1">{{ $quotation->user->address ?? '-' }}</p>
                <p class="mb-1">Phone: {{ $quotation->user->phone ?? '-' }}</p>
            </div>
        </div>
        <div class="col-6">
            <div class="meta-box" style="margin-left: 10px;">
                <div class="meta-title">Quotation Details</div>
                <p class="mb-1"><strong>Code:</strong> {{ $quotation->number }}</p>
                <p class="mb-1"><strong>Date:</strong> {{ $quotation->date ? $quotation->date->format('d F Y') : '-' }}</p>
                <p class="mb-1"><strong>Valid Until:</strong> {{ $quotation->valid_until ? $quotation->valid_until->format('d F Y') : '-' }}</p>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 35%;">Item</th>
                <th style="width: 15%;">Serial Number</th>
                <th style="width: 15%;" class="text-right">Price/Day</th>
                <th style="width: 10%;" class="text-right">Days</th>
                <th style="width: 20%;" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->rentals as $rental)
            <tr>
                <td colspan="6" style="background-color: #f3f4f6; font-weight: bold; font-size: 11px;">
                    Rental: {{ $rental->rental_code }} | Period: {{ $rental->start_date->format('d M Y H:i') }} - {{ $rental->end_date->format('d M Y H:i') }}
                </td>
            </tr>
            @foreach($rental->items as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->productUnit->product->name }}</td>
                <td>{{ $item->productUnit->serial_number }}</td>
                <td class="text-right">Rp {{ number_format($item->daily_rate, 0, ',', '.') }}</td>
                <td class="text-right">{{ $item->days }}</td>
                <td class="text-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
            @php
                $kits = $item->rentalItemKits->count() > 0 
                    ? $item->rentalItemKits->map(fn($k) => $k->unitKit) 
                    : $item->productUnit->kits;
            @endphp
            @foreach($kits as $kit)
            <tr style="background-color: {{ $doc_settings['doc_secondary_color'] ?? '#fafafa' }};">
                <td></td>
                <td style="padding-left: 20px; font-size: 11px;">
                    <span style="display:inline-block; width: 8px; height: 8px; border-left: 1px solid #666; border-bottom: 1px solid #666; margin-right: 2px; margin-bottom: 4px;">&nbsp;</span>
                    {{ $kit->name }}
                </td>
                <td style="font-size: 11px;">{{ $kit->serial_number ?? '-' }}</td>
                <td class="text-right" style="font-size: 11px;">-</td>
                <td class="text-right" style="font-size: 11px;">-</td>
                <td class="text-right" style="font-size: 11px;">-</td>
            </tr>
            @endforeach
            @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="row">
        <div class="col-6">
            @if(!empty($doc_settings['doc_quotation_terms']))
                <div class="meta-box" style="margin-right: 10px;">
                    <div class="meta-title">Terms & Conditions</div>
                    <div style="font-size: 11px;">
                        {!! $doc_settings['doc_quotation_terms'] !!}
                    </div>
                </div>
            @endif
            
            @if($quotation->notes)
            <div class="meta-box" style="margin-right: 10px; margin-top: 10px;">
                <div class="meta-title">Notes</div>
                <p style="font-size: 11px;">{{ $quotation->notes }}</p>
            </div>
            @endif
        </div>
        <div class="col-6">
            <table style="width: 100%; margin-left: 10px;">
                <tr>
                    <td style="border: none; padding: 5px;">Subtotal</td>
                    <td style="border: none; padding: 5px;" class="text-right">Rp {{ number_format($quotation->subtotal, 0, ',', '.') }}</td>
                </tr>
                @php
                    // Aggregate every discount layer across the quotation's rentals,
                    // grouped by their human-readable label (Rental::discountBreakdown).
                    $discountLines = [];
                    foreach ($quotation->rentals as $rental) {
                        foreach ($rental->discountBreakdown() as $line) {
                            $discountLines[$line['label']] = ($discountLines[$line['label']] ?? 0) + $line['amount'];
                        }
                    }
                @endphp

                @foreach($discountLines as $label => $amount)
                    @if($amount > 0)
                    <tr>
                        <td style="border: none; padding: 5px;">{{ $label }}</td>
                        <td style="border: none; padding: 5px;" class="text-right">- Rp {{ number_format($amount, 0, ',', '.') }}</td>
                    </tr>
                    @endif
                @endforeach
                <tr style="font-weight: bold; font-size: 14px; background-color: {{ $doc_settings['doc_secondary_color'] ?? '#f3f4f6' }};">
                    <td style="padding: 10px;">Total</td>
                    <td style="padding: 10px;" class="text-right">Rp {{ number_format($quotation->total, 0, ',', '.') }}</td>
                </tr>
                @php
                    $totalDeposit = $quotation->rentals->sum('deposit');
                @endphp
                @if($totalDeposit > 0)
                <tr>
                    <td style="border: none; padding: 5px;">Deposit</td>
                    <td style="border: none; padding: 5px;" class="text-right">Rp {{ number_format($totalDeposit, 0, ',', '.') }}</td>
                </tr>
                @endif
            </table>
        </div>
    </div>
@endsection
