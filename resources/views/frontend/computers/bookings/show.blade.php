@extends('layouts.frontend')

@section('title', 'Detail Booking '.$booking->booking_code)

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-4">
        <a href="{{ route('customer.computer-bookings.index') }}" class="text-primary-600 hover:underline text-sm">&larr; Kembali ke daftar</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
            <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <p class="text-xs font-mono text-gray-500">{{ $booking->booking_code }}</p>
                <h1 class="text-xl font-bold text-gray-900">{{ $booking->computer->name ?? '-' }}</h1>
            </div>
            @php
                $statusColors = [
                    'confirmed' => 'bg-blue-100 text-blue-800',
                    'active' => 'bg-green-100 text-green-800',
                    'completed' => 'bg-purple-100 text-purple-800',
                    'cancelled' => 'bg-gray-100 text-gray-700',
                    'no_show' => 'bg-red-100 text-red-800',
                ];
            @endphp
            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium {{ $statusColors[$booking->status] ?? 'bg-gray-100' }}">{{ ucfirst(str_replace('_',' ', $booking->status)) }}</span>
        </div>

        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500">Tanggal</dt>
                <dd class="font-medium">{{ $booking->booking_date->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-gray-500">Waktu</dt>
                <dd class="font-medium">{{ $booking->start_time }} - {{ $booking->end_time }}</dd>
            </div>
            <div class="col-span-2">
                <dt class="text-gray-500">Kegunaan</dt>
                <dd class="font-medium whitespace-pre-line">{{ $booking->purpose }}</dd>
            </div>
            @if($booking->admin_notes)
                <div class="col-span-2">
                    <dt class="text-gray-500">Catatan Admin</dt>
                    <dd class="font-medium whitespace-pre-line">{{ $booking->admin_notes }}</dd>
                </div>
            @endif
            @if($booking->cancelled_reason)
                <div class="col-span-2">
                    <dt class="text-gray-500">Alasan Pembatalan</dt>
                    <dd class="font-medium">{{ $booking->cancelled_reason }}</dd>
                </div>
            @endif
        </dl>

        @if($booking->isCancellable())
            <form method="POST" action="{{ route('customer.computer-bookings.cancel', $booking) }}" class="mt-6" onsubmit="return confirm('Yakin ingin membatalkan booking ini?');">
                @csrf
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-md">Batalkan Booking</button>
            </form>
        @endif
    </div>
</div>
@endsection
