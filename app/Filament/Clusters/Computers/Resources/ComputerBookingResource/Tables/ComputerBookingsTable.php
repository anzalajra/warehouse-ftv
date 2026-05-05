<?php

namespace App\Filament\Clusters\Computers\Resources\ComputerBookingResource\Tables;

use App\Models\ComputerBooking;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ComputerBookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('booking_date', 'desc')
            ->columns([
                TextColumn::make('booking_code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('computer.name')
                    ->searchable(),
                TextColumn::make('booking_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('start_time'),
                TextColumn::make('end_time'),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'primary' => ComputerBooking::STATUS_CONFIRMED,
                        'success' => ComputerBooking::STATUS_ACTIVE,
                        'gray' => ComputerBooking::STATUS_COMPLETED,
                        'danger' => ComputerBooking::STATUS_CANCELLED,
                        'warning' => ComputerBooking::STATUS_NO_SHOW,
                    ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        ComputerBooking::STATUS_CONFIRMED => 'Confirmed',
                        ComputerBooking::STATUS_ACTIVE => 'Active',
                        ComputerBooking::STATUS_COMPLETED => 'Completed',
                        ComputerBooking::STATUS_CANCELLED => 'Cancelled',
                        ComputerBooking::STATUS_NO_SHOW => 'No Show',
                    ]),
                SelectFilter::make('computer')
                    ->relationship('computer', 'name'),
            ])
            ->actions([
                Action::make('checkIn')
                    ->label('Check-in')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ComputerBooking $record) => $record->status === ComputerBooking::STATUS_CONFIRMED && $record->checked_in_at === null)
                    ->action(function (ComputerBooking $record) {
                        $record->checked_in_at = now();
                        if ($record->startsAt()->isPast()) {
                            $record->status = ComputerBooking::STATUS_ACTIVE;
                        }
                        $record->save();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ComputerBooking $record) => in_array($record->status, [ComputerBooking::STATUS_CONFIRMED, ComputerBooking::STATUS_ACTIVE]))
                    ->form([
                        Textarea::make('reason')->required()->label('Cancel reason'),
                    ])
                    ->action(function (ComputerBooking $record, array $data) {
                        $record->status = ComputerBooking::STATUS_CANCELLED;
                        $record->cancelled_reason = $data['reason'];
                        $record->save();
                    })
                    ->requiresConfirmation(),
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
