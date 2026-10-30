<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use App\Services\Mufise\MufiseSettings as Configuration;
use BackedEnum;
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
        $this->form->fill(['enabled' => Configuration::enabled(), 'url' => Configuration::url(), 'connection_id' => Configuration::connectionId(), 'api_key' => '']);
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
        ]);
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
        $this->data['api_key'] = '';
        Notification::make()->title('Pengaturan Mufise disimpan')->success()->send();
    }
}
