<?php

namespace App\Console\Commands;

use App\Models\ProductUnit;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\MaintenanceDueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class FlagMaintenanceDueCommand extends Command
{
    protected $signature = 'maintenance:flag-due';

    protected $description = 'Notify admins about units due for QC or preventive service';

    public function handle(): int
    {
        $qcInterval = (int) Setting::get('maintenance_qc_interval_days', 90);
        $preventiveCount = (int) Setting::get('maintenance_preventive_rental_count', 0);

        $qcDue = 0;
        if ($qcInterval > 0) {
            $qcDue = ProductUnit::where('status', ProductUnit::STATUS_AVAILABLE)
                ->where(fn ($q) => $q
                    ->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<', now()->subDays($qcInterval)))
                ->count();
        }

        $preventiveDue = 0;
        if ($preventiveCount > 0) {
            ProductUnit::whereIn('status', [ProductUnit::STATUS_AVAILABLE, ProductUnit::STATUS_SCHEDULED])
                ->chunkById(200, function ($units) use (&$preventiveDue, $preventiveCount) {
                    foreach ($units as $unit) {
                        if ($unit->rentals_since_last_maintenance >= $preventiveCount) {
                            $preventiveDue++;
                        }
                    }
                });
        }

        $this->info("QC due: {$qcDue} · Preventive due: {$preventiveDue}");

        if ($qcDue === 0 && $preventiveDue === 0) {
            return self::SUCCESS;
        }

        $admins = User::role(['super_admin', 'admin', 'staff'])->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new MaintenanceDueNotification($qcDue, $preventiveDue));
            $this->info("Notified {$admins->count()} admin(s).");
        }

        return self::SUCCESS;
    }
}
