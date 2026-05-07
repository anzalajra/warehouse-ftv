@extends('layouts.kiosk')

@section('title', 'Check-in Lain - '.$computer->name)

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{
    confirmed: {{ $activeBooking ? 'false' : 'true' }},
}">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-primary-600 to-primary-700 text-white p-6">
            <h1 class="text-2xl font-bold">Check-in di komputer ini</h1>
            <p class="opacity-90 text-sm">{{ $computer->name }}@if($computer->room) — {{ $computer->room->name }}@endif</p>
        </div>

        @if($activeBooking)
            {{-- Confirmation modal-style guard --}}
            <div x-show="!confirmed" class="p-6 sm:p-8">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <p class="font-semibold text-amber-900">Komputer ini sudah dibooking oleh:</p>
                    <p class="text-lg font-bold text-amber-900 mt-1">{{ $activeBooking->user->name }}</p>
                    <p class="text-sm text-amber-800 mt-1">{{ $activeBooking->start_time }} - {{ $activeBooking->end_time }}</p>
                    <p class="mt-3 text-sm text-amber-900">
                        Kamu akan melakukan check-in di jadwal <strong>{{ $activeBooking->user->name }}</strong>.
                        Apakah kamu yakin?
                    </p>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <a href="{{ route('kiosk.checkin', $computer->checkin_slug) }}"
                       class="px-4 py-3 text-center bg-white border-2 border-gray-300 hover:border-gray-400 text-gray-800 font-semibold rounded-xl transition">
                        Tidak
                    </a>
                    <button type="button" @click="confirmed = true"
                            class="px-4 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition">
                        Iya, lanjut
                    </button>
                </div>
            </div>
        @endif

        {{-- Form --}}
        <form x-show="confirmed" method="POST" action="{{ route('kiosk.checkin.other.submit', $computer->checkin_slug) }}" class="p-6 sm:p-8 space-y-5">
            @csrf

            @if($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required value="{{ old('email') }}"
                       class="w-full px-4 py-3 border-2 border-gray-300 focus:border-primary-500 focus:ring-0 rounded-lg text-base"
                       placeholder="email@example.com">
                <p class="mt-1 text-xs text-gray-500">Kalau email belum terdaftar, kamu akan diarahkan ke registrasi.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Sesi</label>
                <div class="space-y-2">
                    @foreach($sessions as $i => $sess)
                        <label class="flex items-center gap-3 p-3 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-primary-400 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                            <input type="radio" name="session_index" value="{{ $i }}" @if($i === 0) checked @endif required class="text-primary-600">
                            <span class="text-sm font-medium text-gray-900">{{ $sess['label'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kegunaan untuk apa?</label>
                <textarea name="purpose" required rows="3"
                          class="w-full px-4 py-3 border-2 border-gray-300 focus:border-primary-500 focus:ring-0 rounded-lg text-base"
                          placeholder="Misal: editing tugas akhir, render video, dll.">{{ old('purpose') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2">
                <a href="{{ route('kiosk.checkin', $computer->checkin_slug) }}"
                   class="px-4 py-3 text-center bg-white border-2 border-gray-300 hover:border-gray-400 text-gray-800 font-semibold rounded-xl transition">
                    Batal
                </a>
                <button type="submit"
                        class="px-4 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition">
                    Check-in
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
