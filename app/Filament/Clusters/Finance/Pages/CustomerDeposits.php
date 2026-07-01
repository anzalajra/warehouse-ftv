<?php

namespace App\Filament\Clusters\Finance\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Filament\Clusters\Finance\Widgets\CustomerDepositStats;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use App\Models\Rental;
use App\Services\JournalService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerDeposits extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected string $view = 'filament.clusters.finance.pages.customer-deposits';

    protected static ?string $cluster = FinanceCluster::class;

    protected static ?string $navigationLabel = 'Customer Deposits';
    
    protected static ?string $title = 'Customer Deposits Control';

    protected static ?int $navigationSort = 3;

    protected function getHeaderWidgets(): array
    {
        return [
            CustomerDepositStats::class,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Rental::query()
                    ->where('deposit', '>', 0) // Only rentals that require a deposit
                    ->orWhere('security_deposit_amount', '>', 0) // Or have a held amount
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('rental_code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'quotation' => 'gray',
                        'confirmed' => 'info',
                        'active' => 'success',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'late_pickup' => 'warning',
                        'late_return' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('deposit')
                    ->money('IDR')
                    ->label('Required Deposit')
                    ->sortable(),
                TextColumn::make('security_deposit_amount')
                    ->money('IDR')
                    ->label('Held Amount')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                TextColumn::make('security_deposit_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'held' => 'success',
                        'refunded' => 'info',
                        'forfeited' => 'danger',
                        'partial_refunded' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('security_deposit_status')
                    ->options([
                        'pending' => 'Pending',
                        'held' => 'Held',
                        'refunded' => 'Refunded',
                        'forfeited' => 'Forfeited',
                    ]),
            ])
            ->actions([
                // Shared factories — identical deposit GL posting as RentalsTable
                // (Dr Kas / Cr Uang Jaminan 2-1200 on receive; reverse on refund;
                // Dr 2-1200 / Cr Pendapatan Denda on deduction).
                \App\Filament\Actions\ReceiveDepositAction::make()->label('Receive'),
                \App\Filament\Actions\RefundDepositAction::make()->label('Refund'),
            ]);
    }
}