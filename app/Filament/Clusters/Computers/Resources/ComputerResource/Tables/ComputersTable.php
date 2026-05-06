<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerResource\Tables;

use App\Models\Computer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use App\Models\ComputerRoom;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComputersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->disk('public')
                    ->label('Image')
                    ->circular(),
                TextColumn::make('room.name')
                    ->label('Room')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->toggleable(),
                TextColumn::make('brand')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => Computer::STATUS_AVAILABLE,
                        'warning' => Computer::STATUS_MAINTENANCE,
                        'gray' => Computer::STATUS_RETIRED,
                    ]),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('room_id')
                    ->label('Room')
                    ->options(fn () => ComputerRoom::pluck('name', 'id')->toArray()),
                SelectFilter::make('status')
                    ->options([
                        Computer::STATUS_AVAILABLE => 'Available',
                        Computer::STATUS_MAINTENANCE => 'Maintenance',
                        Computer::STATUS_RETIRED => 'Retired',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
