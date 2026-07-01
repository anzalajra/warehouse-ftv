<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Invoice;
use Carbon\Carbon;

/**
 * Accounts Receivable / Accounts Payable aging schedules (umur piutang & hutang).
 * Buckets each open document by how overdue it is relative to its due date, so
 * collections/payments can be prioritized.
 */
class AgingReportService
{
    public const BUCKETS = ['current', '1_30', '31_60', '61_90', 'over_90'];

    public const BUCKET_LABELS = [
        'current' => 'Belum Jatuh Tempo',
        '1_30'    => '1–30 hari',
        '31_60'   => '31–60 hari',
        '61_90'   => '61–90 hari',
        'over_90' => '> 90 hari',
    ];

    /**
     * AR aging from unpaid invoices.
     *
     * @return array{rows: array, totals: array, grand_total: float}
     */
    public static function receivables(?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf)->endOfDay() : now()->endOfDay();

        $invoices = Invoice::query()
            ->with('user')
            ->whereRaw('total > paid_amount')
            ->whereNotIn('status', ['cancelled'])
            ->get();

        $rows = [];
        foreach ($invoices as $inv) {
            $balance = (float) $inv->total - (float) $inv->paid_amount;
            if ($balance <= 0.01) {
                continue;
            }

            $rows[] = self::row(
                $inv->number,
                $inv->user?->name ?? '—',
                $inv->due_date,
                $balance,
                $asOf,
            );
        }

        return self::summarize($rows);
    }

    /**
     * AP aging from unpaid bills.
     *
     * @return array{rows: array, totals: array, grand_total: float}
     */
    public static function payables(?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf)->endOfDay() : now()->endOfDay();

        $bills = Bill::query()
            ->whereRaw('amount > paid_amount')
            ->where('status', '!=', Bill::STATUS_PAID)
            ->get();

        $rows = [];
        foreach ($bills as $bill) {
            $balance = (float) $bill->amount - (float) $bill->paid_amount;
            if ($balance <= 0.01) {
                continue;
            }

            $rows[] = self::row(
                $bill->bill_number ?: ('BILL-' . $bill->id),
                $bill->vendor_name ?: '—',
                $bill->due_date,
                $balance,
                $asOf,
            );
        }

        return self::summarize($rows);
    }

    protected static function row(string $number, string $party, $dueDate, float $balance, Carbon $asOf): array
    {
        $bucket = self::bucketFor($dueDate, $asOf);

        return [
            'number'  => $number,
            'party'   => $party,
            'due_date' => $dueDate ? Carbon::parse($dueDate)->toDateString() : null,
            'balance' => round($balance, 2),
            'bucket'  => $bucket,
        ];
    }

    protected static function bucketFor($dueDate, Carbon $asOf): string
    {
        if (! $dueDate) {
            return 'current';
        }

        $due = Carbon::parse($dueDate)->endOfDay();
        if ($asOf->lte($due)) {
            return 'current';
        }

        $daysOverdue = $due->diffInDays($asOf);

        return match (true) {
            $daysOverdue <= 30 => '1_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default            => 'over_90',
        };
    }

    /**
     * @param array $rows
     * @return array{rows: array, totals: array, grand_total: float}
     */
    protected static function summarize(array $rows): array
    {
        $totals = array_fill_keys(self::BUCKETS, 0.0);
        foreach ($rows as $r) {
            $totals[$r['bucket']] += $r['balance'];
        }

        return [
            'rows'        => $rows,
            'totals'      => array_map(fn ($v) => round($v, 2), $totals),
            'grand_total' => round(array_sum($totals), 2),
        ];
    }
}
