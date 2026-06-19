@extends('layouts.frontend')

@section('title', 'Rental Detail')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <nav class="text-sm mb-6">
        <a href="{{ route('customer.rentals') }}" class="text-gray-500 hover:text-primary-600">My Rentals</a>
        <span class="mx-2 text-gray-400">/</span>
        <span class="text-gray-900">{{ $rental->rental_code }}</span>
    </nav>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-2xl font-bold">{{ $rental->rental_code }}</h1>
                <p class="text-gray-600">Created on {{ $rental->created_at->format('d M Y H:i') }}</p>
            </div>
            <span class="px-4 py-2 rounded-full text-sm font-medium
                @if($rental->status == 'quotation') bg-orange-100 text-orange-800
                @elseif($rental->status == 'confirmed') bg-blue-100 text-blue-800
                @elseif($rental->status == 'active') bg-green-100 text-green-800
                @elseif($rental->status == 'completed') bg-purple-100 text-purple-800
                @elseif($rental->status == 'cancelled') bg-gray-100 text-gray-800
                @elseif(in_array($rental->status, ['late_pickup', 'late_return'])) bg-red-100 text-red-800
                @elseif($rental->status == 'expired') bg-gray-100 text-gray-500
                @else bg-gray-100 text-gray-800
                @endif">
                {{ ucfirst(str_replace('_', ' ', $rental->status)) }}
            </span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div>
                <label class="block text-sm text-gray-500">Start Date</label>
                <p class="font-semibold">{{ $rental->start_date->format('d M Y H:i') }}</p>
            </div>
            <div>
                <label class="block text-sm text-gray-500">End Date</label>
                <p class="font-semibold">{{ $rental->end_date->format('d M Y H:i') }}</p>
            </div>
            <div>
                <label class="block text-sm text-gray-500">Total</label>
                <p class="font-semibold text-primary-600">Rp {{ number_format($rental->total, 0, ',', '.') }}</p>
            </div>
            <div>
                <label class="block text-sm text-gray-500">Deposit</label>
                <p class="font-semibold">Rp {{ number_format($rental->deposit, 0, ',', '.') }}</p>
            </div>
        </div>

        @if($rental->notes)
            <div class="mb-6">
                <label class="block text-sm text-gray-500">Notes</label>
                <p>{{ $rental->notes }}</p>
            </div>
        @endif
    </div>

    @if(!in_array($rental->status, ['completed', 'cancelled']))
        @include('frontend.partials.administration-checklist', [
            'steps' => $checklistSteps,
            'waLink' => $waLink,
            'checklistPdfUrl' => $checklistPdfUrl,
            'permitLink' => $permitLink,
            'rental' => $rental,
            'context' => 'detail',
        ])
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Rental Items</h2>
        
        <div class="space-y-4">
            @php
                $groupedItems = $rental->items->groupBy(function($item) {
                    return $item->productUnit->product->id;
                });
            @endphp

            @foreach($groupedItems as $items)
                @php
                    $firstItem = $items->first();
                    $product = $firstItem->productUnit->product;
                    $quantity = $items->count();
                    $subtotal = $items->sum('subtotal');
                    $allKits = $items->flatMap->rentalItemKits;
                @endphp
                <div class="border rounded-lg p-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center">
                            <div class="h-16 w-16 bg-gray-200 rounded flex-shrink-0 flex items-center justify-center mr-4">
                                @if($product->image)
                                    <img src="{{ Storage::url($product->image) }}" alt="" class="h-full w-full object-cover rounded">
                                @else
                                    <span class="text-2xl">📷</span>
                                @endif
                            </div>
                            <div>
                                <p class="font-semibold">{{ $product->name }}</p>
                                <p class="text-sm text-gray-500">{{ $firstItem->days }} days × Rp {{ number_format($firstItem->daily_rate, 0, ',', '.') }}</p>
                                <p class="text-sm font-medium mt-1">Quantity: {{ $quantity }}</p>
                            </div>
                        </div>
                        <p class="font-semibold text-right sm:text-left">Rp {{ number_format($subtotal, 0, ',', '.') }}</p>
                    </div>

                    @if($allKits->count() > 0)
                        <div class="mt-3 pt-3 border-t">
                            <p class="text-sm font-medium text-gray-700 mb-2">Included Kits (Total):</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($allKits as $kit)
                                    <span class="px-2 py-1 bg-gray-100 rounded text-sm">{{ $kit->unitKit->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6 pt-4 border-t space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Subtotal</span>
                <span>Rp {{ number_format($rental->subtotal, 0, ',', '.') }}</span>
            </div>
            @foreach($rental->discountBreakdown() as $line)
                <div class="flex justify-between text-green-600">
                    <span>{{ $line['label'] }}</span>
                    <span>- Rp {{ number_format($line['amount'], 0, ',', '.') }}</span>
                </div>
            @endforeach
            <div class="flex justify-between font-bold text-base pt-2 border-t">
                <span>Total</span>
                <span class="text-primary-600">Rp {{ number_format($rental->total, 0, ',', '.') }}</span>
            </div>
            @if($rental->deposit > 0)
                <div class="flex justify-between text-gray-600">
                    <span>Deposit</span>
                    <span>Rp {{ number_format($rental->deposit, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection