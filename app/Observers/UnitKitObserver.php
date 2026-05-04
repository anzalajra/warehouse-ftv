<?php

namespace App\Observers;

use App\Models\ProductUnit;
use App\Models\UnitKit;
use App\Services\KitUnitLinker;

class UnitKitObserver
{
    public function saving(UnitKit $kit): void
    {
        // Hard invariant: a kit slot cannot self-reference or point to a unit of the same product as its parent.
        // The form validators catch this in the UI; this guard catches console / seeder / import paths.
        $serialOrParentChanged = $kit->isDirty('serial_number') || $kit->isDirty('unit_id');

        if ($serialOrParentChanged && $kit->unit_id && $kit->serial_number) {
            $parent = ProductUnit::find($kit->unit_id);
            if ($parent) {
                if ($parent->serial_number === $kit->serial_number) {
                    throw new \DomainException("UnitKit serial '{$kit->serial_number}' is identical to its parent unit serial. A unit cannot be a kit component of itself.");
                }
                $candidate = ProductUnit::where('serial_number', $kit->serial_number)->first();
                if ($candidate && $candidate->product_id === $parent->product_id) {
                    throw new \DomainException("UnitKit serial '{$kit->serial_number}' belongs to another unit of the same product. A unit cannot be a kit component of another unit of the same product.");
                }
            }
        }

        $kit->linked_unit_id = app(KitUnitLinker::class)->resolveLinkedUnit([
            'track_by_serial' => $kit->track_by_serial,
            'serial_number'   => $kit->serial_number,
            'name'            => $kit->name,
            'condition'       => $kit->condition,
            'parent_unit_id'  => $kit->unit_id,
        ]);
    }
}
