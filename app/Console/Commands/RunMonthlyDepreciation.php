<?php

namespace App\Console\Commands;

use App\Models\DepreciationRun;
use App\Models\ProductUnit;
use App\Services\JournalService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunMonthlyDepreciation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:run-depreciation {--month= : The month to run for (YYYY-MM)} {--force : Force run even if already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and record monthly depreciation for all assets';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $period = $this->option('month') ?? now()->format('Y-m');
        $force = $this->option('force');

        $this->info("Running depreciation for period: $period");

        // Check if already run
        $existingRun = DepreciationRun::where('period', $period)->first();
        if ($existingRun && !$force) {
            $this->error("Depreciation for $period already exists. Use --force to overwrite (will delete old run).");
            return 1;
        }

        if ($existingRun && $force) {
            // Rollback journal entry?
            // Assuming JournalService handles cascading deletes if reference is deleted, or we manually delete.
            // JournalEntry is polymorphic linked to DepreciationRun.
            // But JournalEntry doesn't cascade delete by default unless set up in DB.
            // We should find the journal entry and delete it.
            $journalEntry = \App\Models\JournalEntry::where('reference_type', DepreciationRun::class)
                ->where('reference_id', $existingRun->id)
                ->first();
            
            if ($journalEntry) {
                // Delete items first
                $journalEntry->items()->delete();
                $journalEntry->delete();
            }

            // Reverse the per-unit accumulated depreciation this run added, then drop
            // its lines — otherwise re-running would double-count on the units.
            foreach (\App\Models\DepreciationRunItem::where('depreciation_run_id', $existingRun->id)->get() as $item) {
                ProductUnit::whereKey($item->product_unit_id)->decrement('accumulated_depreciation', $item->amount);
            }
            \App\Models\DepreciationRunItem::where('depreciation_run_id', $existingRun->id)->delete();

            $existingRun->delete();
            $this->warn("Deleted existing run for $period.");
        }

        // Calculate Depreciation
        // Get all active units (not retired)
        // We only depreciate units purchased before the end of the target month.
        $targetDate = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
        
        $units = ProductUnit::where('status', '!=', ProductUnit::STATUS_RETIRED)
            ->whereNotNull('purchase_date')
            ->whereNotNull('purchase_price')
            ->where('purchase_date', '<=', $targetDate)
            ->get();

        $totalDepreciation = 0;
        $itemsProcessed = 0;
        $lines = []; // per-unit depreciation to persist

        foreach ($units as $unit) {
            $cost = (float) $unit->purchase_price;
            $residual = (float) ($unit->residual_value ?? 0);
            $lifeMonths = (int) ($unit->useful_life ?? 60); // Default 5 years

            if ($lifeMonths <= 0) {
                continue;
            }

            // Cap by the remaining depreciable base so accumulated never exceeds
            // (cost − residual). This makes book value stable/historical instead of
            // recomputed retroactively from the age each read.
            $depreciableBase = max(0, $cost - $residual);
            $accumulated = (float) ($unit->accumulated_depreciation ?? 0);
            $remaining = round($depreciableBase - $accumulated, 2);

            if ($remaining <= 0) {
                continue;
            }

            $monthlyDepreciation = $depreciableBase / $lifeMonths;
            $thisMonth = round(min($monthlyDepreciation, $remaining), 2);

            if ($thisMonth <= 0) {
                continue;
            }

            $totalDepreciation += $thisMonth;
            $itemsProcessed++;
            $lines[] = [
                'unit_id' => $unit->id,
                'amount' => $thisMonth,
                'accumulated_after' => round($accumulated + $thisMonth, 2),
            ];
        }

        $totalDepreciation = round($totalDepreciation, 2);

        if ($totalDepreciation <= 0) {
            $this->info("No depreciation to record for this period.");
            return 0;
        }

        $this->info("Total Depreciation: Rp " . number_format($totalDepreciation, 2));
        $this->info("Items Processed: $itemsProcessed");

        // Record Run
        DB::transaction(function () use ($period, $totalDepreciation, $itemsProcessed, $targetDate, $lines) {
            $run = DepreciationRun::create([
                'date' => $targetDate,
                'period' => $period,
                'total_amount' => $totalDepreciation,
                'items_processed' => $itemsProcessed,
                'notes' => "Auto-generated monthly depreciation for $itemsProcessed items.",
            ]);

            // Persist per-unit lines and bump each unit's accumulated depreciation.
            foreach ($lines as $line) {
                \App\Models\DepreciationRunItem::create([
                    'depreciation_run_id' => $run->id,
                    'product_unit_id' => $line['unit_id'],
                    'amount' => $line['amount'],
                    'accumulated_after' => $line['accumulated_after'],
                ]);
                ProductUnit::whereKey($line['unit_id'])->increment('accumulated_depreciation', $line['amount']);
            }

            // Create Journal Entry
            JournalService::recordSimpleTransaction(
                'MONTHLY_DEPRECIATION',
                $run,
                $totalDepreciation,
                "Beban Penyusutan Peralatan Periode $period"
            );
        });

        $this->info("Depreciation run completed successfully.");
        return 0;
    }
}
