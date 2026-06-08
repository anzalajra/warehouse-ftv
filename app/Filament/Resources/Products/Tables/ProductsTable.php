<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query
                ->withCount([
                    'units',
                    'units as available_units_count' => fn($query) => $query->where('status', 'available'),
                ])
                ->whereDoesntHave('category', fn ($q) => $q->where('slug', 'accessories-kits'))
            )
            ->recordClasses('!p-0 !border-none !shadow-none [&_.fi-ta-record-content]:!p-0 [&_.fi-ta-record-content-ctn]:!p-0')
            ->contentGrid([
                'sm' => 2,
                'md' => 3,
                'lg' => 4,
                'xl' => 4,
            ])
            ->columns([
                \Filament\Tables\Columns\Layout\Split::make([
                    ImageColumn::make('image')
                        ->disk('public')
                        ->square()
                        ->size(100)
                        ->extraImgAttributes([
                            'class' => 'object-cover w-full h-full rounded-l-xl',
                        ])
                        ->grow(false),

                    \Filament\Tables\Columns\Layout\Stack::make([
                        TextColumn::make('name')
                            ->weight('bold')
                            ->size('md')
                            ->searchable(query: function ($query, string $search) {
                                $query->where(function ($subQuery) use ($search) {
                                    $subQuery->where('name', 'like', "%{$search}%")
                                        ->orWhereHas('brand', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                                        ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                                });
                            }),

                        TextColumn::make('daily_rate')
                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.') . ' / 1 Day')
                            ->color('gray')
                            ->size('xs'),

                        TextColumn::make('stock_info')
                            ->getStateUsing(function ($record) {
                                return "unit: {$record->units_count} | stock: {$record->available_units_count}";
                            })
                            ->icon('heroicon-m-rectangle-stack')
                            ->color('primary')
                            ->size('xs'),

                        TextColumn::make('is_visible_on_frontend')
                            ->formatStateUsing(fn(bool $state) => $state ? 'Web Visible' : 'Admin Only')
                            ->color(fn(bool $state) => $state ? 'success' : 'warning')
                            ->icon(fn(bool $state) => $state ? 'heroicon-m-globe-alt' : 'heroicon-m-lock-closed')
                            ->size('xs'),
                    ])
                    ->grow()
                    ->space(1)
                    ->extraAttributes(['class' => 'p-2']),
                ])
                ->extraAttributes([
                    'class' => 'bg-white dark:bg-gray-900 rounded-xl overflow-hidden flex items-stretch h-full !p-0 !border-none !shadow-none',
                ]),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->paginated([48])
            ->recordActions([])
            ->recordUrl(fn($record) => \App\Filament\Resources\Products\ProductResource::getUrl('edit', ['record' => $record]))
            ->bulkActions([]);
    }
}
