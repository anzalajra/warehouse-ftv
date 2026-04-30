<?php

namespace App\Console\Commands;

use App\Models\UnitKit;
use Illuminate\Console\Command;

class AuditKitLinks extends Command
{
    protected $signature = 'kits:audit-links {--fix : Null out linked_unit_id on invalid rows}';

    protected $description = 'Find UnitKit rows whose linked_unit_id points to a self-reference or to a sibling unit of the same product';

    public function handle(): int
    {
        $fix = $this->option('fix');

        $bad = UnitKit::with(['unit.product', 'linkedUnit.product'])
            ->whereNotNull('linked_unit_id')
            ->get()
            ->filter(function (UnitKit $k) {
                if (! $k->unit || ! $k->linkedUnit) {
                    return false;
                }
                if ($k->unit->id === $k->linkedUnit->id) {
                    return true;
                }
                return $k->unit->product_id === $k->linkedUnit->product_id;
            });

        if ($bad->isEmpty()) {
            $this->info('No invalid kit links found.');
            return self::SUCCESS;
        }

        $this->warn("Found {$bad->count()} invalid kit link(s):");
        foreach ($bad as $k) {
            $this->line(sprintf(
                '  kit#%d  parent unit#%d (%s) → linked unit#%d (%s)  [product: %s]',
                $k->id,
                $k->unit_id,
                $k->unit->serial_number,
                $k->linked_unit_id,
                $k->linkedUnit->serial_number,
                $k->unit->product->name ?? '?'
            ));
        }

        if (! $fix) {
            $this->info('Re-run with --fix to null out linked_unit_id on the rows above.');
            return self::SUCCESS;
        }

        // updateQuietly skips UnitKitObserver::saving so we don't immediately re-resolve.
        // Operators can then re-edit the kit serial intentionally.
        $bad->each(fn (UnitKit $k) => $k->updateQuietly(['linked_unit_id' => null]));
        $this->info("Cleared linked_unit_id on {$bad->count()} row(s).");

        return self::SUCCESS;
    }
}
