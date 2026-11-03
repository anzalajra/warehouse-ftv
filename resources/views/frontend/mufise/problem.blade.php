@if(!empty($problem))
    <p class="mt-2">{{ match($problem['action'] ?? '') {
        'check_configuration', 'check_credentials', 'check_token_storage', 'use_server' => 'Hubungi admin Warehouse untuk memeriksa pengaturan dan penyimpanan koneksi.',
        'verify_email' => 'Buka Mufise untuk memverifikasi email, lalu periksa kembali.',
        'contact_admin' => 'Hubungi admin Mufise atau organisasi pemilik koneksi.',
        'review_permissions' => 'Periksa persetujuan pengguna serta akses Production dan Plan di Mufise.',
        'review_resource' => 'Perbarui daftar proyek dan periksa akses akun.',
        'reload_data' => 'Muat ulang dan tinjau perubahan sebelum menyimpan operasi baru.',
        'review_operation' => 'Tinjau data operasi sebelum mengirim ulang.',
        'correct_request' => 'Periksa data formulir sebelum mencoba lagi.',
        'join_organization' => 'Mulai pemasangan baru untuk melengkapi keanggotaan organisasi.',
        'reconnect_account', 'restart_pairing' => 'Mulai pemasangan baru melalui tombol koneksi akun.',
        'retry_later' => 'Coba lagi setelah '.($problem['retry_after'] ?? 30).' detik.',
        default => 'Periksa kembali status koneksi.',
    } }}</p>
    @if(($problem['action'] ?? '') === 'verify_email')
        <a href="{{ \App\Services\Mufise\MufiseSettings::url() }}" target="_blank" rel="noopener noreferrer" class="inline-block mt-2 font-semibold underline">Buka Mufise</a>
    @endif
    @if(!empty($problem['request_id']))<p class="mt-2 text-xs break-all">Referensi Mufise: {{ $problem['request_id'] }}</p>@endif
    @if(!empty($problem['warehouse_request_id']))<p class="mt-2 text-xs break-all">Referensi Warehouse: {{ $problem['warehouse_request_id'] }}</p>@endif
@endif
