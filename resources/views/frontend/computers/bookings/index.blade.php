@extends('layouts.frontend')

@section('title', 'Booking Komputer Saya')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Booking Komputer Saya</h1>
        <a href="{{ route('computers.index') }}" class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-md shadow">+ Booking Baru</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">{{ session('success') }}</div>
    @endif

    @if($bookings->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">Belum ada booking.</div>
    @else
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Kode</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Komputer</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Tanggal</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Waktu</th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach($bookings as $booking)
                        <tr>
                            <td class="px-4 py-2 font-mono text-xs">{{ $booking->booking_code }}</td>
                            <td class="px-4 py-2">{{ $booking->computer->name ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $booking->booking_date->format('d M Y') }}</td>
                            <td class="px-4 py-2">{{ $booking->start_time }} - {{ $booking->end_time }}</td>
                            <td class="px-4 py-2">
                                @php
                                    $statusColors = [
                                        'confirmed' => 'bg-blue-100 text-blue-800',
                                        'active' => 'bg-green-100 text-green-800',
                                        'completed' => 'bg-purple-100 text-purple-800',
                                        'cancelled' => 'bg-gray-100 text-gray-700',
                                        'no_show' => 'bg-red-100 text-red-800',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusColors[$booking->status] ?? 'bg-gray-100' }}">{{ ucfirst(str_replace('_',' ', $booking->status)) }}</span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('customer.computer-bookings.show', $booking) }}" class="text-primary-600 hover:underline text-xs">Detail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $bookings->links() }}</div>
    @endif
</div>
@endsection
