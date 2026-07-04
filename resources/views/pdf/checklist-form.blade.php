@extends('pdf.layout')

@section('title', 'Checklist Form - ' . $rental->rental_code)

@section('content')
    <div class="document-title text-center">CHECKLIST FORM</div>
    <div class="text-center mb-4" style="color: #666;">
        Equipment Pickup & Return Verification
        <br>
        <strong>{{ $rental->rental_code }}</strong>
    </div>

    <div class="row mb-4">
        <div style="float: left; width: 40%;">
            <div class="meta-box" style="margin-right: 5px;">
                <div class="meta-title">Rental Info</div>
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="padding: 2px; border: none; width: 80px;">Status</td>
                        <td style="padding: 2px; border: none;">: {{ ucfirst($rental->getRealTimeStatus()) }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px; border: none;">Date</td>
                        <td style="padding: 2px; border: none;">: {{ $rental->start_date->format('d/m/Y H:i') }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px; border: none;">Period</td>
                        <td style="padding: 2px; border: none;">: {{ $rental->start_date->format('d M') }} - {{ $rental->end_date->format('d M Y') }}</td>
                    </tr>
                </table>
            </div>
        </div>
        <div style="float: left; width: 40%;">
            <div class="meta-box" style="margin-left: 5px; margin-right: 5px;">
                <div class="meta-title">Renter Info</div>
                <p class="mb-1"><strong>{{ $rental->user->name }}</strong></p>
                <p class="mb-1">{{ $rental->user->address ?? '-' }}</p>
                <p class="mb-1">Phone: {{ $rental->user->phone ?? '-' }}</p>
            </div>
        </div>
        <div style="float: left; width: 20%; text-align: right;">
            @if(!empty($doc_settings['doc_qr_checklist_form']))
                <img src="{{ (new \chillerlan\QRCode\QRCode)->render(\App\Filament\Resources\Rentals\RentalResource::getUrl('view', ['record' => $rental])) }}" style="width: 100px; height: 100px;">
            @endif
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40%;">PRODUCT</th>
                <th style="width: 10%; text-align: center;">QTY</th>
                <th style="width: 10%; text-align: center;">OUT</th>
                <th style="width: 10%; text-align: center;">IN</th>
                <th style="width: 30%;">SERIAL NUMBER</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rental->items as $item)
                @php
                    $product = $item->productUnit?->product ?? $item->product;
                    $variation = $item->productUnit?->variation ?? $item->productVariation;
                @endphp
                <tr>
                    <td>
                        <strong>{{ $product?->name ?? '-' }}</strong>
                        @if($variation)
                            <br><span style="font-size: 0.8em; color: #666;">({{ $variation->name }})</span>
                        @endif
                    </td>
                    <td style="text-align: center;">1</td>
                    <td style="text-align: center;"><div style="width: 15px; height: 15px; border: 1px solid #333; margin: 0 auto;"></div></td>
                    <td style="text-align: center;"><div style="width: 15px; height: 15px; border: 1px solid #333; margin: 0 auto;"></div></td>
                    <td>{{ $item->productUnit?->serial_number ?? '(unassigned)' }}</td>
                </tr>
                @foreach($item->rentalItemKits as $kit)
                <tr style="background-color: {{ $doc_settings['doc_secondary_color'] ?? '#fafafa' }};">
                    <td style="padding-left: 20px; font-size: 11px;">
                        <span style="display:inline-block; width: 8px; height: 8px; border-left: 1px solid #666; border-bottom: 1px solid #666; margin-right: 2px; margin-bottom: 4px;">&nbsp;</span>
                        {{ $kit->unitKit->name }}
                    </td>
                    <td style="text-align: center; font-size: 11px;">1</td>
                    <td style="text-align: center;"><div style="width: 12px; height: 12px; border: 1px solid #666; margin: 0 auto;"></div></td>
                    <td style="text-align: center;"><div style="width: 12px; height: 12px; border: 1px solid #666; margin: 0 auto;"></div></td>
                    <td style="font-size: 11px;">{{ $kit->unitKit->serial_number ?? '-' }}</td>
                </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div style="margin-top: 50px; page-break-inside: avoid;">
        <table style="width: 100%; border: 1px solid #333; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="border: 1px solid #333; width: 25%; text-align: center; padding: 8px;">PICKUP RENTER</th>
                    <th style="border: 1px solid #333; width: 25%; text-align: center; padding: 8px;">PICKUP CHECKER</th>
                    <th style="border: 1px solid #333; width: 25%; text-align: center; padding: 8px;">RETURN RENTER</th>
                    <th style="border: 1px solid #333; width: 25%; text-align: center; padding: 8px;">RETURN CHECKER</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="border: 1px solid #333; height: 80px;"></td>
                    <td style="border: 1px solid #333; height: 80px;"></td>
                    <td style="border: 1px solid #333; height: 80px;"></td>
                    <td style="border: 1px solid #333; height: 80px;"></td>
                </tr>

            </tbody>
        </table>
        <div style="font-style: italic; font-size: 10px; margin-top: 10px;">*Isi kolom dengan nama lengkap & tanda tangan untuk setiap proses.<br>Dokumen yang ditahan:</div>
    </div>
@endsection
