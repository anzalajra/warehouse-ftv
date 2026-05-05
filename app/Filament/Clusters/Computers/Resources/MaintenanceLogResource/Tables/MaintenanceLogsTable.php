<?php

namespace App\Filament\Clusters\Computers\Resources\MaintenanceLogResource\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaintenanceLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('computer.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->dateTime()
                    ->placeholder('Ongoing')
                    ->sortable(),
                TextColumn::make('reason')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('createdBy.name')
                    ->label('Logged By')
                    ->placeholder('System'),
            ])
            ->filters([
                SelectFilter::make('computer')
                    ->relationship('computer', 'name'),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }
}
