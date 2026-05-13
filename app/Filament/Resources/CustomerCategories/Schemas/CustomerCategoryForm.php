<?php

namespace App\Filament\Resources\CustomerCategories\Schemas;

use App\Models\Setting;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CustomerCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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

                ColorPicker::make('badge_color')
                    ->label('Badge Color')
                    ->nullable(),

                TextInput::make('discount_percentage')
                    ->label('Discount Percentage')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->helperText('This discount will be applied to all rentals for customers in this category.'),

                TagsInput::make('benefits')
                    ->label('Benefits')
                    ->helperText('List the benefits for this category (press Enter to add).'),

                CheckboxList::make('documentTypes')
                    ->relationship('documentTypes', 'name')
                    ->label('Required Documents')
                    ->columns(2)
                    ->columnSpanFull(),

                CheckboxList::make('required_custom_fields')
                    ->label('Required Custom Registration Fields')
                    ->helperText('Pilih custom registration fields yang wajib diisi oleh customer di kategori ini. Field didefinisikan di Settings → Registration & Verification.')
                    ->options(function (): array {
                        $fields = json_decode(Setting::get('registration_custom_fields', '[]'), true) ?: [];
                        $options = [];
                        foreach ($fields as $field) {
                            if (!empty($field['name']) && !empty($field['label'])) {
                                $options[$field['name']] = $field['label'] . ' (' . $field['name'] . ')';
                            }
                        }
                        return $options;
                    })
                    ->columns(2)
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
