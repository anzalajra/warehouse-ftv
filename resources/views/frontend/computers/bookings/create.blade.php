@extends('layouts.frontend')

@section('title', 'Booking '.$computer->name)

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-4">
        <a href="{{ route('computers.show', $computer) }}" class="text-primary-600 hover:underline text-sm">&larr; Kembali</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-xl font-bold text-gray-900 mb-1">Booking: {{ $computer->name }}</h1>
        <p class="text-sm text-gray-500 mb-6">Lengkapi detail booking di bawah ini.</p>

        @if($errors->any())
            <div class="mb-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('customer.computer-bookings.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="computer_id" value="{{ $computer->id }}">

            <div>
                <label for="booking_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                <input type="date" name="booking_date" id="booking_date" required min="{{ now()->toDateString() }}"
                    value="{{ old('booking_date', request('date', now()->toDateString())) }}"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="start_time" class="block text-sm font-medium text-gray-700 mb-1">Mulai</label>
                    <input type="time" name="start_time" id="start_time" required
                        value="{{ old('start_time', request('start')) }}"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
                <div>
                    <label for="end_time" class="block text-sm font-medium text-gray-700 mb-1">Selesai</label>
                    <input type="time" name="end_time" id="end_time" required
                        value="{{ old('end_time', request('end')) }}"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
            </div>

            <div>
                <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Kegunaan / Purpose</label>
                <textarea name="purpose" id="purpose" rows="3" required minlength="10"
                    placeholder="Contoh: Editing video tugas akhir MK Dokumenter"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('purpose') }}</textarea>
            </div>

            <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-700">
                <p class="font-semibold mb-1">Syarat &amp; Ketentuan</p>
                <p class="whitespace-pre-line">{{ $tncText }}</p>
            </div>

            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="tnc" value="1" required class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                <span>Saya menyetujui Syarat &amp; Ketentuan di atas.</span>
            </label>

            <div class="pt-2">
                <button type="submit" class="w-full inline-flex justify-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-md shadow">
                    Submit Booking
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
