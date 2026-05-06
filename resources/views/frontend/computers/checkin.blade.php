@extends('layouts.frontend')

@section('title', 'Check-in '.$computer->name)

@push('scripts')
<script>
// Web heartbeat — Mac kiosk mode (Safari/Chrome). Electron app punya heartbeat sendiri di main process,
// jadi page ini cuma kirim heartbeat untuk komputer non-Electron. Server-side last_heartbeat_data.source
// akan tertulis 'web' atau 'electron' tergantung mana yang terakhir update.
(function () {
    const url = @json(route('api.kiosk.heartbeat.web', $computer->checkin_slug));
    const startTime = Date.now();
    let interval = 30_000; // default; server akan koreksi via response

    function send() {
        const payload = JSON.stringify({
            uptime_seconds: Math.round((Date.now() - startTime) / 1000),
        });
        // Pakai fetch agar bisa baca response (sendBeacon tidak return body).
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: payload,
            keepalive: true,
        }).then(r => r.ok ? r.json() : null).then(data => {
            if (data && data.heartbeat_interval && data.heartbeat_interval * 1000 !== interval) {
                interval = Math.max(10_000, data.heartbeat_interval * 1000);
                clearInterval(timer);
                timer = setInterval(send, interval);
            }
        }).catch(() => { /* swallow */ });
    }

    let timer = setInterval(send, interval);
    send(); // immediate first beat
})();
</script>
@endpush

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-lg shadow overflow-hidden">
        {{-- Header: computer info --}}
        <div class="bg-gradient-to-r from-primary-600 to-primary-700 text-white p-6">
            @if($computer->room)
                <p class="text-xs uppercase tracking-wider opacity-80">{{ $computer->room->name }}</p>
            @endif
            <h1 class="text-3xl font-bold">{{ $computer->name }}</h1>
            @if($computer->brand)
                <p class="mt-1 opacity-90">{{ $computer->brand }}</p>
            @endif
            <div class="mt-3 flex flex-wrap gap-3 text-sm">
                @if(! empty($computer->specs))
                    @foreach(array_slice($computer->specs, 0, 4, true) as $key => $value)
                        <span class="bg-white/15 backdrop-blur px-2 py-1 rounded">{{ $key }}: {{ $value }}</span>
                    @endforeach
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="m-4 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="m-4 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Main row: QR + check-in panel --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-b">
            {{-- QR --}}
            <div class="flex flex-col items-center text-center">
                <p class="text-sm text-gray-500 mb-2">Scan dari HP untuk membuka halaman ini</p>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&data={{ urlencode($checkinUrl) }}" alt="QR Check-in" class="rounded-lg border border-gray-200">
                <p class="mt-3 text-xs text-gray-400 break-all">{{ $checkinUrl }}</p>
            </div>

            {{-- Check-in panel --}}
            <div class="flex flex-col justify-center">
                @php
                    $now = \Carbon\Carbon::now();
                    $authUserId = auth('customer')->id();
                    $userBookingNow = $activeBooking && $activeBooking->user_id === $authUserId ? $activeBooking : null;
                @endphp

                @if($activeBooking)
                    <p class="text-sm text-gray-500">Booking saat ini:</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $activeBooking->user->name }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ $activeBooking->start_time }} - {{ $activeBooking->end_time }}
                        @if($activeBooking->checked_in_at)
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Sudah check-in</span>
                        @endif
                    </p>

                    @if(! $activeBooking->checked_in_at)
                        <form method="POST" action="{{ route('kiosk.checkin.submit', $computer->checkin_slug) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="w-full px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white text-lg font-semibold rounded-lg shadow">
                                Check-in
                            </button>
                        </form>
                        @guest('customer')
                            <p class="mt-2 text-xs text-gray-500">* Anda akan diminta login akun warehouse jika belum.</p>
                        @endguest
                    @else
                        <p class="mt-4 text-sm text-green-700">Check-in pada {{ $activeBooking->checked_in_at->format('H:i') }}</p>
                    @endif
                @else
                    <p class="text-sm text-gray-500">Booking saat ini:</p>
                    <p class="text-lg font-semibold text-gray-700 mt-1">Tidak ada booking di sesi ini</p>
                    @if($currentSession)
                        <p class="text-sm text-gray-600 mt-2">Sesi sekarang: {{ $currentSession['start'] }} - {{ $currentSession['end'] }} @if($currentSession['is_night']) <span class="text-amber-600 font-medium">(Jam Malam)</span> @endif</p>
                        <p class="text-sm text-gray-600 mt-1">Silakan check-in dan isi data — akun warehouse Anda akan otomatis mengisi slotnya.</p>
                        <form method="POST" action="{{ route('kiosk.checkin.submit', $computer->checkin_slug) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="w-full px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white text-lg font-semibold rounded-lg shadow">
                                Check-in &amp; Isi Data
                            </button>
                        </form>
                    @else
                        <p class="text-sm text-gray-600 mt-2">Bukan jam operasional. Walk-in check-in tidak tersedia.</p>
                    @endif
                @endif
            </div>
        </div>

        {{-- Today's bookings --}}
        <div class="p-6">
            <h2 class="font-semibold text-gray-900 mb-3">Booking Hari Ini</h2>
            @if($todaysBookings->isEmpty())
                <p class="text-sm text-gray-500">Belum ada booking hari ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Waktu</th>
                                <th class="px-3 py-2 text-left font-medium">Mahasiswa</th>
                                <th class="px-3 py-2 text-left font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($todaysBookings as $b)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $b->start_time }} - {{ $b->end_time }}</td>
                                    <td class="px-3 py-2">{{ $b->user->name }}</td>
                                    <td class="px-3 py-2">
                                        @php
                                            $colors = [
                                                'confirmed' => 'bg-blue-100 text-blue-800',
                                                'active' => 'bg-green-100 text-green-800',
                                                'completed' => 'bg-purple-100 text-purple-800',
                                                'cancelled' => 'bg-gray-100 text-gray-700',
                                                'no_show' => 'bg-red-100 text-red-800',
                                            ];
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $colors[$b->status] ?? 'bg-gray-100' }}">{{ ucfirst(str_replace('_',' ', $b->status)) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
