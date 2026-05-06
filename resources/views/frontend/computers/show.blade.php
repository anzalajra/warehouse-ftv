@extends('layouts.frontend')

@section('title', $computer->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="computerBookingWizard()" x-init="init()">
    <div class="mb-4">
        <a href="{{ $computer->room ? route('computers.rooms.show', $computer->room) : route('computers.index') }}" class="text-primary-600 hover:underline text-sm">&larr; Kembali</a>
    </div>

    {{-- Computer detail --}}
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
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
                @if($computer->room)
                    <p class="text-xs text-gray-500 uppercase tracking-wide">{{ $computer->room->name }}</p>
                @endif
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
    </div>

    {{-- Wizard --}}
    @if($computer->status === \App\Models\Computer::STATUS_AVAILABLE)
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="font-semibold text-gray-900 mb-4">Booking</h2>

        {{-- Step indicator --}}
        <ol class="flex items-center w-full mb-6 text-xs sm:text-sm">
            <template x-for="(label, idx) in stepLabels" :key="idx">
                <li class="flex items-center flex-1" :class="idx < stepLabels.length - 1 ? 'after:content-[\'\'] after:flex-1 after:border-t after:border-gray-200 after:mx-2' : ''">
                    <span
                        class="flex items-center justify-center w-7 h-7 rounded-full shrink-0 font-semibold"
                        :class="step > idx + 1 ? 'bg-primary-600 text-white' : (step === idx + 1 ? 'bg-primary-100 text-primary-700 ring-2 ring-primary-600' : 'bg-gray-100 text-gray-400')"
                        x-text="idx + 1"></span>
                    <span class="ml-2 hidden sm:inline" :class="step >= idx + 1 ? 'text-gray-900 font-medium' : 'text-gray-400'" x-text="label"></span>
                </li>
            </template>
        </ol>

        {{-- Step 1: pilih tanggal --}}
        <div x-show="step === 1">
            <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Tanggal</label>
            <input type="date" x-model="date" min="{{ now()->toDateString() }}" @change="onDateChange()"
                class="w-full sm:w-auto rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
            <p class="mt-2 text-xs text-gray-500">Setelah memilih tanggal, slot waktu akan dimuat otomatis.</p>
        </div>

        {{-- Step 2: pilih slot --}}
        <div x-show="step === 2">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm text-gray-600">Tanggal: <span class="font-semibold" x-text="formatDate(date)"></span></p>
                <button type="button" @click="step = 1" class="text-xs text-primary-600 hover:underline">Ubah tanggal</button>
            </div>

            <p class="text-sm text-gray-700 mb-3">Pilih satu atau lebih slot waktu (boleh tidak berurutan):</p>

            <template x-if="loading">
                <p class="text-sm text-gray-500">Memuat slot…</p>
            </template>
            <template x-if="! loading && slots.length === 0">
                <p class="text-sm text-gray-500">Tidak ada slot operasional di tanggal ini.</p>
            </template>

            <div x-show="! loading && slots.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <template x-for="slot in slots" :key="slot.start + slot.end">
                    <button
                        type="button"
                        @click="toggleSlot(slot)"
                        :disabled="! slot.available"
                        class="px-3 py-2 rounded-md border text-sm transition relative"
                        :class="! slot.available ? 'border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed' : (isSelected(slot) ? 'border-primary-600 bg-primary-50 text-primary-800 ring-1 ring-primary-600' : 'border-gray-300 hover:bg-gray-50')">
                        <span class="block font-medium" x-text="slot.start + ' - ' + slot.end"></span>
                        <span x-show="slot.is_night" class="block text-[10px] text-amber-600 font-semibold uppercase">Jam Malam</span>
                        <span x-show="! slot.available" class="block text-xs">Terisi</span>
                    </button>
                </template>
            </div>

            <div class="mt-5 flex justify-between">
                <button type="button" @click="step = 1" class="text-sm text-gray-600 hover:underline">&larr; Kembali</button>
                <button type="button" @click="goToConfirm()" :disabled="selected.length === 0"
                    class="px-4 py-2 rounded-md text-white text-sm font-medium"
                    :class="selected.length === 0 ? 'bg-gray-300 cursor-not-allowed' : 'bg-primary-600 hover:bg-primary-700'">
                    Lanjut &rarr;
                </button>
            </div>
        </div>

        {{-- Step 3: konfirmasi --}}
        <div x-show="step === 3">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-gray-900">Konfirmasi Booking</h3>
                <button type="button" @click="step = 2" class="text-xs text-primary-600 hover:underline">Ubah slot</button>
            </div>

            @auth('customer')
            <form method="POST" action="{{ route('customer.computer-bookings.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="computer_id" value="{{ $computer->id }}">
                <input type="hidden" name="booking_date" :value="date">
                <template x-for="slot in selected" :key="slot.start + slot.end">
                    <div>
                        <input type="hidden" name="slots[][start]" :value="slot.start">
                        <input type="hidden" name="slots[][end]" :value="slot.end">
                    </div>
                </template>

                <dl class="grid grid-cols-2 gap-3 text-sm bg-gray-50 rounded-md p-4">
                    <div>
                        <dt class="text-gray-500">Komputer</dt>
                        <dd class="font-medium">{{ $computer->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Tanggal</dt>
                        <dd class="font-medium" x-text="formatDate(date)"></dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-gray-500 mb-1">Slot terpilih</dt>
                        <dd class="font-medium space-y-1">
                            <template x-for="slot in selected" :key="slot.start + slot.end">
                                <div class="flex items-center gap-2">
                                    <span x-text="slot.start + ' - ' + slot.end"></span>
                                    <span x-show="slot.is_night" class="text-[10px] text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">Jam Malam</span>
                                </div>
                            </template>
                        </dd>
                    </div>
                </dl>

                <template x-if="hasNightSlot">
                    <div class="rounded-md bg-amber-50 border border-amber-300 p-3 text-sm text-amber-900">
                        <p class="font-semibold">⚠ Booking Jam Malam</p>
                        <p>Booking jam malam diwajibkan mengurus perizinan menginap di kampus.</p>
                    </div>
                </template>

                <div>
                    <label for="purpose" class="block text-sm font-medium text-gray-700 mb-1">Penggunaan untuk apa?</label>
                    <textarea name="purpose" id="purpose" rows="3" required
                        placeholder="Contoh: Editing video tugas akhir MK Dokumenter"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('purpose') }}</textarea>
                </div>

                <div class="rounded-md bg-gray-50 p-3 text-xs text-gray-700">
                    <p class="font-semibold mb-1">Syarat &amp; Ketentuan</p>
                    <p class="whitespace-pre-line">{{ \App\Services\ComputerValidationService::tncText() }}</p>
                </div>

                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="tnc" value="1" required class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span>Saya menyetujui Syarat &amp; Ketentuan di atas.</span>
                </label>

                <template x-if="hasNightSlot">
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" name="permit" value="1" required class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <span>Saya sudah memiliki perizinan menginap di kampus.</span>
                    </label>
                </template>

                <div class="pt-2 flex justify-between">
                    <button type="button" @click="step = 2" class="text-sm text-gray-600 hover:underline">&larr; Kembali</button>
                    <button type="submit" class="inline-flex justify-center px-6 py-2 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-md shadow">
                        Submit Booking
                    </button>
                </div>
            </form>
            @else
                <div class="rounded-md bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
                    <p class="font-semibold mb-1">Login diperlukan</p>
                    <p class="mb-3">Silakan login menggunakan akun warehouse untuk melanjutkan booking.</p>
                    <a href="{{ route('customer.login') }}" class="inline-flex px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-md">Login</a>
                </div>
            @endauth
        </div>
    </div>
    @endif
</div>

@if($errors->any())
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 -mt-4">
    <div class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
        <ul class="list-disc pl-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@push('scripts')
<script>
function computerBookingWizard() {
    return {
        step: 1,
        stepLabels: ['Pilih Tanggal', 'Pilih Slot', 'Konfirmasi'],
        date: '{{ now()->toDateString() }}',
        slots: [],
        selected: [],
        loading: false,
        init() {
            // Auto-load if step 2 directly opened
        },
        async onDateChange() {
            await this.loadSlots();
            this.selected = [];
            this.step = 2;
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
            } finally {
                this.loading = false;
            }
        },
        toggleSlot(slot) {
            if (! slot.available) return;
            const idx = this.selected.findIndex(s => s.start === slot.start && s.end === slot.end);
            if (idx >= 0) {
                this.selected.splice(idx, 1);
            } else {
                this.selected.push({ start: slot.start, end: slot.end, is_night: slot.is_night });
                this.selected.sort((a, b) => a.start.localeCompare(b.start));
            }
        },
        isSelected(slot) {
            return this.selected.some(s => s.start === slot.start && s.end === slot.end);
        },
        get hasNightSlot() {
            return this.selected.some(s => s.is_night);
        },
        goToConfirm() {
            if (this.selected.length === 0) return;
            this.step = 3;
        },
        formatDate(dateStr) {
            if (! dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        },
    };
}
</script>
@endpush
@endsection
