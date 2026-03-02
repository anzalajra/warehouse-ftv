<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\Setting;
use App\Services\JournalService;
use App\Models\FinanceTransaction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
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

        $this->form->fill($settings);

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
                    ]),
                
                Section::make('Finance Settings')
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
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        // Handle finance mode change
        $currentMode = Setting::get('finance_mode', 'simple');
        $newMode = $data['finance_mode'] ?? 'simple';
        
        foreach ($data as $key => $value) {
            Setting::set($key, $value);
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
