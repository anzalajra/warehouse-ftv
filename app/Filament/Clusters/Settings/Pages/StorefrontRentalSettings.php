<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StorefrontRentalSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?string $navigationLabel = 'Disable Storefront Rental';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.clusters.settings.pages.storefront-rental-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'storefront_rental_disabled' => filter_var(
                Setting::get('storefront_rental_disabled', false),
                FILTER_VALIDATE_BOOLEAN
            ),
            'storefront_rental_disabled_start'   => Setting::get('storefront_rental_disabled_start'),
            'storefront_rental_disabled_end'     => Setting::get('storefront_rental_disabled_end'),
            'storefront_rental_disabled_message' => Setting::get('storefront_rental_disabled_message'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Disable Storefront Rental')
                    ->description('Nonaktifkan sementara fungsi rental customer di storefront (catalog, detail produk, cart, checkout). Admin tidak terpengaruh.')
                    ->schema([
                        Toggle::make('storefront_rental_disabled')
                            ->label('Disable storefront rental')
                            ->helperText('Aktifkan untuk menghentikan customer dari memilih tanggal, menambah ke cart, dan melakukan checkout.')
                            ->live(),

                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('storefront_rental_disabled_start')
                                    ->label('Start date')
                                    ->seconds(false)
                                    ->helperText('Kosongkan untuk mulai segera.'),
                                DateTimePicker::make('storefront_rental_disabled_end')
                                    ->label('End date')
                                    ->seconds(false)
                                    ->helperText('Kosongkan untuk manual (sampai toggle dimatikan).')
                                    ->afterOrEqual('storefront_rental_disabled_start'),
                            ]),

                        Textarea::make('storefront_rental_disabled_message')
                            ->label('Keterangan')
                            ->rows(4)
                            ->helperText('Pesan yang ditampilkan ke customer (banner & popup). Jelaskan alasan & estimasi kapan aktif kembali.')
                            ->maxLength(1000),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('storefront_rental_disabled', $data['storefront_rental_disabled'] ? '1' : '0');
        Setting::set('storefront_rental_disabled_start', $data['storefront_rental_disabled_start'] ?? '');
        Setting::set('storefront_rental_disabled_end', $data['storefront_rental_disabled_end'] ?? '');
        Setting::set('storefront_rental_disabled_message', $data['storefront_rental_disabled_message'] ?? '');

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
