@extends('layouts.frontend')
@section('title', 'Koneksi Mufise')
@section('content')
@php
    $connectionConfig = [
        'pending' => $pending,
        'connectUrl' => route('customer.mufise.connect'),
        'pollUrl' => route('customer.mufise.poll'),
        'disconnectUrl' => route('customer.mufise.disconnect'),
    ];
@endphp
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6" x-data="mufiseConnection(@js($connectionConfig))" x-init="resume()">
    <div>
        <a href="{{ route('checkout.index') }}" class="inline-flex items-center text-sm font-medium text-primary-600 hover:text-primary-700 mb-4">← Kembali ke Checkout</a>
        <h1 class="text-2xl font-bold text-gray-900">Koneksi Mufise</h1>
        <p class="text-sm text-gray-500 mt-2">Hubungkan akun Mufise untuk melengkapi proyek peminjaman peralatan di luar kelas.</p>
    </div>
    @if(!\App\Services\Mufise\MufiseSettings::enabled())
        <div role="status" class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-lg text-sm">Integrasi Mufise belum diaktifkan oleh admin.</div>
    @else
        @if($error)<div role="alert" class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg text-sm">{{ $error }}</div>@endif
        <section class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-5 sm:p-6 space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-gray-900">Akun Mufise</h2>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $status ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $status ? 'Akun terhubung' : 'Belum terhubung' }}</span>
                </div>
                @if($status)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500 mb-1">Organisasi tujuan</p>
                            <p class="font-semibold text-gray-900">{{ $status['organization']['name'] ?? '—' }}</p>
                            @if(!empty($status['organization']['join_code']))<p class="text-gray-500 mt-1">Kode: {{ $status['organization']['join_code'] }}</p>@endif
                        </div>
                        <div>
                            <p class="text-gray-500 mb-1">Proyek aktif</p>
                            <p class="font-semibold text-gray-900">{{ $status['active_project_count'] ?? 0 }} proyek · {{ $status['shared_active_project_count'] ?? 0 }} dibagikan ke Warehouse</p>
                        </div>
                    </div>
                    <div role="status" class="bg-primary-50 border border-primary-100 rounded-lg p-4 text-sm text-primary-800">{{ match($status['next_action'] ?? '') {
                        'join_organization' => 'Bergabung atau terima undangan organisasi di Mufise untuk melanjutkan. Persetujuan admin organisasi mungkin diperlukan.',
                        'create_project' => 'Buat proyek aktif di Mufise untuk produksi Anda, lalu bagikan ke Warehouse.',
                        'select_project' => 'Pilih proyek yang akan dibagikan ke Warehouse melalui tombol di bawah.',
                        'ready' => 'Akun siap digunakan. Pilih proyek di checkout untuk melanjutkan booking.',
                        default => 'Hubungkan ulang akun Mufise untuk memperbarui akses.'
                    } }}</div>
                @else
                    <p class="text-sm text-gray-600 leading-relaxed">Klik tombol di bawah untuk membuka Mufise di tab baru. Masuk atau daftar akun Mufise, lalu pilih proyek yang akan digunakan.</p>
                @endif
                <div class="flex flex-col sm:flex-row flex-wrap gap-3">
                    <button type="button" @click="connect()" :disabled="busy || polling" class="inline-flex items-center justify-center gap-2 bg-primary-600 text-white rounded-lg px-5 py-3 text-sm font-semibold hover:bg-primary-700 transition disabled:opacity-50 disabled:cursor-wait">
                        <svg x-show="busy || polling" x-cloak class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        <span>{{ $status ? 'Gunakan proyek lain / Buat proyek baru' : 'Connect Mufise Account' }}</span>
                    </button>
                    @if($status)
                        <button type="button" @click="disconnect()" :disabled="busy || polling" class="inline-flex items-center justify-center border border-gray-300 text-gray-700 rounded-lg px-5 py-3 text-sm font-semibold hover:bg-gray-50 transition disabled:opacity-50 disabled:cursor-wait">Putus Koneksi</button>
                    @endif
                </div>
                <p class="text-xs text-gray-500 leading-relaxed">Pendaftaran dan koneksi mengikuti organisasi yang ditentukan admin Warehouse. Lengkapi verifikasi di Mufise bila diminta.</p>
                <div x-show="message" x-cloak role="status" aria-live="polite" class="rounded-lg border p-4 text-sm" :class="failed ? 'bg-red-50 border-red-200 text-red-700' : 'bg-primary-50 border-primary-100 text-primary-800'">
                    <p class="font-medium" x-text="message"></p>
                    <div x-show="verificationUrl" class="mt-2 space-y-2">
                        <p>Selesaikan persetujuan di tab Mufise. Halaman ini akan diperbarui otomatis setelah akun terhubung. Tautan berlaku hingga 10 menit.</p>
                        <a :href="verificationUrl" target="_blank" rel="noopener noreferrer" class="inline-block font-semibold underline underline-offset-2">Halaman Mufise tidak muncul, klik di sini</a>
                    </div>
                </div>
            </div>
        </section>
        @if(count($projects))
            <section class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900">Proyek yang Dibagikan</h2>
                <p class="text-sm text-gray-500 mt-1 mb-4">Tinjau kru, lokasi, dan call sheet sebelum mengajukan booking.</p>
                <div class="space-y-3">
                    @foreach($projects as $project)
                        <a class="flex items-center justify-between gap-3 border border-gray-200 rounded-lg px-4 py-3 hover:border-primary-300 hover:bg-primary-50 transition" href="{{ route('customer.mufise.project', $project['id']) }}">
                            <span class="font-medium text-gray-900 break-words min-w-0">{{ $project['title'] }}</span>
                            <span class="text-sm font-semibold text-primary-600 shrink-0">Lihat Proyek →</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
