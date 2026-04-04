<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerCategory;
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
                    ->columnSpanFull()
                    ->hiddenOn('edit'),

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
                                    ->required()
                                    ->searchable(),

                                Select::make('category_id')
                                    ->label('Category')
                                    ->options(Category::where('is_active', true)->pluck('name', 'id'))
                                    ->required()
                                    ->searchable(),
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
                        Group::make()
                            ->schema([
                                TextInput::make('daily_rate')
                                    ->label('Daily Rate (Rp)')
                                    ->required()
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0),

                                TextInput::make('buffer_time')
                                    ->label('Buffer Time')
                                    ->helperText('Minimum hours required between rentals for units of this product. The system will use the maximum of this value and the global buffer setting.')
                                    ->numeric()
                                    ->suffix('Hours')
                                    ->default(0)
                                    ->minValue(0),
                            ])
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
                            ->grid([
                                'default' => 1,
                                'sm' => 2,
                            ])
                            ->defaultItems(0),
                    ])
                    ->collapsed()
                    ->collapsible(),

                Toggle::make('is_active')
                    ->default(true),

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
