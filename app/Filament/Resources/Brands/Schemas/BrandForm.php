<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BrandForm
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

                FileUpload::make('logo')
                    ->image()
                    ->disk('public')
                    ->visibility('public')
                    ->directory('brands'),

                TextInput::make('website')
                    ->url()
                    ->default(null)
                    ->placeholder('https://www.sony.com'),

                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}