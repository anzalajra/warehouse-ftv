<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerCategory;
use App\Models\ProductTag;
use App\Support\CustomFields;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        $customComponents = self::customFieldComponents();

        return $schema
            ->components([
                // Toggles (Visible only on Create)
                Section::make()
                    ->schema([
                        Toggle::make('is_active')
                            ->default(true),

                        Toggle::make('is_visible_on_frontend')
                            ->label('Website')
                            ->default(true)
                            ->helperText('If disabled, this product will only be available for admin rental.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                // Top Section: Image and Basic Details
                Section::make()
                    ->schema([
                        FileUpload::make('image')
                            ->image()
                            ->disk('public')
                            ->visibility('public')
                            ->directory('products')
                            ->columnSpan(1),

                        Group::make()
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, callable $set) {
                                        $set('slug', Str::slug($state ?? ''));
                                    }),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),

                                Select::make('brand_id')
                                    ->label('Brand')
                                    ->options(Brand::where('is_active', true)->pluck('name', 'id'))
                                    ->placeholder('No brand')
                                    ->searchable()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('website')
                                            ->url()
                                            ->maxLength(255),
                                        Toggle::make('is_active')
                                            ->default(true),
                                    ])
                                    ->createOptionUsing(fn (array $data) => Brand::create($data)->getKey()),

                                Select::make('category_id')
                                    ->label('Category')
                                    ->options(Category::where('is_active', true)->pluck('name', 'id'))
                                    ->placeholder('Uncategorized')
                                    ->searchable()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('description')
                                            ->maxLength(255),
                                        Toggle::make('is_active')
                                            ->default(true),
                                        Toggle::make('is_visible_on_storefront')
                                            ->default(true),
                                    ])
                                    ->createOptionUsing(fn (array $data) => Category::create($data)->getKey()),
                            ])
                            ->columns(2)
                            ->columnSpan(1),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                // Description
                Section::make()
                    ->schema([
                        RichEditor::make('description')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // Pricing and Variations
                Section::make()
                    ->schema([
                        TextInput::make('daily_rate')
                            ->label('Daily Rate (Rp)')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->columnSpan(1),

                        TextInput::make('hourly_rate')
                            ->label('Hourly Rate (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->placeholder('Kosongkan = otomatis dari harga harian')
                            ->columnSpan(1),

                        TextInput::make('weekly_rate')
                            ->label('Weekly Rate (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->placeholder('Kosongkan = otomatis dari harga harian')
                            ->columnSpan(1),

                        TextInput::make('monthly_rate')
                            ->label('Monthly Rate (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->placeholder('Kosongkan = otomatis dari harga harian')
                            ->columnSpan(1),

                        TextInput::make('buffer_time')
                            ->label('Buffer Time')
                            ->helperText('Minimum hours required between rentals for units of this product. The system will use the maximum of this value and the global buffer setting.')
                            ->numeric()
                            ->suffix('Hours')
                            ->default(0)
                            ->minValue(0)
                            ->columnSpan(1),

                        TextInput::make('late_fee_daily_amount')
                            ->label('Denda Telat Harian (Override)')
                            ->helperText('Tarif dasar denda keterlambatan per unit per hari khusus produk ini. Kosongkan untuk mengikuti tarif sewa harian / setting global.')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->placeholder('Ikuti tarif sewa / global')
                            ->columnSpan(1),

                        Select::make('tags')
                            ->label('Tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->helperText('Tags akan ditampilkan sebagai capsule di storefront dan dapat digunakan sebagai filter.')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('color')
                                    ->label('Color (hex)')
                                    ->placeholder('#3b82f6')
                                    ->maxLength(32),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return ProductTag::create($data)->getKey();
                            })
                            ->columnSpan(1),

                        Repeater::make('variations')
                            ->relationship('variations')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Variation Name')
                                    ->placeholder('e.g. 5 Meter')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('daily_rate')
                                    ->label('Override Daily Rate')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('Leave empty to use product rate'),

                                TextInput::make('hourly_rate')
                                    ->label('Override Hourly Rate')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('Leave empty to use product rate'),

                                TextInput::make('weekly_rate')
                                    ->label('Override Weekly Rate')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('Leave empty to use product rate'),

                                TextInput::make('monthly_rate')
                                    ->label('Override Monthly Rate')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->placeholder('Leave empty to use product rate'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add Variation')
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsed()
                    ->collapsible(),

                Toggle::make('is_taxable')
                    ->label('Taxable (Kena Pajak)')
                    ->default(true)
                    ->helperText('If disabled, this product will be excluded from tax calculations.'),

                Toggle::make('price_includes_tax')
                    ->label('Price Includes Tax (Harga Termasuk Pajak)')
                    ->default(false)
                    ->helperText('If enabled, the price is considered inclusive of tax.'),

                CheckboxList::make('excludedCustomerCategories')
                    ->label('Hide from Customer Categories')
                    ->relationship('excludedCustomerCategories', 'name')
                    ->options(CustomerCategory::where('is_active', true)->pluck('name', 'id'))
                    ->columns(2)
                    ->helperText('Selected categories will NOT be able to see this product.'),

                Section::make('Informasi Tambahan')
                    ->description('Custom fields produk (dikelola di Settings → Product Setup).')
                    ->schema($customComponents)
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(count($customComponents) > 0),
            ]);
    }

    /**
     * Build Filament components for the admin-defined product custom fields.
     * Each maps to the JSON `custom_fields` column via `custom_fields.{name}`.
     */
    protected static function customFieldComponents(): array
    {
        $components = [];

        foreach (CustomFields::definitions('product_custom_fields') as $field) {
            $name = 'custom_fields.' . $field['name'];
            $label = $field['label'] ?? $field['name'];
            $type = $field['type'] ?? 'text';
            $component = null;

            switch ($type) {
                case 'text':
                case 'email':
                case 'number':
                    $component = TextInput::make($name)
                        ->label($label)
                        ->numeric($type === 'number')
                        ->email($type === 'email');
                    break;
                case 'textarea':
                    $component = Textarea::make($name)->label($label);
                    break;
                case 'select':
                    $component = Select::make($name)
                        ->label($label)
                        ->options(CustomFields::parseOptions($field['options'] ?? ''));
                    break;
                case 'radio':
                    $component = Radio::make($name)
                        ->label($label)
                        ->options(CustomFields::parseOptions($field['options'] ?? ''));
                    break;
                case 'checkbox':
                    $component = Checkbox::make($name)->label($label);
                    break;
            }

            if ($component) {
                if ($field['required'] ?? false) {
                    $component->required();
                }
                $components[] = $component;
            }
        }

        return $components;
    }
}
