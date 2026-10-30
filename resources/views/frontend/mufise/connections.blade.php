@extends('layouts.frontend')
@section('title', 'Koneksi Mufise')
@section('content')
<div class="max-w-4xl mx-auto px-4 py-8 space-y-6" x-data="mufiseConnection()" x-init="resume()">
    <h1 class="text-2xl font-bold">Koneksi Mufise</h1>
    <p>Hubungkan akun Mufise untuk peminjaman peralatan di luar kelas. Pilih organisasi dan proyek melalui halaman persetujuan Mufise.</p>
    @if(!\App\Services\Mufise\MufiseSettings::enabled())
        <p class="bg-yellow-50 p-4 rounded">Integrasi belum diaktifkan admin.</p>
    @else
        @if($error)<p class="bg-red-50 text-red-700 p-4 rounded">{{ $error }}</p>@endif
        @if($status)
            <div class="bg-white shadow rounded p-5 space-y-2">
                <p class="font-semibold">Akun terhubung</p>
                <p>Organisasi: {{ $status['organization']['name'] ?? '—' }} ({{ $status['organization']['join_code'] ?? '—' }})</p>
                <p>Proyek aktif yang bisa diakses: {{ $status['active_project_count'] ?? 0 }} · Dibagikan ke Warehouse: {{ $status['shared_active_project_count'] ?? 0 }}</p>
                <p>{{ match($status['next_action'] ?? '') {
                    'join_organization' => 'Bergabung atau terima undangan organisasi di Mufise. Persetujuan admin organisasi mungkin diperlukan.',
                    'create_project' => 'Buat proyek aktif di Mufise untuk produksi Anda.',
                    'select_project' => 'Pilih proyek yang akan dibagikan ke Warehouse.',
                    'ready' => 'Akun siap. Kelengkapan proyek akan diperiksa saat pengajuan dan pengambilan.',
                    default => 'Hubungkan ulang untuk memperbarui akses.'
                } }}</p>
            </div>
        @endif
        <div class="flex flex-wrap gap-3">
            <button type="button" @click="connect" :disabled="busy || polling" class="bg-primary-600 text-white rounded px-4 py-2 disabled:opacity-50">{{ $status ? 'Gunakan proyek lain / Buat proyek baru' : 'Connect Mufise Account' }}</button>
            <button type="button" @click="disconnect" :disabled="busy || polling" class="border rounded px-4 py-2">Putus koneksi</button>
            <a href="{{ route('customer.mufise.index') }}" class="border rounded px-4 py-2">Muat ulang</a>
        </div>
        <p x-text="message" role="status" class="text-sm"></p>
        <div x-show="verificationUrl" x-cloak class="bg-blue-50 p-4 rounded space-y-2">
            <a :href="verificationUrl" target="_blank" rel="noopener" class="font-semibold underline">Buka Mufise untuk menyetujui koneksi</a>
            <p>Pemasangan berlaku 10 menit. Setelah disetujui, halaman ini akan dimuat ulang.</p>
        </div>
        @if(count($projects))
            <div class="bg-white rounded shadow p-5 space-y-3">
                <h2 class="font-semibold">Proyek yang dibagikan</h2>
                @foreach($projects as $project)
                    <a class="block text-primary-600 underline" href="{{ route('customer.mufise.project', $project['id']) }}">{{ $project['title'] }}</a>
                @endforeach
            </div>
        @endif
    @endif
    <a href="{{ route('checkout.index') }}" class="inline-block text-primary-600 underline">Kembali ke peminjaman</a>
</div>
<script>
function mufiseConnection() {
    return {
        busy: false, polling: false, message: '', verificationUrl: '', deadline: 0,
        resume() {
            const pending = @json($pending);
            if (!pending) return;
            this.verificationUrl = pending.verification_url;
            this.deadline = Date.parse(pending.expires_at);
            this.message = 'Pemasangan masih menunggu persetujuan di Mufise.';
            this.polling = true;
            setTimeout(() => this.poll(), 5000);
        },
        async send(url) {
            const response = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }});
            const data = await response.json();
            if (!response.ok) { const error = new Error(data.message || 'Permintaan gagal.'); error.retry = response.status === 429 || response.status >= 500; error.wait = Math.max(5, Number(response.headers.get('Retry-After')) || 5); throw error; }
            return data;
        },
        async connect() {
            this.busy = true;
            try {
                const data = await this.send(@json(route('customer.mufise.connect')));
                this.verificationUrl = data.verification_url; this.deadline = Date.parse(data.expires_at);
                this.message = 'Buka tautan Mufise untuk melanjutkan.'; this.polling = true;
                setTimeout(() => this.poll(), 5000);
            } catch (e) { this.message = e.message; }
            finally { this.busy = false; }
        },
        async poll() {
            if (Date.now() >= this.deadline) { this.polling = false; this.message = 'Pemasangan kedaluwarsa. Mulai kembali.'; return; }
            let wait = 5;
            try {
                const data = await this.send(@json(route('customer.mufise.poll')));
                if (data.connected) { location.reload(); return; }
            } catch (e) {
                this.message = e.message;
                if (!e.retry) { this.polling = false; return; }
                wait = e.wait || 5;
            }
            setTimeout(() => this.poll(), wait * 1000);
        },
        async disconnect() {
            this.busy = true;
            try { await this.send(@json(route('customer.mufise.disconnect'))); location.reload(); }
            catch(e) { this.message = e.message; }
            finally { this.busy = false; }
        }
    };
}
</script>
@endsection
