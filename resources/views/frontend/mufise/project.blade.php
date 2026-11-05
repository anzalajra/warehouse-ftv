@extends('layouts.frontend')
@section('title', 'Proyek Mufise')
@section('content')
<div class="max-w-5xl mx-auto p-4 sm:p-8 space-y-5">
    <div class="flex justify-between items-start gap-3"><div><p class="text-sm text-gray-500">Produksi terhubung</p><h1 class="text-2xl font-bold">{{ $bundle['project']['title'] }}</h1>
    @if($rental)<p class="text-sm text-gray-500 mt-1">Rental {{ $rental->rental_code }} · Akses memakai akun Mufise peminjam.</p>@endif</div><a href="{{ route('customer.mufise.index') }}" class="text-sm underline">Kelola koneksi</a></div>
    @include('frontend.mufise.editor')
</div>
@endsection
