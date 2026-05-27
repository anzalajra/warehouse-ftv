<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerCategory;
use App\Models\ProductTag;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
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

                        TextInput::make('buffer_time')
                            ->label('Buffer Time')
                            ->helperText('Minimum hours required between rentals for units of this product. The system will use the maximum of this value and the global buffer setting.')
                            ->numeric()
                            ->suffix('Hours')
                            ->default(0)
                            ->minValue(0)
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
            ]);
    }
}
