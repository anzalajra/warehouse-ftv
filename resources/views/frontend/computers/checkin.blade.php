@extends('layouts.kiosk')

@section('title', 'Check-in '.$computer->name)

@push('scripts')
<script>
(function () {
    const url = @json(route('api.kiosk.heartbeat.web', $computer->checkin_slug));
    const startTime = Date.now();
    let interval = 30_000;
    let timer;

    function send() {
        const payload = JSON.stringify({
            uptime_seconds: Math.round((Date.now() - startTime) / 1000),
        });
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
        }).catch(() => {});
    }

    timer = setInterval(send, interval);
    send();
})();

// Auto-refresh every 30s so booking changes (other-checkin override, no-show flips) surface
setTimeout(() => window.location.reload(), 30_000);
</script>
@endpush

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="kioskShell()" x-init="init()">
    {{-- Network status bar --}}
    <div x-show="status !== 'online'"
         class="mb-3 rounded-lg px-4 py-2 text-sm flex items-center justify-between"
         :class="{
             'bg-amber-50 border border-amber-200 text-amber-800': status === 'connecting',
             'bg-red-50 border border-red-200 text-red-800': status === 'offline'
         }">
        <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full"
                  :class="{ 'bg-amber-500 animate-pulse': status === 'connecting', 'bg-red-500': status === 'offline' }"></span>
            <span x-text="status === 'connecting' ? 'Menyambungkan ke server…' : 'Tidak ada koneksi internet'"></span>
            <span x-show="queueSize > 0" class="ml-2 text-xs" x-text="`(${queueSize} event tertunda)`"></span>
        </div>
        <button x-show="hasBridge && status === 'offline'" @click="openWifi()"
                class="text-xs underline hover:no-underline">Pilih WiFi</button>
    </div>

    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-primary-600 to-primary-700 text-white p-6 sm:p-8">
            @if($computer->room)
                <p class="text-xs uppercase tracking-wider opacity-80">{{ $computer->room->name }}</p>
            @endif
            <h1 class="text-3xl sm:text-4xl font-bold">{{ $computer->name }}</h1>
            @if($computer->brand)
                <p class="mt-1 opacity-90">{{ $computer->brand }}</p>
            @endif
        </div>

        @if(session('success'))
            <div class="mx-6 mt-6 rounded-md bg-green-50 border border-green-200 p-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mx-6 mt-6 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Booking panel --}}
        <div class="p-6 sm:p-8">
            @if($activeBooking)
                <p class="text-sm text-gray-500 uppercase tracking-wider">Komputer ini sudah dibooking</p>
                <p class="text-3xl font-bold text-gray-900 mt-2">{{ $activeBooking->user->name }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                        {{ $activeBooking->start_time }} - {{ $activeBooking->end_time }}
                    </span>
                    @if($isLate)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Telat — Risiko No-Show
                        </span>
                    @endif
                    @if($activeBooking->checked_in_at)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            Sudah check-in {{ $activeBooking->checked_in_at->format('H:i') }}
                        </span>
                    @endif
                </div>

                <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @if(! $activeBooking->checked_in_at)
                        <form method="POST" action="{{ route('kiosk.checkin.submit', $computer->checkin_slug) }}">
                            @csrf
                            <button type="submit" class="w-full px-6 py-4 bg-primary-600 hover:bg-primary-700 text-white text-lg font-semibold rounded-xl shadow-sm transition">
                                Check-in
                            </button>
                        </form>
                    @else
                        <a href="{{ route('kiosk.timer', $computer->checkin_slug) }}?booking={{ $activeBooking->id }}"
                           class="w-full inline-flex justify-center items-center px-6 py-4 bg-primary-600 hover:bg-primary-700 text-white text-lg font-semibold rounded-xl shadow-sm transition">
                            Lanjut ke Timer
                        </a>
                    @endif
                    <a href="{{ route('kiosk.checkin.other', $computer->checkin_slug) }}"
                       class="w-full inline-flex justify-center items-center px-6 py-4 bg-white border-2 border-gray-300 hover:border-gray-400 text-gray-800 text-lg font-semibold rounded-xl transition">
                        Orang lain? Check-in di sini
                    </a>
                </div>
            @else
                <p class="text-sm text-gray-500 uppercase tracking-wider">Tidak ada booking aktif</p>
                <p class="text-2xl font-semibold text-gray-700 mt-2">Silakan check-in walk-in</p>
                @if($currentSession)
                    <p class="text-sm text-gray-600 mt-1">
                        Sesi sekarang: {{ $currentSession['start'] }} - {{ $currentSession['end'] }}
                        @if($currentSession['is_night']) <span class="text-amber-600 font-medium">(Jam Malam)</span> @endif
                    </p>
                @endif
                <div class="mt-6">
                    <a href="{{ route('kiosk.checkin.other', $computer->checkin_slug) }}"
                       class="inline-flex items-center px-6 py-4 bg-primary-600 hover:bg-primary-700 text-white text-lg font-semibold rounded-xl shadow-sm transition">
                        Check-in Walk-in
                    </a>
                </div>
            @endif
        </div>

        {{-- Offline walk-in form (only when offline + electron) --}}
        <div x-show="hasBridge && status === 'offline'" class="border-t bg-amber-50 p-6 sm:p-8">
            <h3 class="font-semibold text-amber-900">Mode Offline — Walk-in</h3>
            <p class="text-sm text-amber-800 mt-1">Tidak ada koneksi. Kamu bisa walk-in dengan isi nama saja. Data akan tersinkron saat online.</p>
            <form @submit.prevent="submitOffline()" class="mt-4 space-y-3">
                <input type="text" x-model="offlineName" required placeholder="Nama lengkap"
                       class="w-full px-3 py-2 border-2 border-amber-300 focus:border-amber-500 focus:ring-0 rounded-lg">
                <input type="text" x-model="offlinePurpose" required placeholder="Kegunaan (mis. editing tugas)"
                       class="w-full px-3 py-2 border-2 border-amber-300 focus:border-amber-500 focus:ring-0 rounded-lg">
                <button type="submit" :disabled="offlineSubmitting"
                        class="w-full px-4 py-3 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white font-semibold rounded-lg">
                    <span x-show="!offlineSubmitting">Check-in Offline</span>
                    <span x-show="offlineSubmitting">Menyimpan…</span>
                </button>
            </form>
        </div>

        {{-- Today's bookings --}}
        <div class="border-t bg-gray-50 px-6 py-5 sm:px-8">
            <h2 class="font-semibold text-gray-900 mb-3">Booking Hari Ini</h2>
            @if($todaysBookings->isEmpty())
                <p class="text-sm text-gray-500">Belum ada booking hari ini.</p>
            @else
                <div class="space-y-2">
                    @foreach($todaysBookings as $b)
                        @php
                            $colors = [
                                'confirmed' => 'bg-blue-100 text-blue-800',
                                'active' => 'bg-green-100 text-green-800',
                                'completed' => 'bg-gray-100 text-gray-700',
                                'cancelled' => 'bg-gray-100 text-gray-500',
                                'no_show' => 'bg-red-100 text-red-800',
                                'overridden' => 'bg-amber-100 text-amber-800',
                            ];
                        @endphp
                        <div class="flex items-center justify-between bg-white rounded-lg border px-3 py-2">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-xs text-gray-500">{{ $b->start_time }} - {{ $b->end_time }}</span>
                                <span class="text-sm font-medium text-gray-900">{{ $b->user->name }}</span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $colors[$b->status] ?? 'bg-gray-100' }}">
                                {{ ucfirst(str_replace('_',' ', $b->status)) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Power footer (electron only) --}}
    <div x-show="hasBridge" class="mt-6 flex justify-center gap-3">
        <button @click="sleep()" class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 rounded-lg text-sm font-medium text-gray-700">
            Sleep
        </button>
        <button @click="shutdown()" class="px-4 py-2 bg-red-50 border border-red-200 hover:bg-red-100 rounded-lg text-sm font-medium text-red-700">
            Shutdown
        </button>
    </div>
</div>

<script>
function kioskShell() {
    return {
        status: 'connecting',
        queueSize: 0,
        hasBridge: !!window.kioskBridge,
        offlineName: '',
        offlinePurpose: '',
        offlineSubmitting: false,

        async init() {
            if (!this.hasBridge) {
                this.status = navigator.onLine ? 'online' : 'offline';
                window.addEventListener('online', () => this.status = 'online');
                window.addEventListener('offline', () => this.status = 'offline');
                return;
            }
            this.status = await window.kioskBridge.getNetworkStatus();
            window.kioskBridge.onNetworkChange((s) => { this.status = s; this.refreshQueue(); });
            this.refreshQueue();
            setInterval(() => this.refreshQueue(), 10_000);
        },

        async refreshQueue() {
            if (!this.hasBridge) return;
            this.queueSize = await window.kioskBridge.getQueueSize();
        },

        async submitOffline() {
            if (!this.hasBridge) return;
            this.offlineSubmitting = true;
            await window.kioskBridge.queueOfflineCheckin({
                name: this.offlineName,
                purpose: this.offlinePurpose,
                started_at: new Date().toISOString(),
            });
            this.offlineSubmitting = false;
            this.offlineName = '';
            this.offlinePurpose = '';
            this.refreshQueue();
            alert('Tersimpan offline. Akan disinkronkan saat online.');
        },

        sleep() { if (this.hasBridge) window.kioskBridge.sleep(); },
        shutdown() {
            if (!this.hasBridge) return;
            if (confirm('Yakin ingin shutdown komputer?')) window.kioskBridge.shutdown();
        },
        openWifi() { if (this.hasBridge) window.kioskBridge.openWifiSettings(); },
    };
}
</script>
@endsection
