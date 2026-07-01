<?php

namespace App\Filament\Clusters\Finance\Pages;

use App\Filament\Clusters\Finance\FinanceCluster;
use App\Models\BankStatementLine;
use App\Models\FinanceAccount;
use App\Models\FinanceTransaction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;

/**
 * Bank reconciliation: import a bank statement for a cash/bank account and match each
 * line to the book's FinanceTransactions, so discrepancies (unrecorded fees, missing
 * deposits) surface. Statement line amount is SIGNED (+ inflow, − outflow).
 */
class BankReconciliation extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $cluster = FinanceCluster::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationLabel = 'Rekonsiliasi Bank';

    protected static ?string $title = 'Rekonsiliasi Bank';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.clusters.finance.pages.bank-reconciliation';

    #[Url]
    public ?int $financeAccountId = null;

    public function mount(): void
    {
        $this->financeAccountId ??= FinanceAccount::where('is_active', true)->value('id');
    }

    public function getAccountOptions(): array
    {
        return FinanceAccount::where('is_active', true)->pluck('name', 'id')->toArray();
    }

    /** Reconciliation summary for the selected account. */
    public function getSummary(): array
    {
        $lines = BankStatementLine::where('finance_account_id', $this->financeAccountId)->get();
        $matched = $lines->whereNotNull('matched_transaction_id');
        $unmatched = $lines->whereNull('matched_transaction_id');

        $bookBalance = (float) (FinanceAccount::find($this->financeAccountId)?->balance ?? 0);

        return [
            'book_balance'    => $bookBalance,
            'statement_total' => round((float) $lines->sum('amount'), 2),
            'matched_count'   => $matched->count(),
            'unmatched_count' => $unmatched->count(),
            'unmatched_total' => round((float) $unmatched->sum('amount'), 2),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BankStatementLine::query()
                    ->where('finance_account_id', $this->financeAccountId ?? 0)
                    ->orderBy('date')
            )
            ->columns([
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('description')->wrap()->searchable(),
                TextColumn::make('amount')
                    ->money('IDR')
                    ->color(fn (BankStatementLine $r) => $r->amount >= 0 ? 'success' : 'danger'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (BankStatementLine $r) => $r->isMatched() ? 'Cocok' : 'Belum')
                    ->color(fn (string $state) => $state === 'Cocok' ? 'success' : 'warning'),
                TextColumn::make('transaction.description')
                    ->label('Transaksi Buku')
                    ->placeholder('—')
                    ->limit(40),
            ])
            ->headerActions([
                Action::make('import')
                    ->label('Import Statement (CSV)')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Select::make('finance_account_id')
                            ->label('Account')
                            ->options($this->getAccountOptions())
                            ->default($this->financeAccountId)
                            ->required(),
                        FileUpload::make('file')
                            ->label('CSV File')
                            ->helperText('Kolom: tanggal, keterangan, jumlah (bertanda: + masuk, − keluar). Baris header dilewati.')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                            ->disk('local')
                            ->directory('bank-statements')
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->importStatement($data)),
                Action::make('auto_match')
                    ->label('Auto-match')
                    ->icon('heroicon-o-sparkles')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn () => $this->autoMatch()),
            ])
            ->recordActions([
                Action::make('match')
                    ->label('Match')
                    ->icon('heroicon-o-link')
                    ->visible(fn (BankStatementLine $r) => ! $r->isMatched())
                    ->form(fn (BankStatementLine $r) => [
                        Select::make('transaction_id')
                            ->label('Pilih transaksi buku')
                            ->options($this->candidateOptions($r))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (BankStatementLine $r, array $data) {
                        $this->matchLine($r, (int) $data['transaction_id']);
                    }),
                Action::make('unmatch')
                    ->label('Unmatch')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (BankStatementLine $r) => $r->isMatched())
                    ->requiresConfirmation()
                    ->action(fn (BankStatementLine $r) => $this->unmatchLine($r)),
            ]);
    }

    /** Unreconciled book transactions whose signed amount equals this line's amount. */
    protected function candidateOptions(BankStatementLine $line): array
    {
        return FinanceTransaction::where('finance_account_id', $line->finance_account_id)
            ->whereNull('reconciled_at')
            ->get()
            ->filter(fn (FinanceTransaction $t) => abs($t->signedAmount() - (float) $line->amount) < 0.01)
            ->mapWithKeys(fn (FinanceTransaction $t) => [
                $t->id => $t->date?->toDateString().' · '.($t->description ?? $t->category).' · Rp '.number_format($t->signedAmount(), 0, ',', '.'),
            ])
            ->toArray();
    }

    protected function matchLine(BankStatementLine $line, int $transactionId): void
    {
        $txn = FinanceTransaction::find($transactionId);
        if (! $txn) {
            return;
        }

        $line->update(['matched_transaction_id' => $txn->id, 'reconciled_at' => now()]);
        $txn->update(['reconciled_at' => now()]);

        Notification::make()->title('Baris dicocokkan')->success()->send();
    }

    protected function unmatchLine(BankStatementLine $line): void
    {
        if ($line->matched_transaction_id) {
            FinanceTransaction::whereKey($line->matched_transaction_id)->update(['reconciled_at' => null]);
        }
        $line->update(['matched_transaction_id' => null, 'reconciled_at' => null]);

        Notification::make()->title('Pencocokan dibatalkan')->success()->send();
    }

    protected function autoMatch(): void
    {
        $lines = BankStatementLine::where('finance_account_id', $this->financeAccountId)
            ->whereNull('matched_transaction_id')
            ->get();

        $matched = 0;
        foreach ($lines as $line) {
            $candidate = FinanceTransaction::where('finance_account_id', $line->finance_account_id)
                ->whereNull('reconciled_at')
                ->get()
                ->first(fn (FinanceTransaction $t) => abs($t->signedAmount() - (float) $line->amount) < 0.01
                    && $t->date && abs($t->date->diffInDays($line->date)) <= 5);

            if ($candidate) {
                $line->update(['matched_transaction_id' => $candidate->id, 'reconciled_at' => now()]);
                $candidate->update(['reconciled_at' => now()]);
                $matched++;
            }
        }

        Notification::make()->title("Auto-match: {$matched} baris dicocokkan")->success()->send();
    }

    protected function importStatement(array $data): void
    {
        $path = $data['file'];
        if (! $path || ! Storage::disk('local')->exists($path)) {
            Notification::make()->title('File tidak ditemukan')->danger()->send();
            return;
        }

        $content = Storage::disk('local')->get($path);
        $rows = preg_split('/\r\n|\r|\n/', trim($content));
        $batch = 'IMP-'.now()->format('YmdHis');
        $created = 0;

        foreach ($rows as $i => $rawLine) {
            if ($rawLine === '') {
                continue;
            }
            $cols = str_getcsv($rawLine);
            // Skip a header row (non-numeric amount in the 3rd column).
            if ($i === 0 && ! is_numeric(str_replace([',', '.'], '', (string) ($cols[2] ?? '')))) {
                continue;
            }

            $dateRaw = trim($cols[0] ?? '');
            $desc = trim($cols[1] ?? '');
            $amountRaw = str_replace([' ', ','], '', (string) ($cols[2] ?? '0'));

            if ($dateRaw === '' || ! is_numeric($amountRaw)) {
                continue;
            }

            try {
                $date = Carbon::parse($dateRaw)->toDateString();
            } catch (\Exception $e) {
                continue;
            }

            BankStatementLine::create([
                'finance_account_id' => $data['finance_account_id'],
                'date' => $date,
                'description' => $desc,
                'amount' => (float) $amountRaw,
                'import_batch' => $batch,
            ]);
            $created++;
        }

        $this->financeAccountId = (int) $data['finance_account_id'];

        Notification::make()->title("Import selesai: {$created} baris")->success()->send();
    }
}
