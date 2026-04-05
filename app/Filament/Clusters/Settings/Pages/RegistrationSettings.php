<?php

namespace App\Filament\Clusters\Settings\Pages;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\CustomerCategory;
use App\Models\DocumentType;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class RegistrationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Registration & Verification';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.clusters.settings.pages.registration-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();
        
        // Decode JSON settings
        if (isset($settings['registration_custom_fields'])) {
            $settings['registration_custom_fields'] = json_decode($settings['registration_custom_fields'], true);
        }

        $this->form->fill([
            ...$settings,
            'document_types' => DocumentType::orderBy('sort_order')->get()->toArray(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Registration Settings')
                    ->schema([
                        Toggle::make('registration_open')
                            ->label('Accept New Registrations')
                            ->default(true),
                        
                        Toggle::make('auto_verify_registration')
                            ->label('Auto Verify Email')
                            ->helperText('If enabled, customers will be verified automatically upon registration.')
                            ->default(true),

                        Select::make('default_customer_category_id')
                            ->label('Default Customer Category')
                            ->options(CustomerCategory::where('is_active', true)->pluck('name', 'id'))
                            ->searchable(),
                    ]),

                Section::make('Custom Registration Fields')
                    ->description('Add extra fields to the registration form.')
                    ->schema([
                        Repeater::make('registration_custom_fields')
                            ->label('Fields')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('label')->required(),
                                    TextInput::make('name')
                                        ->required()
                                        ->label('Field Key')
                                        ->helperText('Unique key for database storage (e.g., student_id)'),
                                ]),
                                Select::make('type')
                                    ->options([
                                        'text' => 'Text',
                                        'number' => 'Number',
                                        'select' => 'Select',
                                        'radio' => 'Radio',
                                        'checkbox' => 'Checkbox', // For single checkbox (boolean)
                                        'textarea' => 'Textarea',
                                    ])
                                    ->required()
                                    ->reactive(),
                                Textarea::make('options')
                                    ->label('Options (comma separated)')
                                    ->helperText('For Select and Radio types only. Example: Option 1, Option 2')
                                    ->visible(fn ($get) => in_array($get('type'), ['select', 'radio']))
                                    ->required(fn ($get) => in_array($get('type'), ['select', 'radio'])),
                                Checkbox::make('required')->label('Required Field'),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null),
                    ]),

                Section::make('Verification Documents')
                    ->description('Manage required documents for customer verification.')
                    ->schema([
                        Repeater::make('document_types')
                            ->label('Document Types')
                            ->schema([
                                Hidden::make('id'),
                                Grid::make(2)->schema([
                                    TextInput::make('name')->required(),
                                    TextInput::make('description'),
                                ]),
                                Checkbox::make('is_required')->label('Required for Verification'),
                                Checkbox::make('is_active')->label('Active')->default(true),
                                Hidden::make('sort_order')->default(0),
                            ])
                            ->orderable('sort_order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->addActionLabel('Add Document Type'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        
        // Handle document types separately (they are stored in a separate table)
        if (isset($data['document_types'])) {
            foreach ($data['document_types'] as $index => $docData) {
                DocumentType::updateOrCreate(
                    ['id' => $docData['id'] ?? null],
                    [
                        'name' => $docData['name'],
                        'description' => $docData['description'],
                        'is_required' => $docData['is_required'],
                        'is_active' => $docData['is_active'],
                        'sort_order' => $index,
                    ]
                );
            }
            // Remove deleted ones if necessary, but Repeater usually just sends what's there. 
            // For simplicity, we just update/create. 
            // Ideally we should sync ids.
            $currentIds = collect($data['document_types'])->pluck('id')->filter()->toArray();
            DocumentType::whereNotIn('id', $currentIds)->delete();
            
            unset($data['document_types']);
        }

        // JSON Encode custom fields
        if (isset($data['registration_custom_fields'])) {
            $data['registration_custom_fields'] = json_encode($data['registration_custom_fields']);
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }
}
