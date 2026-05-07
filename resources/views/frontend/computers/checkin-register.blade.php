@extends('layouts.kiosk')

@section('title', 'Daftar Akun - '.$computer->name)

@push('scripts')
<script>
// Polling supaya kalau registrasi via HP berhasil, kiosk auto-refresh ke timer page
(function () {
    const slug = @json($computer->checkin_slug);
    setInterval(() => {
        fetch(`/kiosk/checkin/${slug}`, { redirect: 'manual' })
            .then(() => window.location.href = `/kiosk/checkin/${slug}`)
            .catch(() => {});
    }, 8000);
})();
</script>
@endpush

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-amber-500 to-amber-600 text-white p-6">
            <h1 class="text-2xl font-bold">Email belum terdaftar</h1>
            <p class="opacity-90 text-sm">{{ $email }}</p>
        </div>
        <div class="p-8 text-center">
            <p class="text-gray-700 mb-4">Kamu harus punya akun warehouse. Silakan daftar dulu — scan QR di bawah dengan HP, isi data singkat, dan check-in akan otomatis dilakukan untuk komputer ini.</p>

            <div class="flex justify-center my-6">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data={{ urlencode($registerUrl) }}"
                     alt="QR Daftar" class="rounded-lg border border-gray-200 w-64 h-64">
            </div>

            <p class="text-xs text-gray-500 break-all">{{ $registerUrl }}</p>

            <div class="mt-8">
                <a href="{{ route('kiosk.checkin', $computer->checkin_slug) }}"
                   class="inline-flex items-center px-4 py-2 bg-white border-2 border-gray-300 hover:border-gray-400 text-gray-800 font-medium rounded-lg transition">
                    Kembali
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
