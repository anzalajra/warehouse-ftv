@php
    $retry = old('form_key') === $formKey;
    $value = fn ($key, $fallback = '') => $retry ? old($key, $fallback) : $fallback;
@endphp
<form method="POST" action="{{ route($base.'.mutate', $params) }}" class="space-y-3 mt-4">
    @csrf
    <input type="hidden" name="operation_id" value="{{ $retry ? old('operation_id') : (string) \Illuminate\Support\Str::uuid() }}">
    <input type="hidden" name="form_key" value="{{ $formKey }}">
    <input type="hidden" name="kind" value="{{ $kind }}">
    @if($target)
        <input type="hidden" name="target" value="{{ $target['id'] }}">
        <input type="hidden" name="expected_version" value="{{ $value('expected_version', $target['version']) }}">
    @endif
    @if($kind === 'crew')
        <label class="block">Nama<input required name="full_name" maxlength="255" value="{{ $value('full_name', $target['name'] ?? '') }}" class="block border rounded p-2 w-full"></label>
        <label class="block">Telepon<input name="phone" {{ !$target ? 'required' : '' }} maxlength="100" value="{{ $value('phone', $target['phone'] ?? '') }}" class="block border rounded p-2 w-full"></label>
        <label class="block">Email<input type="email" name="email" value="{{ $value('email', $target['email'] ?? '') }}" class="block border rounded p-2 w-full"></label>
        @if($target)
            <label class="block">Jabatan<select name="position_id" class="block border rounded p-2 w-full"><option value="">Pertahankan jabatan saat ini</option>
                @foreach($positions as $position)<option value="{{ $position['id'] }}" @selected($value('position_id') === $position['id'])>{{ $position['name'] }}</option>@endforeach
            </select></label>
            <label class="block">Alasan perubahan<input required name="reason" minlength="3" maxlength="500" value="{{ $value('reason') }}" class="block border rounded p-2 w-full"></label>
            <p class="text-sm text-gray-500">Perubahan nama/kontak dapat memengaruhi profil personel di proyek lain. Kontak akun terhubung hanya dapat diubah pemilik melalui profil Mufise.</p>
        @else
            <label class="block">Jabatan<input name="role" required value="{{ $value('role') }}" placeholder="Producer / Director / jabatan kru" class="block border rounded p-2 w-full"></label>
            <label class="block">Departemen<input name="department" value="{{ $value('department', 'Production') }}" class="block border rounded p-2 w-full"></label>
        @endif
        <label class="block text-sm"><input type="checkbox" name="confirm_distinct" value="1" @checked($value('confirm_distinct'))> Jika ada peringatan nama serupa, saya memastikan ini orang yang berbeda.</label>
    @else
        @foreach(['name' => 'Nama lokasi', 'address' => 'Alamat', 'parking' => 'Parkir', 'hospital' => 'Rumah sakit terdekat'] as $key => $label)
            <label class="block">{{ $label }}<input name="{{ $key }}" value="{{ $value($key, $target[$key] ?? '') }}" @required(in_array($key, ['name', 'address'])) class="block border rounded p-2 w-full"></label>
        @endforeach
    @endif
    <button type="submit" class="bg-primary-600 text-white rounded px-4 py-2">Simpan ke Mufise</button>
</form>
