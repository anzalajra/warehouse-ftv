<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
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

    public array $operationalSchedule = [];

    private const DAY_ORDER = ['1', '2', '3', '4', '5', '6', '0'];

    private const DEFAULT_HOURS = ['open' => '08:00', 'close' => '17:00', 'is_24h' => false];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        // Load holidays
        if (isset($settings['holidays'])) {
            $this->holidays = json_decode($settings['holidays'], true) ?? [];
        }

        // Load operational schedule (new format)
        if (isset($settings['operational_schedule'])) {
            $this->operationalSchedule = json_decode($settings['operational_schedule'], true) ?? [];
        } else {
            // Migrate from old operational_days array
            $enabledDays = array_map('strval', json_decode($settings['operational_days'] ?? '[]', true) ?? []);
            foreach (self::DAY_ORDER as $day) {
                $this->operationalSchedule[$day] = array_merge(self::DEFAULT_HOURS, [
                    'enabled' => in_array($day, $enabledDays),
                ]);
            }
        }

        // Remove keys managed outside the Filament form
        unset($settings['holidays'], $settings['operational_days'], $settings['operational_schedule']);

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
                    ]),

                Section::make('Administration Checklist')
                    ->description('Pengaturan untuk checklist administrasi customer (stepper 4 langkah sebelum pengambilan barang).')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('warehouse_whatsapp_number')
                                    ->label('Nomor WhatsApp Warehouse')
                                    ->placeholder('6281234567890')
                                    ->helperText('Nomor WA yang akan dihubungi customer untuk konfirmasi booking. Format: 62xxx (tanpa + atau spasi).'),
                                TextInput::make('permit_document_link')
                                    ->label('Link Template Surat Perizinan')
                                    ->placeholder('https://docs.google.com/document/d/...')
                                    ->url()
                                    ->helperText('Link Google Docs template surat perizinan.'),
                            ]),
                        Textarea::make('warehouse_wa_template')
                            ->label('Template Pesan WhatsApp Konfirmasi Booking')
                            ->rows(4)
                            ->default("Halo admin warehouse, saya [customer_name] ingin konfirmasi booking [rental_code].\n\nMohon konfirmasi booking:\n[admin_url]")
                            ->helperText('Template pesan yang dikirim CUSTOMER ke admin. Placeholder: [customer_name], [rental_code], [admin_url]'),
                        Textarea::make('order_confirmed_wa_template')
                            ->label('Template Pesan WhatsApp Order Confirmed')
                            ->rows(4)
                            ->default("Halo [customer_name], pesanan Anda [rental_code] telah dikonfirmasi.\n\nSilakan cek detail rental Anda di:\n[my_rental]")
                            ->helperText('Template pesan yang dikirim ADMIN ke customer saat order dikonfirmasi. Placeholder: [customer_name], [rental_code], [my_rental]'),
                    ]),
            ]);
    }

    public function updateSchedule(array $schedule): void
    {
        $this->operationalSchedule = $schedule;

        $operationalDays = array_values(array_keys(array_filter($schedule, fn ($d) => $d['enabled'])));

        Setting::set('operational_schedule', json_encode($schedule));
        Setting::set('operational_days', json_encode($operationalDays));
    }

    public function addHoliday(string $name, string $startDate, string $endDate): void
    {
        $this->holidays[] = [
            'name'       => $name,
            'start_date' => $startDate,
            'end_date'   => $endDate,
        ];

        Setting::set('holidays', json_encode(array_values($this->holidays)));
    }

    public function removeHoliday(int $index): void
    {
        array_splice($this->holidays, $index, 1);

        Setting::set('holidays', json_encode(array_values($this->holidays)));
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Persist schedule and holidays alongside form data
        $operationalDays = array_values(array_keys(array_filter($this->operationalSchedule, fn ($d) => $d['enabled'])));
        $data['operational_schedule'] = json_encode($this->operationalSchedule);
        $data['operational_days']     = json_encode($operationalDays);
        $data['holidays']             = json_encode(array_values($this->holidays));

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
