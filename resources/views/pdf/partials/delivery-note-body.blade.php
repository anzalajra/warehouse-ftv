    <div class="document-title text-center">SURAT JALAN</div>
    <div class="text-center mb-4" style="color: #666;">
        {{ $delivery->type === 'out' ? 'BARANG KELUAR' : 'BARANG MASUK' }}
        <br>
        <strong>{{ $delivery->delivery_number }}</strong>
    </div>

    <div class="row mb-4">
        <div style="float: left; width: 40%;">
            <div class="meta-box" style="margin-right: 5px;">
                <div class="meta-title">Delivery Info</div>
                <table style="width: 100%; border: none;">
                    <tr>
                        <td style="padding: 2px; border: none; width: 80px;">Tanggal</td>
                        <td style="padding: 2px; border: none;">: {{ $delivery->date->format('d F Y') }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px; border: none;">Code</td>
                        <td style="padding: 2px; border: none;">: {{ $delivery->rental->rental_code }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 2px; border: none;">Period</td>
                        <td style="padding: 2px; border: none;">: {{ $delivery->rental->start_date->format('d/m/y') }} - {{ $delivery->rental->end_date->format('d/m/y') }}</td>
                    </tr>
                </table>
            </div>
        </div>
        <div style="float: left; width: 40%;">
            <div class="meta-box" style="margin-left: 5px; margin-right: 5px;">
                <div class="meta-title">Customer Info</div>
                <p class="mb-1"><strong>{{ $delivery->rental->user->name }}</strong></p>
                <p class="mb-1">{{ $delivery->rental->user->address ?? '-' }}</p>
                <p class="mb-1">Phone: {{ $delivery->rental->user->phone ?? '-' }}</p>
            </div>
        </div>
        <div style="float: left; width: 20%; text-align: right;">
            @if(!empty($doc_settings['doc_qr_delivery_note']))
                @php
                    $url = $delivery->type === 'out'
                        ? \App\Filament\Resources\Rentals\RentalResource::getUrl('pickup', ['record' => $delivery->rental_id])
                        : \App\Filament\Resources\Rentals\RentalResource::getUrl('return', ['record' => $delivery->rental_id]);
                @endphp
                <img src="{{ (new \chillerlan\QRCode\QRCode)->render($url) }}" style="width: 100px; height: 100px;">
            @endif
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 35%;">Item</th>
                <th style="width: 20%;">Serial Number</th>
                <th style="width: 15%;">Kondisi</th>
                <th style="width: 10%;">Status</th>
                <th style="width: 15%;">Notes</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($delivery->items as $item)
                @if(!$item->rentalItemKit)
                <tr>
                    <td>{{ $no++ }}</td>
                    <td>
                        <strong>{{ $item->rentalItem->productUnit->product->name }}</strong>
                        @if($item->rentalItem->productUnit->variation)
                            <br><span style="font-size: 0.8em; color: #666;">({{ $item->rentalItem->productUnit->variation->name }})</span>
                        @endif
                    </td>
                    <td>{{ $item->rentalItem->productUnit->serial_number }}</td>
                    <td>{{ $item->condition ? ucfirst($item->condition) : '-' }}</td>
                    <td>
                        <span class="badge {{ $item->is_checked ? 'badge-success' : 'badge-danger' }}">
                            {{ $item->is_checked ? 'Checked' : 'Unchecked' }}
                        </span>
                    </td>
                    <td>{{ $item->notes ?? '-' }}</td>
                </tr>
                @foreach($delivery->items->where('rental_item_id', $item->rental_item_id)->whereNotNull('rental_item_kit_id') as $kitItem)
                <tr style="background-color: {{ $doc_settings['doc_secondary_color'] ?? '#fafafa' }};">
                    <td></td>
                    <td style="padding-left: 20px; font-size: 11px;">
                        <span style="display:inline-block; width: 8px; height: 8px; border-left: 1px solid #666; border-bottom: 1px solid #666; margin-right: 2px; margin-bottom: 4px;">&nbsp;</span>
                        {{ $kitItem->rentalItemKit->unitKit->name }}
                    </td>
                    <td style="font-size: 11px;">{{ $kitItem->rentalItemKit->unitKit->serial_number ?? '-' }}</td>
                    <td style="font-size: 11px;">{{ $kitItem->not_taken ? '-' : ($kitItem->condition ? ucfirst($kitItem->condition) : '-') }}</td>
                    <td>
                        @if($kitItem->not_taken)
                            <span class="badge" style="background-color:#e5e7eb;color:#6b7280;">Tidak diambil</span>
                        @else
                            <span class="badge {{ $kitItem->is_checked ? 'badge-success' : 'badge-danger' }}">
                                {{ $kitItem->is_checked ? '✓' : '✗' }}
                            </span>
                        @endif
                    </td>
                    <td style="font-size: 11px;">{{ $kitItem->notes ?? '-' }}</td>
                </tr>
                @endforeach
                @endif
            @endforeach
        </tbody>
    </table>

    @if($delivery->notes)
    <div class="meta-box" style="margin-top: 20px;">
        <div class="meta-title">Catatan</div>
        {{ $delivery->notes }}
    </div>
    @endif

    <div style="margin-top: 50px; page-break-inside: avoid;">
        <table style="width: 100%; border: 1px solid #333; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="border: 1px solid #333; width: 25%; text-align: center; padding: 8px;">RENTER</th>
                    <th style="border: 1px solid #333; width: 25%; text-align: center; padding: 8px;">CHECKER</th>
                    <th style="border: 1px solid #333; width: 25%; text-align: center; padding: 8px;">VALIDATOR</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="border: 1px solid #333; height: 80px; vertical-align: bottom; text-align: center;">
                        @if($delivery->recipient_signature)
                            <img src="{{ $delivery->recipient_signature }}" style="max-height: 55px; display: block; margin: 0 auto 2px;">
                        @endif
                        <p class="mb-1"><strong>{{ $delivery->recipient_name ?? $delivery->rental->user->name }}</strong></p>
                    </td>
                    <td style="border: 1px solid #333; height: 80px; vertical-align: bottom; text-align: center;">
                        @if($delivery->checkedBy)
                            <p class="mb-1"><strong>{{ $delivery->checkedBy->name }}</strong></p>
                        @endif
                    </td>
                    <td style="border: 1px solid #333; height: 80px;"></td>
                </tr>
            </tbody>
        </table>
        <div style="font-style: italic; font-size: 10px; margin-top: 10px;">*Isi kolom dengan nama lengkap & tanda tangan.</div>
    </div>
