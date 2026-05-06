<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerResource\Tables;

use App\Models\Computer;
use App\Models\ComputerRoom;
use App\Models\Setting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
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
                IconColumn::make('is_online')
                    ->label('Online')
                    ->boolean()
                    ->trueIcon('heroicon-o-signal')
                    ->falseIcon('heroicon-o-no-symbol')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn (Computer $r) => $r->is_online),
                TextColumn::make('current_user')
                    ->label('Sedang Dipakai')
                    ->getStateUsing(fn (Computer $r) => optional($r->currentBookingUser())->name ?? '—'),
                TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->sortable()
                    ->toggleable(),
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
                Filter::make('online_only')
                    ->label('Online only')
                    ->toggle()
                    ->query(function ($query) {
                        $threshold = (int) (Setting::get('computer_kiosk_offline_threshold_seconds') ?? 60);

                        return $query->where('last_seen_at', '>=', now()->subSeconds($threshold));
                    }),
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
