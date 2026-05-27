<?php

namespace App\Filament\Resources\Categories\Tables;

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

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->where('slug', '!=', 'accessories-kits'))
            ->columns([
                ImageColumn::make('image')
                    ->circular(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable()
                    ->visibleFrom('lg'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('is_visible_on_storefront')
                    ->label('Storefront')
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
                    ->modalHeading(fn ($record) => "Delete category: {$record->name}")
                    ->modalDescription(fn ($record) => new HtmlString(
                        'Menghapus kategori <strong>tidak</strong> akan menghapus produk di dalamnya — produk tetap ada dan akan menjadi <em>Uncategorized</em> ('
                        . $record->products()->count() . ' produk terdampak).<br>'
                        . 'Untuk mencegah penghapusan tidak sengaja, ketik nama kategori persis di bawah ini untuk mengkonfirmasi.'
                    ))
                    ->schema([
                        TextInput::make('confirmation_name')
                            ->label(fn ($record) => "Ketik: {$record->name}")
                            ->required()
                            ->dehydrated(false)
                            ->rule(fn ($record) => "in:{$record->name}")
                            ->validationMessages([
                                'in' => 'Nama yang Anda ketik tidak cocok dengan nama kategori.',
                            ]),
                    ])
                    ->modalSubmitActionLabel('Hapus kategori'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Delete selected categories')
                        ->modalDescription(new HtmlString(
                            'Produk di dalam kategori-kategori ini akan tetap ada (menjadi <em>Uncategorized</em>), bukan ikut terhapus.<br>'
                            . 'Ketik <strong>HAPUS KATEGORI</strong> di bawah untuk mengkonfirmasi.'
                        ))
                        ->schema([
                            TextInput::make('confirmation_phrase')
                                ->label('Ketik: HAPUS KATEGORI')
                                ->required()
                                ->dehydrated(false)
                                ->rule('in:HAPUS KATEGORI')
                                ->validationMessages([
                                    'in' => 'Frasa konfirmasi tidak cocok.',
                                ]),
                        ])
                        ->modalSubmitActionLabel('Hapus kategori terpilih'),
                ]),
            ]);
    }
}
