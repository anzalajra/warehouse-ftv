<?php

namespace App\Observers;

use App\Models\Computer;
use App\Models\ComputerMaintenanceLog;

class ComputerObserver
{
    public function updating(Computer $computer): void
    {
        if (! $computer->isDirty('status')) {
            return;
        }

        $newStatus = $computer->status;
        $oldStatus = $computer->getOriginal('status');

        if ($newStatus === Computer::STATUS_MAINTENANCE && $oldStatus !== Computer::STATUS_MAINTENANCE) {
            ComputerMaintenanceLog::create([
                'computer_id' => $computer->id,
                'started_at' => now(),
                'reason' => $computer->maintenance_reason ?? 'Maintenance',
                'created_by' => auth()->id(),
            ]);
        }

        if ($oldStatus === Computer::STATUS_MAINTENANCE && $newStatus !== Computer::STATUS_MAINTENANCE) {
            ComputerMaintenanceLog::where('computer_id', $computer->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->limit(1)
                ->update(['ended_at' => now()]);
        }
    }
}
