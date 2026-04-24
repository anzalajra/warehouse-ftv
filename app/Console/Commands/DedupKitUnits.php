<?php

namespace App\Console\Commands;

use App\Models\ProductUnit;
use App\Models\UnitKit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupKitUnits extends Command
{
    protected $signature = 'kits:dedupe {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Merge ghost KIT-XXXX ProductUnits into their real serial-number units and clean up orphans';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('[DRY RUN] No changes will be written.');
        }

        $mergeCount  = 0;
        $deleteCount = 0;

        // Find all UnitKit rows that have a linked_unit_id pointing to a KIT-* ghost unit
        // while a "real" ProductUnit with the same serial_number exists separately.
        $kits = UnitKit::with(['linkedUnit.product'])
            ->whereNotNull('linked_unit_id')
            ->whereNotNull('serial_number')
            ->get();

        foreach ($kits as $kit) {
            $linkedUnit = $kit->linkedUnit;

            if (!$linkedUnit) {
                continue;
            }

            // Only process ghost units (serial starts with KIT-)
            if (!str_starts_with($linkedUnit->serial_number, 'KIT-')) {
                continue;
            }

            // Look for the "real" unit matching this kit's serial_number
            $realUnit = ProductUnit::where('serial_number', $kit->serial_number)
                ->where('id', '!=', $linkedUnit->id)
                ->first();

            if (!$realUnit) {
                continue;
            }

            $this->line(sprintf(
                '  Merging kit #%d (%s / serial %s): ghost unit #%d → real unit #%d',
                $kit->id,
                $kit->name,
                $kit->serial_number,
                $linkedUnit->id,
                $realUnit->id
            ));

            if (!$dryRun) {
                DB::transaction(function () use ($kit, $realUnit, $linkedUnit) {
                    // Point all UnitKit rows that reference the ghost unit to the real unit
                    UnitKit::where('linked_unit_id', $linkedUnit->id)
                        ->update(['linked_unit_id' => $realUnit->id]);

                    // Delete the ghost ProductUnit if nothing else references it
                    $stillReferenced = UnitKit::where('linked_unit_id', $linkedUnit->id)->exists();
                    if (!$stillReferenced) {
                        $linkedUnit->delete();
                    }
                });
            }

            $mergeCount++;
        }

        // Second pass: delete any remaining KIT-* ProductUnits that no UnitKit references at all
        $orphans = ProductUnit::where('serial_number', 'like', 'KIT-%')
            ->whereDoesntHave('kits')
            ->whereDoesntHave('rentalItems')
            ->get();

        foreach ($orphans as $orphan) {
            $this->line(sprintf(
                '  Deleting orphan ghost unit #%d (serial %s, product: %s)',
                $orphan->id,
                $orphan->serial_number,
                $orphan->product->name ?? '?'
            ));

            if (!$dryRun) {
                $orphan->delete();
            }

            $deleteCount++;
        }

        $this->info(sprintf(
            '%sMerged %d kit(s), deleted %d orphan ghost unit(s).',
            $dryRun ? '[DRY RUN] ' : '',
            $mergeCount,
            $deleteCount
        ));

        return self::SUCCESS;
    }
}
