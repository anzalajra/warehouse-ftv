<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use App\Services\JournalService;
use App\Models\FinanceTransaction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class FinanceSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Finance';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.clusters.settings.pages.finance-settings';

    public ?array $data = [];

    public function mount(): void
    {
        // Load general finance settings
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        
        // Load tax settings
        $taxSettings = Setting::where('group', 'tax')->get()->pluck('value', 'key')->toArray();
        
        // Merge all settings
        $allSettings = array_merge($settings, $taxSettings);
        
        // Decode JSON fields if they are strings
        if (isset($allSettings['international_tax_rates']) && is_string($allSettings['international_tax_rates'])) {
            $allSettings['international_tax_rates'] = json_decode($allSettings['international_tax_rates'], true) ?? [];
        }

        $this->form->fill($allSettings);

        if (session()->has('show_sync_confirmation')) {
            Notification::make()
                ->title('Switched to Advanced Mode')
                ->body('Do you want to sync all existing simple transactions to journal entries?')
                ->warning()
                ->persistent()
                ->actions([
                    Action::make('sync')
                        ->button()
                        ->label('Sync Now')
                        ->dispatch('syncSimpleTransactions'),
                    Action::make('close')
                        ->label('Later')
                        ->close(),
                ])
                ->send();

            session()->forget('show_sync_confirmation');
        }
    }

    protected $listeners = ['syncSimpleTransactions' => 'syncSimpleTransactions'];

    public function syncSimpleTransactions(): void
    {
        $count = 0;
        FinanceTransaction::chunk(100, function ($transactions) use (&$count) {
            foreach ($transactions as $transaction) {
                JournalService::syncFromTransaction($transaction);
                $count++;
            }
        });

        Notification::make()
            ->title("Synced {$count} transactions to Journal Entries")
            ->success()
            ->send();
            
        $this->redirect(request()->header('Referer'));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Finance Settings')
                    ->tabs([
                        Tab::make('Finance Mode')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('Finance Mode')
                                    ->description('Choose the finance mode that best fits your business needs.')
                                    ->schema([
                                        ToggleButtons::make('finance_mode')
                                            ->label('Finance Mode')
                                            ->options([
                                                'simple' => 'Simple (Income/Expense)',
                                                'advanced' => 'Advanced (Double Entry Accounting)',
                                            ])
                                            ->icons([
                                                'simple' => 'heroicon-o-banknotes',
                                                'advanced' => 'heroicon-o-calculator',
                                            ])
                                            ->default('simple')
                                            ->inline()
                                            ->live()
                                            ->afterStateUpdated(function ($state) {
                                                if ($state === 'advanced') {
                                                    session()->put('show_sync_confirmation', true);
                                                }
                                            }),
                                    ]),

                                Section::make('Accounting Standard')
                                    ->description('Determines WHEN rental revenue is recognized in the double-entry ledger. Only applies in Advanced mode.')
                                    ->visible(fn ($get) => $get('finance_mode') === 'advanced')
                                    ->schema([
                                        ToggleButtons::make('accounting_standard')
                                            ->label('Standard')
                                            ->options(\App\Services\RentalAccountingService::standardOptions())
                                            ->icons([
                                                \App\Services\RentalAccountingService::STANDARD_SAK => 'heroicon-o-building-library',
                                                \App\Services\RentalAccountingService::STANDARD_IFRS => 'heroicon-o-globe-alt',
                                            ])
                                            ->default(\App\Services\RentalAccountingService::STANDARD_SAK)
                                            ->inline()
                                            ->helperText('SAK: pendapatan diakui saat invoice terbit (point-in-time). IFRS/ASC 606/PSAK 72: pendapatan ditangguhkan dan diakui saat sewa selesai (over-time).'),
                                    ]),

                                Section::make('Tutup Buku (Period Lock)')
                                    ->description('Kunci periode akuntansi. Jurnal dengan tanggal SAMPAI DENGAN tanggal ini akan ditolak — mencegah perubahan periode yang sudah dilaporkan.')
                                    ->visible(fn ($get) => $get('finance_mode') === 'advanced')
                                    ->schema([
                                        DatePicker::make('finance_locked_until')
                                            ->label('Buku Terkunci Sampai')
                                            ->helperText('Kosongkan untuk membuka semua periode. Isi mis. 2026-06-30 untuk mengunci sampai akhir Juni 2026.')
                                            ->native(false),
                                    ]),
                            ]),
                        
                        Tab::make('Tax Settings')
                            ->icon('heroicon-o-receipt-percent')
                            ->schema([
                                Section::make('Global Tax Configuration')
                                    ->schema([
                                        Toggle::make('tax_enabled')
                                            ->label('Enable Tax System')
                                            ->helperText('Turn off if you do not want to use tax features in the system.')
                                            ->default(true)
                                            ->live(),
                                    ]),

                                Section::make('Tax System Mode')
                                    ->visible(fn ($get) => $get('tax_enabled'))
                                    ->schema([
                                        Radio::make('tax_mode')
                                            ->label('Select Tax System')
                                            ->options([
                                                'indonesia' => 'Indonesia (PPN & PPh Final)',
                                                'international' => 'International (Multi-Tax Rates)',
                                            ])
                                            ->default('indonesia')
                                            ->required()
                                            ->live(),
                                    ]),

                                Section::make('Company Tax Identity')
                                    ->visible(fn ($get) => $get('tax_enabled'))
                                    ->description('Manage your company tax information (Master Data Perpajakan)')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('company_tax_name')
                                                ->label('Company Name (Tax)')
                                                ->placeholder('Nama Sesuai KTP/Paspor/Akta')
                                                ->required(),
                                            TextInput::make('company_npwp')
                                                ->label('NPWP')
                                                ->placeholder('Nomor Pokok Wajib Pajak'),
                                            TextInput::make('company_nik')
                                                ->label('NIK')
                                                ->placeholder('Nomor Induk Kependudukan'),
                                            TextInput::make('company_tax_address')
                                                ->label('Tax Address')
                                                ->columnSpanFull(),
                                        ]),
                                    ]),
                                
                                Section::make('Indonesia Tax Configuration')
                                    ->visible(fn ($get) => $get('tax_enabled') && $get('tax_mode') === 'indonesia')
                                    ->schema([
                                        Toggle::make('is_pkp')
                                            ->label('Pengusaha Kena Pajak (PKP)')
                                            ->helperText('Enable if your business is registered as PKP and can issue Faktur Pajak.')
                                            ->live(),

                                        Toggle::make('is_taxable')
                                            ->label('Kena PPN (11%) (Default)')
                                            ->helperText('Default setting for new transactions.')
                                            ->default(true),

                                        Toggle::make('price_includes_tax')
                                            ->label('Harga Termasuk Pajak (Default)')
                                            ->helperText('Default setting for new transactions.')
                                            ->default(false),
                                        
                                        FileUpload::make('digital_certificate')
                                            ->label('Digital Certificate (e-Faktur)')
                                            ->helperText('Upload your digital certificate (.p12/.pfx) for e-Faktur integration.')
                                            ->disk('local')
                                            ->directory('certificates')
                                            ->visible(fn ($get) => $get('is_pkp')),
                                            
                                        Grid::make(2)->schema([
                                            TextInput::make('ppn_rate')
                                                ->label('Default PPN Rate (%)')
                                                ->numeric()
                                                ->default(11)
                                                ->suffix('%')
                                                ->visible(fn ($get) => $get('is_pkp')),
                                                
                                            TextInput::make('pph_final_rate')
                                                ->label('PPh Final Rate (%)')
                                                ->numeric()
                                                ->default(0.5)
                                                ->suffix('%')
                                                ->helperText('For UMKM (PP 55/2022)'),
                                        ]),
                                    ]),

                                Section::make('International Tax Configuration')
                                    ->visible(fn ($get) => $get('tax_enabled') && $get('tax_mode') === 'international')
                                    ->schema([
                                        Repeater::make('international_tax_rates')
                                            ->label('Tax Rates by Country/Region')
                                            ->schema([
                                                Select::make('country_code')
                                                    ->label('Country')
                                                    ->options([
                                                        'SG' => 'Singapore',
                                                        'MY' => 'Malaysia',
                                                        'US' => 'United States',
                                                        'UK' => 'United Kingdom',
                                                        'AU' => 'Australia',
                                                        'JP' => 'Japan',
                                                        'CN' => 'China',
                                                        'IN' => 'India',
                                                        'TH' => 'Thailand',
                                                        'VN' => 'Vietnam',
                                                        'PH' => 'Philippines',
                                                    ])
                                                    ->searchable()
                                                    ->required(),
                                                TextInput::make('tax_name')
                                                    ->label('Tax Name')
                                                    ->placeholder('e.g. VAT, GST, Sales Tax')
                                                    ->required(),
                                                TextInput::make('rate')
                                                    ->label('Rate (%)')
                                                    ->numeric()
                                                    ->suffix('%')
                                                    ->required(),
                                            ])
                                            ->columns(3)
                                            ->defaultItems(1)
                                            ->addActionLabel('Add Tax Rate'),
                                    ]),
                            ]),
                    ])
                    ->contained(false),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        // Tax-related keys
        $taxKeys = [
            'tax_enabled', 'tax_mode', 'company_tax_name', 'company_npwp', 
            'company_nik', 'company_tax_address', 'is_pkp', 'is_taxable', 
            'price_includes_tax', 'digital_certificate', 'ppn_rate', 
            'pph_final_rate', 'international_tax_rates'
        ];
        
        // Handle finance mode change
        $currentMode = Setting::get('finance_mode', 'simple');
        $newMode = $data['finance_mode'] ?? 'simple';
        
        foreach ($data as $key => $value) {
            if (in_array($key, $taxKeys)) {
                // Tax settings
                $type = 'string';
                $originalValue = $value;

                if (is_bool($value)) {
                    $type = 'boolean';
                } elseif (is_numeric($value)) {
                    $type = 'number';
                } elseif (is_array($value)) {
                    $type = 'json';
                    $value = json_encode($value);
                }

                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'group' => 'tax',
                        'type' => $type,
                        'label' => ucwords(str_replace('_', ' ', $key)),
                    ]
                );
            } else {
                // General finance settings
                Setting::set($key, $value);
            }
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
            
        // Reload if finance mode changed to trigger session check
        if ($currentMode !== $newMode && $newMode === 'advanced') {
            $this->redirect(request()->header('Referer'));
        }
    }
}
