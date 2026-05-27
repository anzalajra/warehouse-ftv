<?php

namespace App\Filament\Resources\Brands\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class BrandsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->circular(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('website')
                    ->toggleable()
                    ->visibleFrom('md'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalHeading(fn ($record) => "Delete brand: {$record->name}")
                    ->modalDescription(fn ($record) => new HtmlString(
                        'Menghapus brand <strong>tidak</strong> akan menghapus produk di dalamnya — produk tetap ada dan brand-nya menjadi kosong ('
                        . \App\Models\Product::where('brand_id', $record->id)->count() . ' produk terdampak).<br>'
                        . 'Untuk mencegah penghapusan tidak sengaja, ketik nama brand persis di bawah ini untuk mengkonfirmasi.'
                    ))
                    ->schema([
                        TextInput::make('confirmation_name')
                            ->label(fn ($record) => "Ketik: {$record->name}")
                            ->required()
                            ->dehydrated(false)
                            ->rule(fn ($record) => "in:{$record->name}")
                            ->validationMessages([
                                'in' => 'Nama yang Anda ketik tidak cocok dengan nama brand.',
                            ]),
                    ])
                    ->modalSubmitActionLabel('Hapus brand'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Delete selected brands')
                        ->modalDescription(new HtmlString(
                            'Produk di dalam brand-brand ini akan tetap ada (brand menjadi kosong), bukan ikut terhapus.<br>'
                            . 'Ketik <strong>HAPUS BRAND</strong> di bawah untuk mengkonfirmasi.'
                        ))
                        ->schema([
                            TextInput::make('confirmation_phrase')
                                ->label('Ketik: HAPUS BRAND')
                                ->required()
                                ->dehydrated(false)
                                ->rule('in:HAPUS BRAND')
                                ->validationMessages([
                                    'in' => 'Frasa konfirmasi tidak cocok.',
                                ]),
                        ])
                        ->modalSubmitActionLabel('Hapus brand terpilih'),
                ]),
            ]);
    }
}