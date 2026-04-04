<?php

namespace App\Filament\Clusters\Finance\Resources\FinanceTransactionResource\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\FinanceTransaction;
use Illuminate\Support\Facades\Auth;

class FinanceTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required()
                    ->default(now()),
                Select::make('finance_account_id')
                    ->relationship('account', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('type')
                    ->options([
                        FinanceTransaction::TYPE_INCOME => 'Income',
                        FinanceTransaction::TYPE_EXPENSE => 'Expense',
                        FinanceTransaction::TYPE_TRANSFER => 'Transfer',
                    ])
                    ->required()
                    ->native(false),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix('IDR'),
                TextInput::make('category')
                    ->maxLength(255)
                    ->datalist([
                        'Rental Income',
                        'Operational Expense',
                        'Maintenance',
                        'Salary',
                        'Marketing',
                    ]),
                Select::make('payment_method')
                    ->options([
                        'Cash' => 'Cash',
                        'Transfer' => 'Bank Transfer',
                        'QRIS' => 'QRIS',
                        'Credit Card' => 'Credit Card',
                    ]),
                FileUpload::make('proof_document')
                    ->disk('public')
                    ->visibility('public')
                    ->directory('finance-proofs')
                    ->image()
                    ->imageEditor(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Hidden::make('user_id')
                    ->default(fn () => Auth::id()),
            ]);
    }
}
