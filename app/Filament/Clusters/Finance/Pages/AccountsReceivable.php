<?php

namespace App\Filament\Clusters\Finance\Pages;

use App\Filament\Actions\RecordPaymentAction;
use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Invoice;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;

class AccountsReceivable extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static ?string $navigationLabel = 'Accounts Receivable';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.clusters.finance.pages.accounts-receivable';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Invoice::query()
                    ->where('status', '!=', Invoice::STATUS_PAID)
                    ->whereRaw('total > paid_amount')
            )
            ->columns([
                TextColumn::make('number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('total')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('paid_amount')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('balance_due')
                    ->label('Due')
                    ->money('IDR')
                    ->state(fn (Invoice $record): float => $record->balance),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Invoice::getStatusOptions()[$state] ?? $state),
            ])
            ->filters([
                //
            ])
            ->recordUrl(fn (Invoice $record): string => ViewInvoice::getUrl(['record' => $record]))
            ->actions([
                // Shared action — keeps journal-entry posting consistent with the
                // Invoice resource (this page previously skipped journal entries).
                RecordPaymentAction::make(),
                Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Invoice $record): string => ViewInvoice::getUrl(['record' => $record])),
            ])
            ->bulkActions([
                //
            ]);
    }
}
