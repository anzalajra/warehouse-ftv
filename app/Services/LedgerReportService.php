<?php

namespace App\Services;

use App\Models\JournalEntryItem;
use Illuminate\Support\Collection;

/**
 * Financial statements derived from the GENERAL LEDGER (journal entries), not from
 * operational aggregates. This is the double-entry counterpart to FinancialReports:
 * every figure traces to posted journal lines, so the statements always articulate
 * (Trial Balance balances, Balance Sheet balances) instead of using a plugged equity.
 *
 * Normal balance convention:
 *   asset, expense   -> debit − credit
 *   liability, equity, revenue -> credit − debit
 */
class LedgerReportService
{
    /**
     * Per-account debit/credit totals within an optional date range.
     *
     * @return array{rows: Collection, total_debit: float, total_credit: float, balanced: bool}
     */
    public static function trialBalance(?string $start = null, ?string $end = null): array
    {
        $rows = self::accountTotals($start, $end)
            ->map(function ($r) {
                // Residual on the account's normal side; a negative residual (contra
                // balance) is shown on the opposite column.
                $isDebitNormal = in_array($r->type, ['asset', 'expense'], true);
                $net = $isDebitNormal
                    ? (float) $r->debit - (float) $r->credit
                    : (float) $r->credit - (float) $r->debit;

                $onNormalSide = round(max($net, 0), 2);
                $onContraSide = round(max(-$net, 0), 2);

                return [
                    'code'   => $r->code,
                    'name'   => $r->name,
                    'type'   => $r->type,
                    'debit'  => $isDebitNormal ? $onNormalSide : $onContraSide,
                    'credit' => $isDebitNormal ? $onContraSide : $onNormalSide,
                ];
            })
            ->filter(fn ($r) => abs($r['debit']) > 0.001 || abs($r['credit']) > 0.001)
            ->values();

        $totalDebit = round((float) $rows->sum('debit'), 2);
        $totalCredit = round((float) $rows->sum('credit'), 2);

        return [
            'rows'         => $rows,
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced'     => abs($totalDebit - $totalCredit) < 0.01,
        ];
    }

    /**
     * Income statement (Laba Rugi) for a period.
     *
     * @return array{revenue: Collection, expense: Collection, total_revenue: float, total_expense: float, net_income: float}
     */
    public static function incomeStatement(?string $start = null, ?string $end = null): array
    {
        $totals = self::accountTotals($start, $end);

        $revenue = $totals->where('type', 'revenue')->map(fn ($r) => [
            'code'   => $r->code,
            'name'   => $r->name,
            'amount' => round((float) $r->credit - (float) $r->debit, 2),
        ])->filter(fn ($r) => abs($r['amount']) > 0.001)->values();

        $expense = $totals->where('type', 'expense')->map(fn ($r) => [
            'code'   => $r->code,
            'name'   => $r->name,
            'amount' => round((float) $r->debit - (float) $r->credit, 2),
        ])->filter(fn ($r) => abs($r['amount']) > 0.001)->values();

        $totalRevenue = round((float) $revenue->sum('amount'), 2);
        $totalExpense = round((float) $expense->sum('amount'), 2);

        return [
            'revenue'       => $revenue,
            'expense'       => $expense,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_income'    => round($totalRevenue - $totalExpense, 2),
        ];
    }

    /**
     * Balance sheet (Neraca) cumulative up to a date. Retained/current earnings
     * (revenue − expense to date) are folded into equity so Assets = Liab + Equity.
     *
     * @return array{assets: Collection, liabilities: Collection, equity: Collection,
     *               total_assets: float, total_liabilities: float, total_equity: float,
     *               net_income: float, difference: float, balanced: bool}
     */
    public static function balanceSheet(?string $asOf = null): array
    {
        $totals = self::accountTotals(null, $asOf);

        $assets = $totals->where('type', 'asset')->map(fn ($r) => [
            'code'   => $r->code,
            'name'   => $r->name,
            'amount' => round((float) $r->debit - (float) $r->credit, 2),
        ])->filter(fn ($r) => abs($r['amount']) > 0.001)->values();

        $liabilities = $totals->where('type', 'liability')->map(fn ($r) => [
            'code'   => $r->code,
            'name'   => $r->name,
            'amount' => round((float) $r->credit - (float) $r->debit, 2),
        ])->filter(fn ($r) => abs($r['amount']) > 0.001)->values();

        $equity = $totals->where('type', 'equity')->map(fn ($r) => [
            'code'   => $r->code,
            'name'   => $r->name,
            'amount' => round((float) $r->credit - (float) $r->debit, 2),
        ])->filter(fn ($r) => abs($r['amount']) > 0.001)->values();

        $totalRevenue = round((float) $totals->where('type', 'revenue')->sum(fn ($r) => (float) $r->credit - (float) $r->debit), 2);
        $totalExpense = round((float) $totals->where('type', 'expense')->sum(fn ($r) => (float) $r->debit - (float) $r->credit), 2);
        $netIncome = round($totalRevenue - $totalExpense, 2);

        $totalAssets = round((float) $assets->sum('amount'), 2);
        $totalLiabilities = round((float) $liabilities->sum('amount'), 2);
        $totalEquity = round((float) $equity->sum('amount') + $netIncome, 2);
        $difference = round($totalAssets - ($totalLiabilities + $totalEquity), 2);

        return [
            'assets'            => $assets,
            'liabilities'       => $liabilities,
            'equity'            => $equity,
            'total_assets'      => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity'      => $totalEquity,
            'net_income'        => $netIncome,
            'difference'        => $difference,
            'balanced'          => abs($difference) < 0.01,
        ];
    }

    /**
     * General Ledger (Buku Besar) for one account: opening balance + every posted line
     * in the period with a running balance, and the closing balance. The standard drill-
     * down behind each trial-balance figure.
     *
     * @return array{account: ?array, opening: float, rows: array, closing: float, total_debit: float, total_credit: float}
     */
    public static function generalLedger(int $accountId, ?string $start = null, ?string $end = null): array
    {
        $account = \App\Models\Account::find($accountId);
        if (! $account) {
            return ['account' => null, 'opening' => 0, 'rows' => [], 'closing' => 0, 'total_debit' => 0, 'total_credit' => 0];
        }

        $isDebitNormal = in_array($account->type, ['asset', 'expense'], true);
        $sign = fn (float $d, float $c) => $isDebitNormal ? $d - $c : $c - $d;

        // Opening balance = normal-side net of everything strictly before the period start.
        $opening = 0.0;
        if ($start) {
            $before = \App\Models\JournalEntryItem::query()
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
                ->where('journal_entry_items.account_id', $accountId)
                ->whereDate('journal_entries.date', '<', $start)
                ->selectRaw('COALESCE(SUM(journal_entry_items.debit),0) as debit, COALESCE(SUM(journal_entry_items.credit),0) as credit')
                ->first();
            $opening = round($sign((float) $before->debit, (float) $before->credit), 2);
        }

        $items = \App\Models\JournalEntryItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->where('journal_entry_items.account_id', $accountId)
            ->when($start, fn ($q) => $q->whereDate('journal_entries.date', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('journal_entries.date', '<=', $end))
            ->orderBy('journal_entries.date')
            ->orderBy('journal_entries.id')
            ->selectRaw('journal_entries.date as date, journal_entries.reference_number as ref, journal_entries.description as description, journal_entry_items.debit as debit, journal_entry_items.credit as credit')
            ->get();

        $running = $opening;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $rows = [];
        foreach ($items as $it) {
            $debit = (float) $it->debit;
            $credit = (float) $it->credit;
            $running = round($running + $sign($debit, $credit), 2);
            $totalDebit += $debit;
            $totalCredit += $credit;

            $rows[] = [
                'date'        => $it->date,
                'ref'         => $it->ref,
                'description' => $it->description,
                'debit'       => round($debit, 2),
                'credit'      => round($credit, 2),
                'balance'     => $running,
            ];
        }

        return [
            'account'      => ['code' => $account->code, 'name' => $account->name, 'type' => $account->type],
            'opening'      => $opening,
            'rows'         => $rows,
            'closing'      => round($running, 2),
            'total_debit'  => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
        ];
    }

    /**
     * Per-account debit/credit sums grouped by account within an optional date range.
     */
    protected static function accountTotals(?string $start, ?string $end): Collection
    {
        return JournalEntryItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_items.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_items.account_id')
            ->when($start, fn ($q) => $q->whereDate('journal_entries.date', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('journal_entries.date', '<=', $end))
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->selectRaw('accounts.id as id, accounts.code as code, accounts.name as name, accounts.type as type, SUM(journal_entry_items.debit) as debit, SUM(journal_entry_items.credit) as credit')
            ->get();
    }
}
