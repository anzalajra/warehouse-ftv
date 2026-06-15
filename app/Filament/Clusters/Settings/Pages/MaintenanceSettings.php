<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Maintenance';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.clusters.settings.pages.maintenance-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        $this->form->fill([
            'maintenance_qc_interval_days' => $settings['maintenance_qc_interval_days'] ?? 90,
            'maintenance_preventive_rental_count' => $settings['maintenance_preventive_rental_count'] ?? 0,
            'maintenance_overdue_days' => $settings['maintenance_overdue_days'] ?? 7,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('QC / Stock Opname')
                    ->description('Unit yang belum di-QC melebihi interval akan muncul di tab "QC Jatuh Tempo" dan notifikasi harian.')
                    ->schema([
                        TextInput::make('maintenance_qc_interval_days')
                            ->label('Interval QC (hari)')
                            ->numeric()
                            ->minValue(0)
                            ->default(90)
                            ->suffix('hari')
                            ->helperText('0 untuk menonaktifkan pengecekan QC jatuh tempo.'),
                    ]),

                Section::make('Servis Preventif')
                    ->description('Tandai unit untuk servis setelah dipakai sejumlah rental sejak maintenance terakhir.')
                    ->schema([
                        TextInput::make('maintenance_preventive_rental_count')
                            ->label('Servis setelah N rental')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('rental')
                            ->helperText('0 untuk menonaktifkan pemicu servis berbasis pemakaian.'),
                    ]),

                Section::make('Tampilan')
                    ->schema([
                        TextInput::make('maintenance_overdue_days')
                            ->label('Ambang "Lama" (hari)')
                            ->numeric()
                            ->minValue(1)
                            ->default(7)
                            ->suffix('hari')
                            ->helperText('Unit yang sudah di maintenance melebihi ini ditandai merah di tabel.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
