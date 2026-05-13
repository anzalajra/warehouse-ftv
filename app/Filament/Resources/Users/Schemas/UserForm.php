<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Checkbox;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use App\Models\CustomerCategory;
use App\Models\Setting;

class UserForm
{
    /**
     * Normalize custom-field options to a value=>label array, accepting:
     *   - CSV string ("A,B,C")
     *   - flat array of strings (["A","B"])
     *   - array of {value,label} objects/arrays
     * Returns array with non-null string labels only.
     */
    protected static function normalizeOptions($raw): array
    {
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)), fn ($v) => $v !== '');
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $opt) {
            if (is_array($opt)) {
                $value = $opt['value'] ?? null;
                $label = $opt['label'] ?? $value;
                if ($value === null || $value === '') continue;
                $out[(string) $value] = (string) ($label ?? $value);
            } else {
                if ($opt === null || $opt === '') continue;
                $out[(string) $opt] = (string) $opt;
            }
        }

        return $out;
    }

    public static function configure(Schema $schema): Schema
    {
        $customFields = json_decode(Setting::get('registration_custom_fields', '[]'), true);
        $customComponents = [];

        if (!empty($customFields)) {
            foreach ($customFields as $field) {
                $fieldName = 'custom_fields.' . $field['name'];
                $label = $field['label'];
                $component = null;

                switch ($field['type']) {
                    case 'text':
                    case 'email':
                    case 'number':
                        $component = TextInput::make($fieldName)
                            ->label($label)
                            ->numeric($field['type'] === 'number')
                            ->email($field['type'] === 'email');
                        break;
                    case 'textarea':
                        $component = Textarea::make($fieldName)->label($label);
                        break;
                    case 'select':
                        $options = self::normalizeOptions($field['options'] ?? []);
                        $component = Select::make($fieldName)
                            ->label($label)
                            ->options($options);
                        break;
                    case 'radio':
                        $options = self::normalizeOptions($field['options'] ?? []);
                        $component = Radio::make($fieldName)
                            ->label($label)
                            ->options($options);
                        break;
                    case 'checkbox':
                        $component = Checkbox::make($fieldName)->label($label);
                        break;
                }

                if ($component) {
                    if ($field['required'] ?? false) {
                        $component->required();
                    }
                    $customComponents[] = $component;
                }
            }
        }

        return $schema
            ->components([
                Tabs::make('User Details')
                    ->tabs([
                        Tab::make('Customer Information')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(20),
                                Select::make('customer_category_id')
                                    ->label('Category')
                                    ->options(CustomerCategory::where('is_active', true)->whereNotNull('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                TextInput::make('nik')
                                    ->label('NIK / KTP')
                                    ->maxLength(255),
                                Textarea::make('address')
                                    ->maxLength(65535)
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Tab::make('Additional Information')
                            ->schema($customComponents)
                            ->columns(2)
                            ->visible(count($customComponents) > 0),

                        Tab::make('Tax Identity')
                            ->visible(fn () => filter_var(Setting::get('tax_enabled', true), FILTER_VALIDATE_BOOLEAN))
                            ->schema([
                                TextInput::make('tax_identity_name')
                                    ->label('Tax Name (Nama Faktur Pajak)')
                                    ->placeholder('Sesuai NPWP/KTP')
                                    ->maxLength(255),
                                TextInput::make('npwp')
                                    ->label('NPWP')
                                    ->maxLength(20),
                                TextInput::make('tax_registration_number')
                                    ->label('Tax Registration Number (TRN/VAT ID)')
                                    ->placeholder('For international customers')
                                    ->maxLength(50),
                                Select::make('tax_type')
                                    ->label('Tax Entity Type')
                                    ->options([
                                        'personal' => 'Personal (Pribadi)',
                                        'corporate' => 'Corporate (Badan Usaha)',
                                        'government' => 'Government (Instansi Pemerintah)',
                                    ])
                                    ->default('personal'),
                                Toggle::make('is_pkp')
                                    ->label('PKP (Pengusaha Kena Pajak)')
                                    ->helperText('Enable if this customer is a PKP.'),
                                Toggle::make('is_tax_exempt')
                                    ->label('Tax Exempt (Zero-Rated)')
                                    ->helperText('Enable for government entities or export services (No Tax applied).'),
                                Textarea::make('tax_address')
                                    ->label('Tax Address')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                Select::make('tax_country')
                                    ->label('Tax Country')
                                    ->options([
                                        'ID' => 'Indonesia',
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
                                    ->default('ID'),
                            ])->columns(2),

                        Tab::make('Account')
                            ->schema([
                                DateTimePicker::make('email_verified_at'),
                                TextInput::make('password')
                                    ->password()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn (?string $state) => filled($state))
                                    ->maxLength(255)
                                    ->suffixAction(
                                        \Filament\Actions\Action::make('resetPassword')
                                            ->icon('heroicon-o-arrow-path')
                                            ->color('warning')
                                            ->requiresConfirmation()
                                            ->modalHeading('Reset Password')
                                            ->modalDescription('Are you sure you want to reset this user\'s password to "resetpassword"?')
                                            ->action(function ($record) {
                                                if (!$record) return;
                                                $record->update([
                                                    'password' => 'resetpassword', // Ideally hashed, but User model casts password to hashed
                                                ]);
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Password reset successfully')
                                                    ->success()
                                                    ->send();
                                            })
                                            ->visible(fn ($record) => $record !== null)
                                            ->tooltip('Reset Password to "resetpassword"')
                                    ),
                                Select::make('roles')
                                    ->relationship('roles', 'name', fn ($query) => $query->whereNotNull('name'))
                                    ->multiple()
                                    ->preload()
                                    ->searchable(),
                                Toggle::make('is_verified')
                                    ->label('Verified Customer')
                                    ->default(false),
                            ])->columns(2),
                    ])->columnSpanFull(),
            ]);
    }
}
