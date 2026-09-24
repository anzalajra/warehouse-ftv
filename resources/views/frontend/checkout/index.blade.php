@extends('layouts.frontend')

@section('title', 'Checkout')

@section('content')
@php
    $itineraryHasErrors = collect($errors->keys())->contains(fn ($key) => in_array($key, ['usage_type', 'outside_purpose', 'production_name'])
        || str_starts_with($key, 'shooting_locations')
        || str_starts_with($key, 'shooting_crew'));
    $initialLocations = old('shooting_locations') ?: [['location_name' => '', 'start_at' => '', 'end_at' => '']];
    $initialCrew = old('shooting_crew') ?: [['name' => '', 'nim' => '', 'role' => '']];
@endphp
<style>
    [x-cloak] { display: none !important; }
    .terms-modal { opacity: 0; transition: opacity 220ms ease-out; }
    .terms-modal.is-open { opacity: 1; }
    .terms-modal__panel {
        transform: translateY(16px) scale(0.96);
        opacity: 0;
        transition: transform 260ms cubic-bezier(0.16, 1, 0.3, 1), opacity 220ms ease-out;
    }
    .terms-modal.is-open .terms-modal__panel {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
</style>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <h1 class="text-2xl font-bold mb-8">Checkout</h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Checkout Form -->
        <form action="{{ route('checkout.process') }}" method="POST" class="contents" x-data="shootingCheckout()"
            @submit="if (step !== 2) { $event.preventDefault(); nextStep(); }">
            @csrf
            <div :class="step === 1 ? 'lg:col-span-3' : 'lg:col-span-2'">
                <div class="flex items-center gap-3 mb-6 text-sm font-semibold">
                    <span :class="step === 1 ? 'bg-primary-600 text-white' : 'bg-green-600 text-white'" class="rounded-full w-8 h-8 flex items-center justify-center">1</span>
                    <span>Penggunaan & Itinerary</span>
                    <span class="text-gray-400">→</span>
                    <span :class="step === 2 ? 'bg-primary-600 text-white' : 'bg-gray-200 text-gray-500'" class="rounded-full w-8 h-8 flex items-center justify-center">2</span>
                    <span :class="step === 2 ? 'text-gray-900' : 'text-gray-500'">Tinjau Booking</span>
                </div>

                <div id="shooting_step" x-show="step === 1" x-cloak>
                <!-- Customer Info -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-2">Jenis Penggunaan</h2>
                    <p class="text-sm text-gray-500 mb-4">Pilih tujuan penggunaan peralatan untuk booking ini.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-3 border rounded-lg p-4 cursor-pointer" :class="usageType === 'in_class' ? 'border-primary-600 bg-primary-50' : 'border-gray-200'">
                            <input type="radio" name="usage_type" value="in_class" x-model="usageType" required class="mt-1">
                            <span><strong class="block">Akademik dalam kelas</strong><span class="text-sm text-gray-500">Penggunaan untuk kegiatan pembelajaran di kelas.</span></span>
                        </label>
                        <label class="flex items-start gap-3 border rounded-lg p-4 cursor-pointer" :class="usageType === 'outside_class' ? 'border-primary-600 bg-primary-50' : 'border-gray-200'">
                            <input type="radio" name="usage_type" value="outside_class" x-model="usageType" required class="mt-1">
                            <span><strong class="block">Shooting di luar kelas</strong><span class="text-sm text-gray-500">Tugas harian, UTS, UAS, atau Tugas Akhir.</span></span>
                        </label>
                    </div>
                    @error('usage_type')<p class="text-red-500 text-xs mt-2">{{ $message }}</p>@enderror
                    <div x-show="usageType === 'outside_class'" x-cloak class="mt-5">
                        <label for="outside_purpose" class="block text-sm font-medium text-gray-700 mb-1">Jenis Tugas <span class="text-red-500">*</span></label>
                        <select id="outside_purpose" name="outside_purpose" x-model="outsidePurpose" :disabled="usageType !== 'outside_class'" :required="usageType === 'outside_class'" class="w-full border rounded-lg px-3 py-2">
                            <option value="">Pilih jenis tugas</option>
                            @foreach(\App\Models\Rental::outsidePurposeOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('outside_purpose')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-4">Customer Information</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <p class="text-gray-900">{{ $customer->name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <p class="text-gray-900">{{ $customer->email }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <p class="text-gray-900">{{ $customer->phone ?? '-' }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <p class="text-gray-900">{{ $customer->address ?? '-' }}</p>
                        </div>
                    </div>
                    <a href="{{ route('customer.profile') }}" class="text-primary-600 text-sm hover:underline mt-2 inline-block">Update Profile</a>
                </div>

                <fieldset :disabled="usageType !== 'outside_class'" x-show="usageType === 'outside_class'" x-cloak class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-2">Shooting Itinerary</h2>
                    <p class="text-sm text-gray-500 mb-6">Isi rencana shooting dan crew inti untuk booking ini.</p>

                    <div class="mb-7">
                        <label for="production_name" class="block text-sm font-medium text-gray-700 mb-1">Nama Produksi / Film <span class="text-red-500">*</span></label>
                        <input id="production_name" type="text" name="production_name" value="{{ old('production_name') }}" required maxlength="255"
                            class="w-full border rounded-lg px-3 py-2" placeholder="Contoh: Film Pendek Senja">
                        @error('production_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-7">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <div>
                                <h3 class="font-semibold">Lokasi Shooting</h3>
                                <p class="text-xs text-gray-500">Satu baris untuk setiap lokasi dan waktu shooting.</p>
                            </div>
                            <button type="button" @click="addLocation()" :disabled="locations.length >= 25" class="text-sm font-semibold text-primary-600 disabled:opacity-50">+ Tambah lokasi</button>
                        </div>
                        @error('shooting_locations')<p class="text-red-500 text-xs mb-2">{{ $message }}</p>@enderror
                        <div class="space-y-3">
                            <template x-for="(location, index) in locations" :key="location.key">
                                <div class="border rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <strong class="text-sm" x-text="'Lokasi ' + (index + 1)"></strong>
                                        <button type="button" @click="removeLocation(index)" x-show="locations.length > 1" class="text-xs text-red-600">Hapus</button>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-medium mb-1">Nama / Alamat Lokasi *</label>
                                            <input type="text" :name="`shooting_locations[${index}][location_name]`" x-model="location.location_name" required maxlength="255" class="w-full border rounded-lg px-3 py-2">
                                            <p x-show="fieldError(`shooting_locations.${index}.location_name`)" x-text="fieldError(`shooting_locations.${index}.location_name`)" class="text-red-500 text-xs mt-1"></p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium mb-1">Tanggal & Jam Mulai *</label>
                                            <input type="datetime-local" :name="`shooting_locations[${index}][start_at]`" x-model="location.start_at" required class="w-full border rounded-lg px-3 py-2">
                                            <p x-show="fieldError(`shooting_locations.${index}.start_at`)" x-text="fieldError(`shooting_locations.${index}.start_at`)" class="text-red-500 text-xs mt-1"></p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium mb-1">Tanggal & Jam Selesai *</label>
                                            <input type="datetime-local" :name="`shooting_locations[${index}][end_at]`" x-model="location.end_at" required class="w-full border rounded-lg px-3 py-2">
                                            <p x-show="fieldError(`shooting_locations.${index}.end_at`)" x-text="fieldError(`shooting_locations.${index}.end_at`)" class="text-red-500 text-xs mt-1"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p class="text-sm font-semibold mt-3">Total hari shooting: <span x-text="totalDays"></span> hari</p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <h3 class="font-semibold">Crew Inti / Kelompok</h3>
                            <button type="button" @click="addCrew()" :disabled="crew.length >= 50" class="text-sm font-semibold text-primary-600 disabled:opacity-50">+ Tambah crew</button>
                        </div>
                        @error('shooting_crew')<p class="text-red-500 text-xs mb-2">{{ $message }}</p>@enderror
                        <div class="space-y-3">
                            <template x-for="(member, index) in crew" :key="member.key">
                                <div class="border rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <strong class="text-sm" x-text="'Crew ' + (index + 1)"></strong>
                                        <button type="button" @click="removeCrew(index)" x-show="crew.length > 1" class="text-xs text-red-600">Hapus</button>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium mb-1">Nama *</label>
                                            <input type="text" :name="`shooting_crew[${index}][name]`" x-model="member.name" required maxlength="255" class="w-full border rounded-lg px-3 py-2">
                                            <p x-show="fieldError(`shooting_crew.${index}.name`)" x-text="fieldError(`shooting_crew.${index}.name`)" class="text-red-500 text-xs mt-1"></p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium mb-1">NIM *</label>
                                            <input type="text" :name="`shooting_crew[${index}][nim]`" x-model="member.nim" required maxlength="50" inputmode="numeric" class="w-full border rounded-lg px-3 py-2">
                                            <p x-show="fieldError(`shooting_crew.${index}.nim`)" x-text="fieldError(`shooting_crew.${index}.nim`)" class="text-red-500 text-xs mt-1"></p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium mb-1">Role *</label>
                                            <input type="text" :name="`shooting_crew[${index}][role]`" x-model="member.role" required maxlength="100" list="shooting_roles" placeholder="Sutradara, DOP, ..." class="w-full border rounded-lg px-3 py-2">
                                            <p x-show="fieldError(`shooting_crew.${index}.role`)" x-text="fieldError(`shooting_crew.${index}.role`)" class="text-red-500 text-xs mt-1"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <datalist id="shooting_roles">
                            <option value="Sutradara"><option value="DOP"><option value="Produser"><option value="Penata Suara"><option value="Gaffer"><option value="Art Director">
                        </datalist>
                    </div>
                </fieldset>

                <div class="flex justify-end mb-6">
                    <button type="button" @click="nextStep()" class="bg-primary-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-700">Lanjutkan ke Ringkasan →</button>
                </div>
                </div>

                <div x-show="step === 2" x-cloak>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold">Tinjau Booking</h2>
                        <button type="button" @click="step = 1; window.scrollTo({top: 0, behavior: 'smooth'})" class="text-sm font-semibold text-primary-600">← Edit Penggunaan</button>
                    </div>
                    <div x-show="usageType === 'in_class'" class="bg-white rounded-lg shadow p-6 mb-6">
                        <h3 class="font-semibold">Akademik dalam kelas</h3>
                        <p class="text-sm text-gray-500 mt-1">Booking untuk kegiatan pembelajaran di kelas.</p>
                    </div>
                    <div x-show="usageType === 'outside_class'" class="bg-white rounded-lg shadow p-6 mb-6">
                        <h3 class="font-semibold mb-2" x-text="document.getElementById('production_name')?.value || 'Shooting Itinerary'"></h3>
                        <p class="text-sm text-gray-600 mb-1" x-text="outsidePurposeLabels[outsidePurpose] || ''"></p>
                        <p class="text-sm text-gray-600"><span x-text="totalDays"></span> hari shooting · <span x-text="locations.length"></span> lokasi · <span x-text="crew.length"></span> crew</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mt-4 text-sm">
                            <div>
                                <h4 class="font-semibold mb-2">Lokasi & Jadwal</h4>
                                <template x-for="location in locations" :key="location.key">
                                    <p class="mb-2"><span class="font-medium" x-text="location.location_name"></span><br><span class="text-gray-600" x-text="`${location.start_at.replace('T', ' ')} – ${location.end_at.replace('T', ' ')}`"></span></p>
                                </template>
                            </div>
                            <div>
                                <h4 class="font-semibold mb-2">Crew Inti</h4>
                                <template x-for="member in crew" :key="member.key">
                                    <p class="mb-2"><span class="font-medium" x-text="member.name"></span> · <span x-text="member.role"></span><br><span class="text-gray-600" x-text="member.nim"></span></p>
                                </template>
                            </div>
                        </div>
                    </div>

                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-4">Order Items</h2>
                    <div class="space-y-4">
                        @foreach($cartItems as $item)
                            <div class="flex items-center justify-between py-3 border-b">
                                <div class="flex items-center">
                                    <div class="h-12 w-12 bg-gray-200 rounded flex items-center justify-center mr-4">
                                        <span class="text-xl">📷</span>
                                    </div>
                                    <div>
                                        <p class="font-medium">
                                            {{ $item->productUnit->product->name }}
                                            @if($item->productUnit->variation)
                                                <span class="text-gray-500 font-normal">({{ $item->productUnit->variation->name }})</span>
                                            @endif
                                        </p>
                                        <p class="text-sm text-gray-500">{{ $item->start_date->format('d M Y') }} - {{ $item->end_date->format('d M Y') }} ({{ $item->days }} {{ $item->periodLabel() }})</p>
                                    </div>
                                </div>
                                <p class="font-semibold">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Fulfillment / Delivery -->
                <div class="bg-white rounded-lg shadow p-6 mb-6"
                    x-data="{ method: '{{ old('fulfillment_method', 'pickup') }}' }">
                    <h2 class="text-lg font-semibold mb-4">Metode Pengambilan</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer"
                            :class="method === 'pickup' ? 'border-primary-500 ring-1 ring-primary-500' : 'border-gray-200'">
                            <input type="radio" name="fulfillment_method" value="pickup" x-model="method" class="mt-1">
                            <span>
                                <span class="block font-medium">Ambil sendiri</span>
                                <span class="block text-sm text-gray-500">Datang ke gudang</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 border rounded-lg px-4 py-3 cursor-pointer"
                            :class="method === 'delivery' ? 'border-primary-500 ring-1 ring-primary-500' : 'border-gray-200'">
                            <input type="radio" name="fulfillment_method" value="delivery" x-model="method" class="mt-1">
                            <span>
                                <span class="block font-medium">Diantar</span>
                                <span class="block text-sm text-gray-500">Kirim ke alamat Anda</span>
                            </span>
                        </label>
                    </div>

                    <div x-show="method === 'delivery'" x-cloak class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Pengiriman <span class="text-red-500">*</span></label>
                            <textarea name="delivery_address" rows="3" class="w-full border rounded-lg px-3 py-2"
                                placeholder="Alamat lengkap tujuan pengiriman…"
                                x-bind:required="method === 'delivery'">{{ old('delivery_address', auth()->guard('customer')->user()?->address) }}</textarea>
                            @error('delivery_address')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kontak Penerima</label>
                            <input type="text" name="delivery_contact" class="w-full border rounded-lg px-3 py-2"
                                placeholder="Nama / no. HP penerima"
                                value="{{ old('delivery_contact', auth()->guard('customer')->user()?->phone) }}">
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-semibold mb-4">Additional Notes</h2>
                    <textarea name="notes" rows="3" class="w-full border rounded-lg px-3 py-2" placeholder="Any special requests or notes...">{{ old('notes') }}</textarea>
                </div>

                <!-- Custom fields (Informasi Tambahan) -->
                @if(!empty($rentalCustomFields))
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h2 class="text-lg font-semibold mb-4">Informasi Tambahan</h2>
                        <div class="space-y-4">
                            @foreach($rentalCustomFields as $field)
                                @php
                                    $fname = $field['name'];
                                    $ftype = $field['type'] ?? 'text';
                                    $inputName = 'custom_'.$fname;
                                    $old = old($inputName);
                                    $req = $field['required'] ?? false;
                                @endphp
                                <div>
                                    @if($ftype === 'checkbox')
                                        <label class="flex items-center cursor-pointer">
                                            <input type="checkbox" name="{{ $inputName }}" value="1" @checked($old) class="mr-2" @if($req) required @endif>
                                            <span class="text-sm text-gray-700">{{ $field['label'] ?? $fname }}@if($req)<span class="text-red-500"> *</span>@endif</span>
                                        </label>
                                    @else
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $field['label'] ?? $fname }}@if($req)<span class="text-red-500"> *</span>@endif</label>
                                        @if($ftype === 'textarea')
                                            <textarea name="{{ $inputName }}" rows="3" class="w-full border rounded-lg px-3 py-2" @if($req) required @endif>{{ $old }}</textarea>
                                        @elseif(in_array($ftype, ['select','radio']))
                                            <select name="{{ $inputName }}" class="w-full border rounded-lg px-3 py-2" @if($req) required @endif>
                                                <option value="">— Pilih —</option>
                                                @foreach(\App\Support\CustomFields::parseOptions($field['options'] ?? '') as $val => $lbl)
                                                    <option value="{{ $val }}" @selected($old === (string) $val)>{{ $lbl }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="{{ $ftype === 'number' ? 'number' : ($ftype === 'email' ? 'email' : 'text') }}"
                                                name="{{ $inputName }}" value="{{ $old }}"
                                                class="w-full border rounded-lg px-3 py-2" @if($req) required @endif>
                                        @endif
                                    @endif
                                    @error($inputName)
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Terms -->
                <div class="bg-white rounded-lg shadow p-6">
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" name="agree_terms" id="agree_terms" required class="mt-1 mr-3">
                        <span class="text-sm text-gray-600">
                            Saya telah membaca dan menyetujui <a href="{{ url('/syarat-ketentuan') }}" target="_blank" class="text-primary-600 hover:underline">Syarat dan Ketentuan</a> dan bertanggung jawab penuh terhadap alat yang dipinjam.
                        </span>
                    </label>
                </div>

                <!-- Terms Modal -->
                <div id="terms_modal" class="terms-modal fixed inset-0 z-50 hidden items-center justify-center p-4">
                    <div class="terms-modal__backdrop absolute inset-0 bg-black bg-opacity-50"></div>
                    <div class="terms-modal__panel relative bg-white rounded-lg shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col">
                        <div class="px-6 py-4 border-b flex items-center justify-between">
                            <h3 class="text-lg font-semibold">Syarat dan Ketentuan</h3>
                            <button type="button" id="terms_close" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>
                        <div class="flex-1 overflow-hidden">
                            <iframe id="terms_iframe" src="" class="w-full h-full min-h-[60vh] border-0"></iframe>
                        </div>
                        <div class="px-6 py-4 border-t flex justify-end gap-3 bg-gray-50 rounded-b-lg">
                            <button type="button" id="terms_cancel" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm">Batal</button>
                            <button type="button" id="terms_accept" class="px-4 py-2 rounded-lg bg-primary-600 text-white hover:bg-primary-700 text-sm">Saya Setuju</button>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1" x-show="step === 2" x-cloak>
                <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                    <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                    
                    <div class="mb-6 border-b pb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kode Diskon</label>
                        <div class="flex gap-2">
                            <input type="text" id="discount_code" value="{{ session('checkout_discount_code') }}" class="w-full border rounded-lg px-3 py-2 text-sm uppercase" placeholder="Masukkan kode">
                            <button type="button" id="apply_discount" class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-700 transition">Pakai</button>
                        </div>
                        <p id="discount_message" class="text-xs mt-2 {{ session('checkout_discount_amount') ? 'text-green-600' : 'hidden' }}">
                            {{ session('checkout_discount_amount') ? 'Diskon berhasil diterapkan!' : '' }}
                        </p>
                    </div>

                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span>Rp {{ number_format($grossTotal ?? $subtotal, 0, ',', '.') }}</span>
                        </div>
                        
                        @if(isset($categoryDiscountAmount) && $categoryDiscountAmount > 0)
                            <div class="flex justify-between text-green-600">
                                <span>Discount ({{ $categoryName }})</span>
                                <span>- Rp {{ number_format($categoryDiscountAmount, 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if(isset($dailyDiscountAmount) && $dailyDiscountAmount > 0)
                            <div class="flex justify-between text-green-600">
                                <span>{{ $dailyDiscountName ?? 'Diskon Harian' }}</span>
                                <span>- Rp {{ number_format($dailyDiscountAmount, 0, ',', '.') }}</span>
                            </div>
                        @endif

                        @if(isset($datePromotionAmount) && $datePromotionAmount > 0)
                            <div class="flex justify-between text-green-600">
                                <span>{{ $datePromotionName ?? 'Promo Khusus' }}</span>
                                <span>- Rp {{ number_format($datePromotionAmount, 0, ',', '.') }}</span>
                            </div>
                        @endif

                        <div id="discount_row" class="flex justify-between text-green-600 {{ isset($discountAmount) && $discountAmount > 0 ? '' : 'hidden' }}">
                            <span>Discount (Coupon)</span>
                            <span id="discount_amount">-Rp {{ number_format($discountAmount ?? 0, 0, ',', '.') }}</span>
                        </div>

                        @if($deposit > 0)
                        <div class="flex justify-between">
                            <span class="text-gray-600">Deposit</span>
                            <span id="deposit_amount">Rp {{ number_format($deposit, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        <hr>
                        <div class="flex justify-between font-bold text-lg">
                            <span>Total</span>
                            <span class="text-primary-600" id="grand_total">Rp {{ number_format($grandTotal ?? ($grossTotal - ($categoryDiscountAmount ?? 0) - ($totalDiscount ?? 0)), 0, ',', '.') }}</span>
                        </div>
                    </div>

                    @if(isset($activePromotions) && count($activePromotions) > 0)
                    <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                        <p class="text-xs font-semibold text-green-700 mb-1">Promo Aktif:</p>
                        <ul class="text-xs text-green-600 space-y-1">
                            @foreach($activePromotions as $promo)
                                <li>• {{ $promo }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <button type="submit" class="w-full bg-primary-600 text-white py-3 rounded-lg font-semibold hover:bg-primary-700 transition">
                        Submit Booking
                    </button>

                    <p class="text-xs text-gray-500 mt-4 text-center">
                        Setelah submit, mohon untuk segera melakukan konfirmasi booking ke Admin.
                    </p>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function shootingCheckout() {
        const savedLocations = @json($initialLocations);
        const savedCrew = @json($initialCrew);

        return {
            step: {{ $itineraryHasErrors ? 1 : (($errors->any() || old('usage_type')) ? 2 : 1) }},
            usageType: @json(old('usage_type', '')),
            outsidePurpose: @json(old('outside_purpose', '')),
            outsidePurposeLabels: @json(\App\Models\Rental::outsidePurposeOptions()),
            errors: @json($errors->toArray()),
            clientErrors: {},
            locations: (Array.isArray(savedLocations) && savedLocations.length ? savedLocations : [{}])
                .map(item => ({ key: Math.random().toString(36).slice(2), location_name: item.location_name ?? '', start_at: item.start_at ?? '', end_at: item.end_at ?? '' })),
            crew: (Array.isArray(savedCrew) && savedCrew.length ? savedCrew : [{}])
                .map(item => ({ key: Math.random().toString(36).slice(2), name: item.name ?? '', nim: item.nim ?? '', role: item.role ?? '' })),
            get totalDays() {
                return new Set(this.locations.map(location => location.start_at?.slice(0, 10)).filter(Boolean)).size;
            },
            fieldError(key) {
                return this.clientErrors[key] || this.errors[key]?.[0] || '';
            },
            addLocation() {
                if (this.locations.length < 25) this.locations.push({ key: Math.random().toString(36).slice(2), location_name: '', start_at: '', end_at: '' });
            },
            removeLocation(index) {
                if (this.locations.length > 1) this.locations.splice(index, 1);
            },
            addCrew() {
                if (this.crew.length < 50) this.crew.push({ key: Math.random().toString(36).slice(2), name: '', nim: '', role: '' });
            },
            removeCrew(index) {
                if (this.crew.length > 1) this.crew.splice(index, 1);
            },
            nextStep() {
                const invalidInput = [...document.querySelectorAll('#shooting_step input, #shooting_step select')]
                    .find(input => !input.checkValidity());
                if (invalidInput) {
                    invalidInput.reportValidity();
                    return;
                }

                this.clientErrors = {};
                if (this.usageType === 'outside_class') {
                    for (const [index, location] of this.locations.entries()) {
                        if (location.end_at <= location.start_at) {
                            this.clientErrors[`shooting_locations.${index}.end_at`] = 'Jam selesai harus setelah jam mulai.';
                            this.$nextTick(() => document.querySelector(`[name="shooting_locations[${index}][end_at]"]`)
                                ?.scrollIntoView({ behavior: 'smooth', block: 'center' }));
                            return;
                        }
                    }
                }

                this.step = 2;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        };
    }

    document.getElementById('apply_discount').addEventListener('click', function() {
        const code = document.getElementById('discount_code').value;
        const btn = this;
        const msg = document.getElementById('discount_message');
        
        if (!code) return;

        btn.disabled = true;
        btn.innerHTML = '...';
        msg.classList.add('hidden');
        msg.className = 'text-xs mt-2 hidden';

        fetch('{{ route("checkout.validate-discount") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ code: code })
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = 'Pakai';
            msg.classList.remove('hidden');
            msg.textContent = data.message;
            
            if (data.valid) {
                msg.classList.add('text-green-600');
                msg.classList.remove('text-red-600');
                
                // Update Summary
                document.getElementById('discount_row').classList.remove('hidden');
                document.getElementById('discount_amount').textContent = '-Rp ' + new Intl.NumberFormat('id-ID').format(data.discount_amount);

                // The server returns the fully-stacked payable total (real price −
                // category − promos − coupon), so just display it.
                document.getElementById('grand_total').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.grand_total);
            } else {
                msg.classList.add('text-red-600');
                msg.classList.remove('text-green-600');
                
                // Hide discount row if invalid?
                // document.getElementById('discount_row').classList.add('hidden');
                // Or keep previous valid state? 
                // If invalid, maybe clear previous discount?
                // The controller doesn't clear session on failure.
                // Ideally if user types wrong code, we just show error, don't remove existing valid code.
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btn.disabled = false;
            btn.innerHTML = 'Pakai';
            msg.classList.remove('hidden');
            msg.textContent = 'Terjadi kesalahan, silakan coba lagi.';
            msg.classList.add('text-red-600');
        });
    });

    // Terms & Conditions confirmation modal
    (function () {
        const checkbox = document.getElementById('agree_terms');
        const modal = document.getElementById('terms_modal');
        const iframe = document.getElementById('terms_iframe');
        const acceptBtn = document.getElementById('terms_accept');
        const cancelBtn = document.getElementById('terms_cancel');
        const closeBtn = document.getElementById('terms_close');
        const termsUrl = @json(url('/page-embed/syarat-ketentuan'));
        let accepted = false;

        function openModal() {
            if (iframe.getAttribute('src') !== termsUrl) iframe.setAttribute('src', termsUrl);
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            // next frame so the transition runs from the initial state
            requestAnimationFrame(() => {
                requestAnimationFrame(() => modal.classList.add('is-open'));
            });
        }

        function closeModal() {
            modal.classList.remove('is-open');
            const panel = modal.querySelector('.terms-modal__panel');
            const done = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.style.overflow = '';
                panel.removeEventListener('transitionend', done);
            };
            panel.addEventListener('transitionend', done);
        }

        checkbox.addEventListener('click', function (e) {
            if (accepted) return; // already accepted, allow normal toggle
            if (checkbox.checked) {
                // Prevent the check from sticking until they confirm in modal
                e.preventDefault();
                checkbox.checked = false;
                openModal();
            }
        });

        acceptBtn.addEventListener('click', function () {
            accepted = true;
            checkbox.checked = true;
            closeModal();
        });

        cancelBtn.addEventListener('click', function () {
            checkbox.checked = false;
            closeModal();
        });

        closeBtn.addEventListener('click', function () {
            checkbox.checked = false;
            closeModal();
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.classList.contains('terms-modal__backdrop')) {
                checkbox.checked = false;
                closeModal();
            }
        });
    })();
</script>
@endsection
