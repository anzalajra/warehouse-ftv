@extends('pdf.layout')

@section('title', 'Invoice - ' . $invoice->number)

@section('content')
    <div class="document-title">INVOICE</div>

    <div class="row mb-4">
        <div class="col-6">
            <div class="meta-box" style="margin-right: 10px;">
                <div class="meta-title">Invoice Details</div>
                <p class="mb-1"><strong>Invoice #:</strong> {{ $invoice->number }}</p>
                <p class="mb-1"><strong>Date:</strong> {{ $invoice->date ? $invoice->date->format('d F Y') : '-' }}</p>
                <p class="mb-1"><strong>Due Date:</strong> {{ $invoice->due_date ? $invoice->due_date->format('d F Y') : '-' }}</p>
                <p class="mb-1"><strong>Status:</strong> 
                    <span class="badge {{ $invoice->status === 'paid' ? 'badge-success' : 'badge-danger' }}">
                        {{ strtoupper($invoice->status) }}
                    </span>
                </p>
            </div>
        </div>
        <div class="col-6">
            <div class="meta-box" style="margin-left: 10px;">
                <div class="meta-title">Bill To</div>
                <p class="mb-1"><strong>{{ $invoice->user->name }}</strong></p>
                <p class="mb-1">{{ $invoice->user->address ?? '-' }}</p>
                <p class="mb-1">Phone: {{ $invoice->user->phone ?? '-' }}</p>
                <p class="mb-1">Email: {{ $invoice->user->email ?? '-' }}</p>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 35%;">Item</th>
                <th style="width: 20%;">Serial Number</th>
                <th style="width: 15%;" class="text-right">Price/Day</th>
                <th style="width: 10%;" class="text-right">Days</th>
                <th style="width: 15%;" class="text-right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->rentals as $rental)
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
            @if(!empty($doc_settings['doc_bank_details']))
                <div class="meta-box" style="margin-right: 10px;">
                    <div class="meta-title">Payment Info</div>
                    <div style="font-size: 11px;">
                        {!! $doc_settings['doc_bank_details'] !!}
                    </div>
                </div>
            @endif

            @if($invoice->notes)
            <div class="meta-box" style="margin-right: 10px; margin-top: 10px;">
                <div class="meta-title">Notes</div>
                <p style="font-size: 11px;">{{ $invoice->notes }}</p>
            </div>
            @endif
        </div>
        <div class="col-6">
            <table style="width: 100%; margin-left: 10px;">
                <tr>
                    <td style="border: none; padding: 5px;">Subtotal</td>
                    <td style="border: none; padding: 5px;" class="text-right">Rp {{ number_format($invoice->subtotal, 0, ',', '.') }}</td>
                </tr>
                @php
                    $totalDiscount = $invoice->rentals->sum(function($rental) {
                        $baseDiscount = 0;
                        if ($rental->discount_type === 'percent') {
                            $baseDiscount = ($rental->subtotal ?? 0) * (($rental->discount ?? 0) / 100);
                        } else {
                            $baseDiscount = $rental->discount ?? 0;
                        }
                        
                        return $baseDiscount + ($rental->daily_discount_amount ?? 0) + ($rental->date_promotion_amount ?? 0);
                    });
                @endphp
                @if($totalDiscount > 0)
                <tr>
                    <td style="border: none; padding: 5px;">Discount</td>
                    <td style="border: none; padding: 5px;" class="text-right">- Rp {{ number_format($totalDiscount, 0, ',', '.') }}</td>
                </tr>
                @endif

                @if($invoice->late_fee > 0)
                <tr>
                    <td style="border: none; padding: 5px;">Late Fee</td>
                    <td style="border: none; padding: 5px;" class="text-right">Rp {{ number_format($invoice->late_fee, 0, ',', '.') }}</td>
                </tr>
                @endif

                @if($invoice->ppn_amount > 0)
                <tr>
                    <td style="border: none; padding: 5px;">DPP (Tax Base)</td>
                    <td style="border: none; padding: 5px;" class="text-right">Rp {{ number_format($invoice->tax_base, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td style="border: none; padding: 5px;">PPN ({{ 11 }}%)</td>
                    <td style="border: none; padding: 5px;" class="text-right">Rp {{ number_format($invoice->ppn_amount, 0, ',', '.') }}</td>
                </tr>
                @endif

                @if($invoice->pph_amount > 0)
                <tr>
                    <td style="border: none; padding: 5px;">PPh ({{ $invoice->pph_rate }}%)</td>
                    <td style="border: none; padding: 5px;" class="text-right">(Rp {{ number_format($invoice->pph_amount, 0, ',', '.') }})</td>
                </tr>
                @endif

                @php
                    $totalDeposit = $invoice->rentals->sum('deposit_amount');
                @endphp
                @if($totalDeposit > 0)
                <tr>
                    <td style="border: none; padding: 5px;">Security Deposit (Titipan)</td>
                    <td style="border: none; padding: 5px;" class="text-right">Rp {{ number_format($totalDeposit, 0, ',', '.') }}</td>
                </tr>
                @endif

                <tr style="font-weight: bold; font-size: 14px; background-color: {{ $doc_settings['doc_secondary_color'] ?? '#f3f4f6' }};">
                    <td style="padding: 10px;">GRAND TOTAL</td>
                    <td style="padding: 10px;" class="text-right">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>
    </div>
@endsection
