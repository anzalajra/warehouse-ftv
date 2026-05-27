<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->toggleable()
                    ->visibleFrom('md'),
                TextColumn::make('roles.name')
                    ->badge()
                    ->label('Roles')
                    ->toggleable()
                    ->visibleFrom('sm'),
                TextColumn::make('category.name')
                    ->badge()
                    ->label('Category')
                    ->color('info')
                    ->toggleable()
                    ->visibleFrom('sm'),
                TextColumn::make('is_verified')
                    ->label('Verified')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (User $record): string => UserResource::getUrl('edit', ['record' => $record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records) {
                            $blocked = $records->filter(fn ($r) => $r->rentals()->exists() || $r->invoices()->exists());
                            $deletable = $records->diff($blocked);
                            foreach ($deletable as $r) {
                                $r->delete();
                            }
                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->title('Sebagian user tidak dapat dihapus')
                                    ->body($blocked->count() . ' user masih punya riwayat sewa/invoice (data finansial harus dipertahankan). ' . $deletable->count() . ' user terhapus.')
                                    ->warning()->persistent()->send();
                            } else {
                                Notification::make()->title($deletable->count() . ' user dihapus')->success()->send();
                            }
                        }),
                ]),
            ]);
    }
}
