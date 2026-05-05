@extends('layouts.frontend')

@section('title', $computer->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="computerBooking()" x-init="init()">
    <div class="mb-4">
        <a href="{{ route('computers.index') }}" class="text-primary-600 hover:underline text-sm">&larr; Kembali ke daftar komputer</a>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
            <div class="aspect-video bg-gray-100 rounded-lg overflow-hidden">
                @if($computer->image_path)
                    <img src="{{ asset('storage/'.$computer->image_path) }}" alt="{{ $computer->name }}" class="w-full h-full object-cover">
                @else
                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                        <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25"/></svg>
                    </div>
                @endif
            </div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $computer->name }}</h1>
                @if($computer->brand)
                    <p class="text-gray-500">{{ $computer->brand }}</p>
                @endif
                <div class="mt-3">
                    @if($computer->status === \App\Models\Computer::STATUS_AVAILABLE)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium bg-green-100 text-green-800">Tersedia</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-sm font-medium bg-yellow-100 text-yellow-800">Sedang Maintenance</span>
                    @endif
                </div>

                @if(! empty($computer->specs))
                    <h3 class="mt-6 font-semibold text-gray-900">Spesifikasi</h3>
                    <dl class="mt-2 space-y-1 text-sm">
                        @foreach($computer->specs as $key => $value)
                            <div class="flex">
                                <dt class="w-32 text-gray-500">{{ $key }}</dt>
                                <dd class="text-gray-900">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif

                @if($computer->notes)
                    <p class="mt-4 text-sm text-gray-600">{{ $computer->notes }}</p>
                @endif
            </div>
        </div>

        <div class="border-t p-6">
            <h2 class="font-semibold text-gray-900 mb-3">Cek Ketersediaan</h2>
            <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                    <input type="date" x-model="date" min="{{ now()->toDateString() }}" @change="loadSlots()" class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </div>
            </div>

            <div class="mt-4">
                <template x-if="loading">
                    <p class="text-sm text-gray-500">Memuat slot…</p>
                </template>
                <template x-if="! loading && slots.length === 0 && date">
                    <p class="text-sm text-gray-500">Tidak ada slot operasional di tanggal ini.</p>
                </template>
                <div x-show="! loading && slots.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <template x-for="slot in slots" :key="slot.start + slot.end">
                        <button
                            type="button"
                            @click="bookSlot(slot)"
                            :disabled="! slot.available || computerStatus !== 'available'"
                            class="px-3 py-2 rounded-md border text-sm transition"
                            :class="(slot.available && computerStatus === 'available') ? 'border-primary-500 text-primary-700 hover:bg-primary-50' : 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed'">
                            <span x-text="slot.start + ' - ' + slot.end"></span>
                            <span x-show="! slot.available" class="block text-xs">Terisi</span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function computerBooking() {
    return {
        date: '{{ now()->toDateString() }}',
        slots: [],
        loading: false,
        computerStatus: '{{ $computer->status }}',
        init() {
            this.loadSlots();
        },
        async loadSlots() {
            this.loading = true;
            try {
                const res = await fetch('{{ route('computers.availability', $computer) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ date: this.date }),
                });
                const data = await res.json();
                this.slots = data.slots || [];
                this.computerStatus = data.computer_status || this.computerStatus;
            } finally {
                this.loading = false;
            }
        },
        bookSlot(slot) {
            const url = '{{ route('customer.computer-bookings.create', $computer) }}'
                + '?date=' + encodeURIComponent(this.date)
                + '&start=' + encodeURIComponent(slot.start)
                + '&end=' + encodeURIComponent(slot.end);
            window.location.href = url;
        },
    };
}
</script>
@endpush
@endsection
