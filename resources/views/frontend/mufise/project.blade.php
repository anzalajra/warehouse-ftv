@extends('layouts.frontend')
@section('title', 'Proyek Mufise')
@section('content')
@php
    $editable = ($bundle['permissions']['can_edit'] ?? false) && (!$rental || in_array($rental->status, ['quotation', 'confirmed', 'late_pickup'], true));
@endphp
<div class="max-w-5xl mx-auto p-4 sm:p-8 space-y-6">
    <h1 class="text-2xl font-bold">{{ $bundle['project']['title'] }}</h1>
    @if($rental)<p>Rental {{ $rental->rental_code }} · Data memakai akses Mufise milik peminjam.</p>@endif
    @if(session('success'))<p class="bg-green-50 p-3 rounded">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="bg-red-50 p-3 rounded" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        <p>Jika hasil pengiriman belum diketahui, kirim ulang formulir yang sama. Jika data berubah atau perlu dikoreksi, muat ulang dahulu untuk membuat operasi baru.</p>
    </div>@endif
    <a href="{{ url()->current() }}" class="underline">Muat ulang data</a>
    <div class="bg-white rounded shadow p-5 space-y-2">
        <h2 class="font-semibold">Kesiapan peminjaman</h2>
        <p>Pengajuan: {{ ($bundle['readiness']['can_submit'] ?? false) ? 'Lengkap' : 'Belum lengkap' }}</p>
        <p>Pengambilan: {{ ($bundle['readiness']['can_collect'] ?? false) ? 'Lengkap' : 'Belum lengkap' }}</p>
        <p class="text-sm">Perlu proyek aktif, produser atau sutradara, dan lokasi bernama serta beralamat. Pengambilan juga memerlukan call sheet Day 1 yang terkunci dan akses Plan.</p>
        <p class="text-xs text-gray-500">Diperiksa: {{ $bundle['checked_at'] ?? '—' }} · Revisi jadwal: {{ $bundle['schedule_revision'] ?? '—' }}</p>
    </div>
    @foreach(['crew' => 'Kru', 'locations' => 'Lokasi'] as $kind => $label)
        <section class="bg-white rounded shadow p-5 space-y-4">
            <h2 class="text-lg font-semibold">{{ $label }}</h2>
            @forelse($bundle[$kind] ?? [] as $entry)
                <div class="border rounded p-4 space-y-2">
                    <p class="font-semibold">{{ $entry['name'] }} {{ $kind === 'crew' ? ' · '.($entry['role'] ?? '') : '' }}</p>
                    @if($kind === 'locations')<p>{{ $entry['address'] ?? '' }}</p>@endif
                    @if($editable)
                        <details><summary class="cursor-pointer underline">Edit {{ strtolower($label) }}</summary>
                            @include('frontend.mufise.mutation-form', ['target' => $entry, 'formKey' => $kind.':'.$entry['id']])
                        </details>
                    @endif
                </div>
            @empty<p>Belum ada {{ strtolower($label) }}.</p>@endforelse
            @if($editable)
                <details><summary class="cursor-pointer underline">Tambah {{ strtolower($label) }}</summary>
                    @include('frontend.mufise.mutation-form', ['target' => null, 'formKey' => $kind.':new'])
                </details>
                @if($kind === 'crew')
                    <details><summary class="cursor-pointer underline">Tugaskan personel Mufise yang sudah ada</summary>
                        <form method="POST" action="{{ route($base.'.mutate', $params) }}" class="space-y-3 mt-4">
                            @csrf
                            <input type="hidden" name="operation_id" value="{{ old('form_key') === 'crew:assign' ? old('operation_id') : (string) \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="form_key" value="crew:assign">
                            <input type="hidden" name="kind" value="crew">
                            <label class="block">Person ID Mufise<input type="text" name="person_id" required value="{{ old('form_key') === 'crew:assign' ? old('person_id') : '' }}" class="block border rounded p-2 w-full"></label>
                            <p class="text-sm">Gunakan ID personel yang sudah diketahui dari Mufise. Warehouse tidak menyediakan pencarian direktori organisasi.</p>
                            <button type="submit" class="bg-primary-600 text-white rounded px-4 py-2">Tugaskan ke proyek</button>
                        </form>
                    </details>
                @endif
            @endif
        </section>
    @endforeach
    <section class="bg-white rounded shadow p-5 space-y-4">
        <h2 class="font-semibold">Call sheet</h2>
        @forelse($bundle['call_sheets'] ?? [] as $sheet)
            @if($sheet['exists'])
                <details><summary class="cursor-pointer">Day {{ $sheet['day_number'] }} · {{ $sheet['date'] }} · {{ $sheet['locked'] ? 'Terkunci' : 'Draf' }}</summary>
                    <iframe loading="lazy" sandbox title="Call sheet Day {{ $sheet['day_number'] }}" src="{{ route($base.'.sheet', array_merge($params, ['day' => $sheet['day_id']])) }}" class="w-full border rounded" style="height: 75vh"></iframe>
                </details>
            @endif
        @empty<p>Call sheet belum tersedia atau akses Plan belum diizinkan.</p>@endforelse
    </section>
    <a href="{{ route('customer.mufise.index') }}" class="underline">Pengaturan koneksi akun Mufise</a>
</div>
@endsection
