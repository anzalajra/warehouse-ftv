@extends('layouts.guest')

@section('title', 'Daftar & Check-in')

@section('content')
<div class="min-h-screen bg-gray-50 py-8 px-4">
    <div class="max-w-md mx-auto bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-primary-600 to-primary-700 text-white p-5">
            <h1 class="text-xl font-bold">Daftar & Check-in</h1>
            <p class="text-sm opacity-90 mt-1">{{ $computer->name }}@if($computer->room) — {{ $computer->room->name }}@endif</p>
        </div>

        <form method="POST" action="{{ route('mobile.kiosk-register.submit', $computer->checkin_slug) }}" class="p-5 space-y-4">
            @csrf
            <input type="hidden" name="purpose" value="{{ $purpose }}">
            <input type="hidden" name="session_index" value="{{ $sessionIndex }}">

            @if($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                    <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                <input type="text" name="name" required value="{{ old('name') }}"
                       class="w-full px-3 py-2.5 border-2 border-gray-300 focus:border-primary-500 focus:ring-0 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required value="{{ old('email', $email) }}"
                       class="w-full px-3 py-2.5 border-2 border-gray-300 focus:border-primary-500 focus:ring-0 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">No. HP</label>
                <input type="tel" name="phone" required value="{{ old('phone') }}"
                       class="w-full px-3 py-2.5 border-2 border-gray-300 focus:border-primary-500 focus:ring-0 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full px-3 py-2.5 border-2 border-gray-300 focus:border-primary-500 focus:ring-0 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" required minlength="8"
                       class="w-full px-3 py-2.5 border-2 border-gray-300 focus:border-primary-500 focus:ring-0 rounded-lg">
            </div>

            <button type="submit" class="w-full px-4 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-lg transition">
                Daftar & Check-in
            </button>
        </form>
    </div>
</div>
@endsection
