@extends('layouts.frontend')

@section('title', 'Booking Confirmed')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="text-center mb-8">
        <div class="text-6xl mb-4">✅</div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Booking Submitted!</h1>
        <p class="text-gray-600">Thank you for your booking. We will contact you shortly to confirm.</p>
    </div>

    @include('frontend.partials.administration-checklist', [
        'steps' => $rental->getChecklistSteps(),
        'waLink' => $waLink,
        'checklistPdfUrl' => $checklistPdfUrl,
        'permitLink' => $permitLink,
        'rental' => $rental,
        'context' => 'detail',
    ])

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Booking Details</h2>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm text-gray-500">Booking Code</label>
                <p class="font-semibold text-lg">{{ $rental->rental_code }}</p>
            </div>
            <div>
                <label class="block text-sm text-gray-500">Status</label>
                <span class="inline-block px-3 py-1 bg-orange-100 text-orange-800 rounded-full text-sm font-medium">{{ ucfirst($rental->status) }}</span>
            </div>
            <div>
                <label class="block text-sm text-gray-500">Start Date</label>
                <p class="font-medium">{{ $rental->start_date->format('d M Y H:i') }}</p>
            </div>
            <div>
                <label class="block text-sm text-gray-500">End Date</label>
                <p class="font-medium">{{ $rental->end_date->format('d M Y H:i') }}</p>
            </div>
        </div>

        <hr class="my-4">

        <h3 class="font-semibold mb-3">Items</h3>
        <div class="space-y-3">
            @foreach($rental->items as $item)
                <div class="flex justify-between">
                    <div>
                        <p class="font-medium">
                            {{ $item->productUnit->product->name }}
                            @if($item->productUnit->variation)
                                <span class="text-gray-500 font-normal">({{ $item->productUnit->variation->name }})</span>
                            @endif
                        </p>
                        <p class="text-sm text-gray-500">{{ $item->days }} days × Rp {{ number_format($item->daily_rate, 0, ',', '.') }}</p>
                    </div>
                    <p class="font-medium">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</p>
                </div>
            @endforeach
        </div>

        <hr class="my-4">

        <div class="flex justify-between font-bold text-lg">
            <span>Total</span>
            <span class="text-primary-600">Rp {{ number_format($rental->total, 0, ',', '.') }}</span>
        </div>
        <div class="flex justify-between text-sm text-gray-600 mt-2">
            <span>Deposit Required</span>
            <span>Rp {{ number_format($rental->deposit, 0, ',', '.') }}</span>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row justify-center gap-3">
        <a href="{{ $waLink }}" target="_blank" rel="noopener"
            class="inline-flex items-center justify-center bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition">
            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.5.5 0 00.612.638l4.702-1.407A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-2.24 0-4.326-.727-6.022-1.96l-.424-.318-2.785.833.762-2.672-.348-.453A9.96 9.96 0 012 12C2 6.486 6.486 2 12 2s10 4.486 10 10-4.486 10-10 10z"/></svg>
            Konfirmasi via WhatsApp
        </a>
        <a href="{{ route('customer.rentals') }}" class="inline-flex items-center justify-center bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition">
            Lihat Rental Saya
        </a>
    </div>
</div>
@endsection