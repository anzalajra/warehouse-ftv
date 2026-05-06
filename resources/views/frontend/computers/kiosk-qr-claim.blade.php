@extends('layouts.frontend')

@section('title', 'Konfirmasi Check-in')

@section('content')
<div class="max-w-md mx-auto px-4 py-10">
    <div class="bg-white rounded-lg shadow p-6 text-center">
        <div class="mx-auto w-16 h-16 rounded-full bg-primary-100 flex items-center justify-center mb-4">
            <svg class="w-9 h-9 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25M21 12V5.25A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25V12"/>
            </svg>
        </div>

        <h1 class="text-xl font-bold text-gray-900">Konfirmasi Check-in</h1>
        <p class="mt-2 text-sm text-gray-600">
            Anda akan check-in di komputer:
        </p>
        <p class="mt-3 font-semibold text-gray-900 text-lg">
            {{ $token->computer->name }}
        </p>
        @if($token->computer->room)
            <p class="text-xs text-gray-500">{{ $token->computer->room->name }}</p>
        @endif

        <p class="mt-4 text-sm text-gray-600">
            Login sebagai <span class="font-medium">{{ auth('customer')->user()->name }}</span>
        </p>

        <form method="POST" action="{{ route('mobile.kiosk-login.claim', $token->token) }}" class="mt-6 space-y-3">
            @csrf
            <button type="submit" class="w-full inline-flex justify-center px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg shadow">
                Ya, Check-in Sekarang
            </button>
        </form>

        <p class="mt-4 text-xs text-gray-400">
            Token berlaku hingga {{ $token->expires_at->format('H:i') }}.
        </p>
    </div>
</div>
@endsection
