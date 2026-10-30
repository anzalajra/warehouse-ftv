@extends('layouts.frontend')
@section('title', 'Koneksi Mufise')
@section('content')
<div class="max-w-4xl mx-auto p-8 space-y-4">
    <h1 class="text-xl font-bold">Data Mufise belum dapat dibuka</h1>
    <p>{{ $message }}</p>
    <a class="underline" href="{{ route('customer.mufise.index') }}">Pengaturan koneksi akun</a>
</div>
@endsection
