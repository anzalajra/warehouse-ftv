<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class GeneralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'General Settings';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.clusters.settings.pages.general-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        $settings['app_timezone'] = $settings['app_timezone'] ?? 'Asia/Jakarta';

        $this->form->fill($settings);
    }

    protected function timezoneOptions(): array
    {
        $list = \DateTimeZone::listIdentifiers();
        return array_combine($list, $list);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(2)
                    ->schema([
                        FileUpload::make('site_logo')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('settings')
                            ->visibility('public')
                            ->columnSpanFull(),
                        TextInput::make('site_name')
                            ->label('Site Name')
                            ->required(),
                        TextInput::make('site_description')
                            ->label('Site Description'),
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->required(),
                        TextInput::make('company_address')
                            ->label('Address'),
                        TextInput::make('company_phone')
                            ->label('Phone'),
                        TextInput::make('company_email')
                            ->label('Email')
                            ->email(),
                        Select::make('app_timezone')
                            ->label('Timezone')
                            ->options($this->timezoneOptions())
                            ->searchable()
                            ->required()
                            ->default('Asia/Jakarta')
                            ->helperText('Timezone untuk seluruh aplikasi (booking, jam kiosk, jadwal). Perubahan butuh refresh halaman.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
