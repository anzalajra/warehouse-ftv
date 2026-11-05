<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use App\Services\Mufise\MufiseSettings as Configuration;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;

class MufiseSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Integrasi Mufise';

    protected string $view = 'filament.clusters.settings.pages.mufise-settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') || auth()->user()?->can('Manage:Mufise');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(['enabled' => Configuration::enabled(), 'url' => Configuration::url(), 'connection_id' => Configuration::connectionId(), 'api_key' => '', 'booking_crew' => Configuration::bookingRequirements()['crew'], 'booking_location' => Configuration::bookingRequirements()['location'], 'booking_call_sheet' => Configuration::bookingRequirements()['call_sheet'], 'poll_seconds' => Configuration::pollSeconds(), 'inline_editing' => Configuration::inlineEditing()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Koneksi Mufise')->description('Berlaku untuk peminjaman luar kelas baru. Buat koneksi Warehouse FTV pada pengaturan organisasi Mufise.')->schema([
                Toggle::make('enabled')->label('Aktifkan integrasi')->live(),
                TextInput::make('url')->label('URL Mufise')->url()->maxLength(200)->required(),
                TextInput::make('connection_id')->label('Connection ID')->maxLength(100)->required(),
                TextInput::make('api_key')->label('API key baru')->password()->autocomplete('new-password')->maxLength(500)->helperText('Kosongkan untuk mempertahankan key tersimpan. Key tidak ditampilkan kembali.'),
            ]),
            Section::make('Syarat tambahan sebelum booking')->description('Syarat dasar berasal dari koneksi organisasi Mufise dan tetap berlaku. Kontrol berikut menambah syarat Warehouse; pilih Ikuti Mufise untuk memakai kebijakan organisasi tanpa syarat tambahan.')->schema([
                Select::make('booking_crew')->label('Kru')->options(['none' => 'Ikuti Mufise', 'any' => 'Minimal satu kru', 'producer_or_director' => 'Produser atau sutradara'])->required(),
                Toggle::make('booking_location')->label('Wajib lokasi dan alamat sebelum booking'),
                Select::make('booking_call_sheet')->label('Call sheet Day 1 sebelum booking')->options(['none' => 'Ikuti Mufise', 'exists' => 'Wajib memiliki kegiatan', 'locked' => 'Wajib terkunci'])->required(),
            ]),
            Section::make('Pengalaman editor')->schema([
                Toggle::make('inline_editing')->label('Izinkan edit ringkas di checkout')->helperText('Tetap mengikuti izin efektif proyek dan persetujuan pengguna.'),
                TextInput::make('poll_seconds')->label('Interval sinkronisasi (detik)')->numeric()->minValue(5)->maxValue(300)->required()->helperText('15 detik disarankan. Batas API dibagi seluruh pengguna koneksi.'),
            ]),
        ]);
    }

    public ?array $connectionTest = null;

    public function testConnection(): void
    {
        abort_unless(static::canAccess(), 403);
        // Test saved credentials only. No pairing or user/project mutation.
        try {
            app(\App\Services\MufiseClient::class)->request('GET', 'me', diagnostic: true);
            $this->connectionTest = ['ok' => true, 'message' => 'Kredensial koneksi diterima Mufise. Uji akun dan akses proyek melalui halaman koneksi pelanggan.'];
        } catch (\App\Services\Mufise\MufiseException $e) {
            $accepted = $e->reason === 'user_token_missing';
            $this->connectionTest = ['ok' => $accepted, 'message' => $accepted ? 'Server Mufise dapat dijangkau dan kredensial koneksi diterima. Token peminjam diperlukan untuk menguji akses proyek.' : $e->getMessage(), 'reference' => $e->requestId ?? $e->warehouseRequestId];
        }
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        Configuration::validateUrl($data['url']);
        if ($data['enabled'] && empty($data['api_key']) && ! Configuration::key()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.api_key' => 'API key wajib diisi untuk mengaktifkan koneksi.']);
        }
        Setting::set('mufise_url', rtrim($data['url'], '/'));
        Setting::set('mufise_connection_id', $data['connection_id']);
        if (! empty($data['api_key'])) {
            Setting::set('mufise_api_key_encrypted', Crypt::encryptString($data['api_key']));
        }
        Setting::set('mufise_enabled', $data['enabled'] ? '1' : '0');
        foreach (['booking_crew', 'booking_location', 'booking_call_sheet', 'poll_seconds', 'inline_editing'] as $field) {
            Setting::set('mufise_'.$field, is_bool($data[$field]) ? ($data[$field] ? '1' : '0') : $data[$field]);
        }
        $this->data['api_key'] = '';
        Notification::make()->title('Pengaturan Mufise disimpan')->success()->send();
    }
}
