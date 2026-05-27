<?php

namespace App\Filament\Clusters\Finance\Resources\FinanceAccountResource\Tables;

use App\Filament\Support\PreventDeleteIfUsed;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use App\Models\FinanceAccount;

class FinanceAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => FinanceAccount::TYPE_BANK,
                        'success' => FinanceAccount::TYPE_CASH,
                        'warning' => FinanceAccount::TYPE_EWALLET,
                    ]),
                TextColumn::make('account_number')
                    ->searchable(),
                TextColumn::make('holder_name')
                    ->searchable(),
                TextColumn::make('balance')
                    ->money('IDR')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('ledger')
                    ->label('Ledger')
                    ->icon('heroicon-o-book-open')
                    ->url(fn (FinanceAccount $record) => route('filament.admin.finance.resources.cash-and-bank.ledger', ['record' => $record])),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->before(PreventDeleteIfUsed::guard([
                        'transaksi' => fn ($record) => $record->transactions()->count(),
                    ])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records) {
                            $blocked = $records->filter(fn ($r) => $r->transactions()->exists());
                            $deletable = $records->diff($blocked);

                            foreach ($deletable as $r) {
                                $r->delete();
                            }

                            if ($blocked->isNotEmpty()) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Sebagian tidak dapat dihapus')
                                    ->body($blocked->count() . ' akun kas/bank masih memiliki transaksi dan dilewati. ' . $deletable->count() . ' akun terhapus.')
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->title($deletable->count() . ' akun dihapus')
                                    ->success()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
