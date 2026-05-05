<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ComputerBookingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'Computer Booking';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.clusters.settings.pages.computer-booking-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'computer_quota_hours_per_week' => Setting::get('computer_quota_hours_per_week') ?? 6,
            'computer_quota_slots_per_day' => Setting::get('computer_quota_slots_per_day') ?? 1,
            'computer_no_show_grace_minutes' => Setting::get('computer_no_show_grace_minutes') ?? 30,
            'computer_tnc_text' => Setting::get('computer_tnc_text') ?? "Dilarang makan & minum di meja komputer.\nWajib backup data mandiri.\nLaporkan kerusakan kepada admin lab.",
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Quota / Fair Usage Policy')
                    ->schema([
                        TextInput::make('computer_quota_hours_per_week')
                            ->label('Maksimum Jam per Minggu (per user)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Set 0 untuk menonaktifkan kuota mingguan.'),
                        TextInput::make('computer_quota_slots_per_day')
                            ->label('Maksimum Slot per Hari (per user)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Set 0 untuk menonaktifkan kuota harian.'),
                    ])->columns(2),
                Section::make('No-Show Policy')
                    ->schema([
                        TextInput::make('computer_no_show_grace_minutes')
                            ->label('Grace Period (menit)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->helperText('Booking akan auto-cancel ke status no_show jika user tidak check-in dalam X menit setelah jam mulai.'),
                    ]),
                Section::make('Syarat &amp; Ketentuan')
                    ->schema([
                        Textarea::make('computer_tnc_text')
                            ->label('Teks T&C')
                            ->rows(6)
                            ->required()
                            ->helperText('Akan ditampilkan di form booking customer.'),
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
            ->title('Computer booking settings saved')
            ->success()
            ->send();
    }
}
