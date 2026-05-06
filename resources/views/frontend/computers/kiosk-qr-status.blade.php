@extends('layouts.frontend')

@section('title', 'QR Check-in')

@section('content')
<div class="max-w-md mx-auto px-4 py-10">
    <div class="bg-white rounded-lg shadow p-6 text-center">
        @if($state === 'success')
            <div class="mx-auto w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-4">
                <svg class="w-9 h-9 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900">Check-in Berhasil</h1>
            <p class="mt-2 text-sm text-gray-600">
                Anda sudah check-in di komputer <span class="font-semibold">{{ $computer->name ?? '-' }}</span>.
                Halaman komputer akan otomatis update.
            </p>
            <p class="mt-3 text-xs text-gray-500">Anda boleh tutup tab ini.</p>
        @elseif($state === 'expired')
            <div class="mx-auto w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mb-4">
                <svg class="w-9 h-9 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900">QR Sudah Kadaluarsa</h1>
            <p class="mt-2 text-sm text-gray-600">
                Token QR ini sudah expired. Silakan scan ulang QR di komputer lab — QR akan refresh otomatis.
            </p>
        @else
            <div class="mx-auto w-16 h-16 rounded-full bg-yellow-100 flex items-center justify-center mb-4">
                <svg class="w-9 h-9 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-900">QR Sudah Digunakan</h1>
            <p class="mt-2 text-sm text-gray-600">
                Token ini sudah pernah dipakai. Silakan scan ulang QR di komputer lab kalau ingin check-in lagi.
            </p>
        @endif
    </div>
</div>
@endsection
