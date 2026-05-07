@extends('layouts.kiosk')

@section('title', 'Timer Aktif - '.$computer->name)

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-600 to-primary-800 text-white"
     x-data="kioskTimer()" x-init="init()">
    <div class="text-center px-4">
        <p class="text-sm uppercase tracking-wider opacity-80">{{ $computer->name }}</p>
        <h1 class="text-3xl font-semibold mt-2">Hi, {{ $booking->user->name }}</h1>
        <div class="mt-8 text-7xl sm:text-8xl font-mono font-bold tabular-nums" x-text="display">00:00:00</div>
        <p class="mt-3 text-sm opacity-80">Sesi {{ $booking->start_time }} - {{ $booking->end_time }}</p>

        <div class="mt-12 flex flex-col sm:flex-row gap-3 justify-center">
            <button type="button" @click="logout()" :disabled="loggingOut"
                    class="px-8 py-4 bg-white text-primary-700 hover:bg-gray-100 disabled:opacity-50 text-lg font-semibold rounded-xl shadow-lg transition">
                <span x-show="!loggingOut">Logout</span>
                <span x-show="loggingOut">Menyimpan…</span>
            </button>
            <button type="button" @click="sleep()"
                    class="px-8 py-4 bg-white/20 hover:bg-white/30 border border-white/30 text-white text-lg font-semibold rounded-xl shadow-lg transition">
                Sleep
            </button>
            <button type="button" @click="shutdown()"
                    class="px-8 py-4 bg-red-500 hover:bg-red-600 text-white text-lg font-semibold rounded-xl shadow-lg transition">
                Shutdown
            </button>
        </div>
    </div>
</div>

<script>
function kioskTimer() {
    return {
        startedAt: @json(($booking->actual_started_at ?? $booking->checked_in_at ?? now())->toIso8601String()),
        display: '00:00:00',
        timer: null,
        loggingOut: false,
        bookingId: {{ $booking->id }},
        slug: @json($computer->checkin_slug),

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);

            if (window.kioskBridge && typeof window.kioskBridge.enterTimerMode === 'function') {
                window.kioskBridge.enterTimerMode({
                    bookingId: this.bookingId,
                    userName: @json($booking->user?->name ?? $booking->offline_walkin_name ?? 'User'),
                });
            }
        },

        tick() {
            const elapsed = Math.max(0, Math.floor((Date.now() - new Date(this.startedAt).getTime()) / 1000));
            const h = String(Math.floor(elapsed / 3600)).padStart(2, '0');
            const m = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
            const s = String(elapsed % 60).padStart(2, '0');
            this.display = `${h}:${m}:${s}`;
        },

        async logout() {
            this.loggingOut = true;
            const endedAt = new Date().toISOString();
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            try {
                const res = await fetch(`/kiosk/checkin/${this.slug}/logout`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `booking_id=${this.bookingId}`,
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                if (window.kioskBridge) window.kioskBridge.exitTimerMode();
                window.location.href = `/kiosk/checkin/${this.slug}`;
            } catch (e) {
                // Offline — queue and exit timer mode
                if (window.kioskBridge && window.kioskBridge.queueOfflineLogout) {
                    await window.kioskBridge.queueOfflineLogout({
                        booking_id: this.bookingId,
                        started_at: this.startedAt,
                        ended_at: endedAt,
                    });
                    window.kioskBridge.exitTimerMode();
                    window.location.href = `/kiosk/checkin/${this.slug}`;
                } else {
                    alert('Gagal logout: ' + e.message);
                    this.loggingOut = false;
                }
            }
        },

        sleep() {
            if (window.kioskBridge) window.kioskBridge.sleep();
        },
        shutdown() {
            if (window.kioskBridge && confirm('Yakin shutdown?')) window.kioskBridge.shutdown();
            else if (!window.kioskBridge) alert('Shutdown hanya tersedia di kiosk Electron app.');
        },
    };
}
</script>
@endsection
