@extends('layouts.guest')

@section('title', 'Check-in Berhasil')

@section('content')
<div class="min-h-screen bg-gray-50 py-10 px-4 flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl overflow-hidden text-center p-8">
        <div class="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="mt-4 text-2xl font-bold text-gray-900">Berhasil Check-in</h1>
        <p class="mt-2 text-gray-600">Akun berhasil dibuat dan kamu sudah check-in di komputer:</p>
        <p class="mt-1 text-lg font-semibold">{{ $computer->name }}</p>
        <p class="text-sm text-gray-500">Sesi: {{ $booking->start_time }} - {{ $booking->end_time }}</p>
        <p class="mt-6 text-sm text-gray-500">Silakan kembali ke komputer dan mulai gunakan.</p>
    </div>
</div>
@endsection
