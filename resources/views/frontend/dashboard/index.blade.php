@extends('layouts.frontend')

@section('title', 'Dashboard')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center gap-3 mb-8 flex-wrap">
        <h1 class="text-2xl font-bold">Welcome, {{ $customer->name }}!</h1>
        @if($customer->category)
            <span class="px-3 py-1 rounded-full text-sm font-medium text-white shadow-sm" style="background-color: {{ $customer->category->badge_color ?? '#6b7280' }}">
                {{ $customer->category->name }}
            </span>
        @endif

        @php
            $__dashFields = json_decode(\App\Models\Setting::get('registration_custom_fields', '[]'), true) ?: [];
            $__customValues = $customer->custom_fields ?? [];
        @endphp
        @foreach($__dashFields as $__df)
            @php
                if (empty($__df['display_on_dashboard']) || empty($__df['name'])) continue;
                $__val = $__customValues[$__df['name']] ?? null;
                if ($__val === null || $__val === '' || $__val === []) continue;

                // Map select/radio values to their labels if options provided
                $__rawOpts = $__df['options'] ?? [];
                if (is_string($__rawOpts)) {
                    $__rawOpts = array_filter(array_map('trim', explode(',', $__rawOpts)), fn($v) => $v !== '');
                }
                $__display = $__val;
                if ($__df['type'] === 'checkbox') {
                    $__display = $__val ? ($__df['label'] ?? 'Yes') : null;
                } elseif (in_array($__df['type'] ?? null, ['select', 'radio']) && !empty($__rawOpts)) {
                    foreach ($__rawOpts as $__o) {
                        if (is_array($__o)) {
                            if (($__o['value'] ?? null) == $__val) { $__display = $__o['label'] ?? $__val; break; }
                        } else {
                            if ($__o == $__val) { $__display = $__o; break; }
                        }
                    }
                }
            @endphp
            @if($__display !== null && $__display !== '')
                <span class="px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-700 border border-gray-200 shadow-sm">
                    <span class="text-gray-500">{{ $__df['label'] ?? $__df['name'] }}:</span>
                    <span class="font-semibold">{{ $__display }}</span>
                </span>
            @endif
        @endforeach
    </div>

    <!-- Verification Warning -->
    @if($verificationStatus === 'blocked')
        <div class="mb-8 p-4 rounded-lg border bg-red-50 border-red-300">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728A9 9 0 015.636 5.636"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Akun Anda Diblokir</h3>
                    <p class="mt-1 text-sm text-red-700">Akun Anda telah diblokir oleh admin dan tidak dapat melakukan rental.</p>
                    @if($customer->blocked_reason)
                        <p class="mt-1 text-sm text-red-700"><span class="font-semibold">Alasan:</span> {{ $customer->blocked_reason }}</p>
                    @endif
                </div>
            </div>
        </div>
    @elseif($customer->isRedNotice())
        <div class="mb-8 p-4 rounded-lg border bg-orange-50 border-orange-300">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-orange-800">Red Notice</h3>
                    <p class="mt-1 text-sm text-orange-700">Akun Anda berada dalam status Red Notice. Mohon perhatian khusus pada penggunaan layanan.</p>
                </div>
            </div>
        </div>
    @elseif($verificationStatus !== 'verified')
        <div class="mb-8 p-4 rounded-lg border
            @if($verificationStatus === 'pending') bg-yellow-50 border-yellow-300
            @else bg-red-50 border-red-300 @endif">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    @if($verificationStatus === 'pending')
                        <svg class="h-6 w-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    @else
                        <svg class="h-6 w-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    @endif
                </div>
                <div class="ml-3">
                    @if($verificationStatus === 'pending')
                        <h3 class="text-sm font-medium text-yellow-800">Verifikasi Sedang Diproses</h3>
                        <p class="mt-1 text-sm text-yellow-700">Dokumen Anda sedang ditinjau oleh admin. Anda akan dapat melakukan rental setelah verifikasi disetujui.</p>
                    @else
                        <h3 class="text-sm font-medium text-red-800">Akun Belum Terverifikasi</h3>
                        <p class="mt-1 text-sm text-red-700">Anda harus mengunggah dokumen yang diperlukan untuk melakukan rental.
                            <a href="{{ route('customer.profile') }}" class="font-semibold underline">Lengkapi verifikasi sekarang →</a>
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        @php
            $isRedNoticeOnly = $verificationStatus !== 'blocked' && $customer->isRedNotice();
            if ($verificationStatus === 'blocked') {
                $statusBg = 'bg-red-100'; $statusText = 'text-red-600'; $statusLabel = 'Blocked';
            } elseif ($isRedNoticeOnly) {
                $statusBg = 'bg-orange-100'; $statusText = 'text-orange-600'; $statusLabel = 'Red Notice';
            } elseif ($verificationStatus === 'verified') {
                $statusBg = 'bg-green-100'; $statusText = 'text-green-600'; $statusLabel = 'Terverifikasi';
            } elseif ($verificationStatus === 'pending') {
                $statusBg = 'bg-yellow-100'; $statusText = 'text-yellow-600'; $statusLabel = 'Sedang Diverifikasi';
            } else {
                $statusBg = 'bg-red-100'; $statusText = 'text-red-600'; $statusLabel = 'Belum Verifikasi';
            }
        @endphp
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full mr-4 {{ $statusBg }}">
                    <svg class="w-6 h-6 {{ $statusText }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Status</p>
                    <p class="text-lg font-bold {{ $statusText }}">
                        {{ $statusLabel }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-primary-100 rounded-full mr-4">
                    <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Active Rentals</p>
                    <p class="text-2xl font-bold">{{ $activeRentals->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-green-100 rounded-full mr-4">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Completed</p>
                    <p class="text-2xl font-bold">{{ $pastRentals->where('status', 'completed')->count() }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-3 bg-yellow-100 rounded-full mr-4">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Cart Items</p>
                    <p class="text-2xl font-bold">{{ $cartCount }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Rentals -->
    <div class="bg-white rounded-lg shadow mb-8">
        <div class="p-6 border-b">
            <h2 class="text-lg font-semibold">Active Rentals</h2>
        </div>
        @if($activeRentals->count() > 0)
            <div class="divide-y">
                @foreach($activeRentals as $rental)
                    <div class="p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <p class="font-semibold">{{ $rental->rental_code }}</p>
                            <p class="text-sm text-gray-600">{{ $rental->start_date->format('d M Y') }} - {{ $rental->end_date->format('d M Y') }}</p>
                        </div>
                        <div class="flex items-center space-x-4 w-full sm:w-auto justify-between sm:justify-start">
                            <span class="px-3 py-1 rounded-full text-sm font-medium
                                @if($rental->status == 'quotation') bg-orange-100 text-orange-800
                                @elseif($rental->status == 'active') bg-green-100 text-green-800
                                @elseif($rental->status == 'expired') bg-gray-100 text-gray-500
                                @else bg-red-100 text-red-800
                                @endif">
                                {{ ucfirst(str_replace('_', ' ', $rental->status)) }}
                            </span>
                            <a href="{{ route('customer.rental.detail', $rental->id) }}" class="text-primary-600 hover:underline">View Details</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-6 text-center text-gray-500">
                No active rentals.
            </div>
        @endif
    </div>

    <!-- Quick Links -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <a href="{{ route('catalog.index') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
            <h3 class="font-semibold mb-2">Browse Catalog</h3>
            <p class="text-sm text-gray-600">Find and rent equipment</p>
        </a>
        <a href="{{ route('customer.rentals') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
            <h3 class="font-semibold mb-2">My Rentals</h3>
            <p class="text-sm text-gray-600">View all rental history</p>
        </a>
        <a href="{{ route('customer.profile') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
            <h3 class="font-semibold mb-2">Profile & Verification</h3>
            <p class="text-sm text-gray-600">Update info & upload documents</p>
        </a>
    </div>
</div>
@endsection