<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class RentalSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Rental Settings';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.clusters.settings.pages.rental-settings';

    public ?array $data = [];

    public array $holidays = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        // Decode JSON settings
        if (isset($settings['operational_days'])) {
            $settings['operational_days'] = json_decode($settings['operational_days'], true);
        }
        if (isset($settings['holidays'])) {
            $this->holidays = json_decode($settings['holidays'], true) ?? [];
            unset($settings['holidays']);
        }

        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make('Deposit Settings')
                            ->schema([
                                Checkbox::make('deposit_enabled')
                                    ->label('Enable Deposit')
                                    ->default(true)
                                    ->live(),
                                Grid::make(2)
                                    ->visible(fn ($get) => $get('deposit_enabled'))
                                    ->schema([
                                        Select::make('deposit_type')
                                            ->options([
                                                'percentage' => 'Percentage (%)',
                                                'fixed' => 'Fixed Amount (Rp)',
                                            ])
                                            ->default('percentage')
                                            ->live()
                                            ->required(),
                                        TextInput::make('deposit_amount')
                                            ->label(fn ($get) => $get('deposit_type') === 'percentage' ? 'Percentage' : 'Amount')
                                            ->numeric()
                                            ->suffix(fn ($get) => $get('deposit_type') === 'percentage' ? '%' : null)
                                            ->prefix(fn ($get) => $get('deposit_type') === 'fixed' ? 'Rp' : null)
                                            ->required()
                                            ->default(30)
                                            ->minValue(0)
                                            ->maxValue(fn ($get) => $get('deposit_type') === 'percentage' ? 100 : null),
                                    ]),
                            ])->columnSpanFull(),

                        Section::make('Late Fee Settings')
                            ->schema([
                                Select::make('late_fee_type')
                                    ->label('Late Fee Type')
                                    ->options([
                                        'percentage' => 'Percentage (%)',
                                        'fixed' => 'Fixed Amount (Rp)',
                                    ])
                                    ->default('percentage')
                                    ->live()
                                    ->required(),
                                TextInput::make('late_fee_amount')
                                    ->label(fn ($get) => $get('late_fee_type') === 'percentage' ? 'Percentage per Day' : 'Amount per Day')
                                    ->numeric()
                                    ->suffix(fn ($get) => $get('late_fee_type') === 'percentage' ? '%' : null)
                                    ->prefix(fn ($get) => $get('late_fee_type') === 'fixed' ? 'Rp' : null)
                                    ->required(),
                            ])->columnSpanFull(),

                        Section::make('Operational Schedule')
                            ->schema([
                                CheckboxList::make('operational_days')
                                    ->label('Operational Days')
                                    ->options([
                                        '1' => 'Monday',
                                        '2' => 'Tuesday',
                                        '3' => 'Wednesday',
                                        '4' => 'Thursday',
                                        '5' => 'Friday',
                                        '6' => 'Saturday',
                                        '0' => 'Sunday',
                                    ])
                                    ->columns(3)
                                    ->required(),
                            ])->columnSpanFull(),
                    ]),
            ]);
    }

    public function addHoliday(string $name, string $startDate, string $endDate): void
    {
        $this->holidays[] = [
            'name'       => $name,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ];
    }

    public function removeHoliday(int $index): void
    {
        array_splice($this->holidays, $index, 1);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Encode JSON fields
        if (isset($data['operational_days'])) {
            $data['operational_days'] = json_encode($data['operational_days']);
        }

        // Save holidays separately
        $data['holidays'] = json_encode(array_values($this->holidays));

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
