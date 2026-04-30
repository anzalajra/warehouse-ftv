<?php

namespace App\Observers;

use App\Models\ProductUnit;
use App\Models\UnitKit;

class ProductUnitObserver
{
    /**
     * Keep UnitKit.serial_number in sync when a ProductUnit's serial changes.
     *
     * UnitKit rows that link to this unit (linked_unit_id = $unit->id) carry their own
     * serial_number column for display + resolver lookups. If the parent ProductUnit's
     * serial is renamed (typo correction, etc.), those linked rows must follow — otherwise
     * KitUnitLinker will later fail to resolve the slot and either null the link or
     * spawn a duplicate ghost unit.
     *
     * Use updateQuietly to skip UnitKitObserver::saving (which would re-resolve and
     * potentially fight us). The link is already correct; only the stored serial drifts.
     */
    public function updated(ProductUnit $unit): void
    {
        if (! $unit->wasChanged('serial_number')) {
            return;
        }

        $newSerial = $unit->serial_number;

        UnitKit::where('linked_unit_id', $unit->id)
            ->where('serial_number', '!=', $newSerial)
            ->get()
            ->each(fn (UnitKit $kit) => $kit->updateQuietly(['serial_number' => $newSerial]));
    }
}
