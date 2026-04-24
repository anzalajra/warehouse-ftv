<?php

namespace App\Observers;

use App\Models\UnitKit;
use App\Services\KitUnitLinker;

class UnitKitObserver
{
    public function saving(UnitKit $kit): void
    {
        $kit->linked_unit_id = app(KitUnitLinker::class)->resolveLinkedUnit([
            'track_by_serial' => $kit->track_by_serial,
            'serial_number'   => $kit->serial_number,
            'name'            => $kit->name,
            'condition'       => $kit->condition,
        ]);
    }
}
