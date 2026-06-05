<?php

namespace App\Console\Commands;

use App\Models\ProductUnit;
use App\Models\Rental;
use App\Notifications\DailyReminderSummaryNotification;
use App\Notifications\MaintenanceReminderNotification;
use App\Notifications\OverdueAlertNotification;
use App\Notifications\PickupReminderNotification;
use App\Notifications\ReturnReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendRentalReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-rental-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send rental reminders (Pickup, Return, Overdue, Maintenance)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending rental reminders...');

        // 1. Pickup Reminder (H-1)
        // Check rentals starting tomorrow
        $pickupRentals = Rental::whereIn('status', [Rental::STATUS_QUOTATION, Rental::STATUS_CONFIRMED])
            ->whereDate('start_date', now()->addDay()->toDateString())
            ->get();

        foreach ($pickupRentals as $rental) {
            if ($rental->customer) {
                $rental->customer->notify(new PickupReminderNotification($rental));
                $this->info("Sent Pickup Reminder for {$rental->rental_code}");
            }
        }

        // 2. Return Reminder (H-1 atau sudah terlewat)
        $returnRentals = Rental::whereIn('status', [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_PICKUP, Rental::STATUS_LATE_RETURN])
            ->whereDate('end_date', '<=', now()->addDay()->toDateString())
            ->get();

        foreach ($returnRentals as $rental) {
             if ($rental->customer) {
                $rental->customer->notify(new ReturnReminderNotification($rental));
                $this->info("Sent Return Reminder for {$rental->rental_code}");
             }
        }

        // 2b. Daily summary ke admin (gabungan pickup + return H-1)
        $tomorrow = now()->addDay()->toDateString();
        $admins = \App\Models\User::role(['super_admin', 'admin', 'staff'])->get();

        // Always send the daily summary to admins — even when there is nothing
        // scheduled for tomorrow — so the absence of a notification can't be
        // mistaken for the scheduler being broken.
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new DailyReminderSummaryNotification(
                $pickupRentals->count(),
                $returnRentals->count(),
                $tomorrow,
            ));
            $this->info("Sent Daily Reminder Summary ({$pickupRentals->count()} pickup, {$returnRentals->count()} return)");
        }

        // 3. Overdue Alert
        // Check active rentals that are past due date
        $overdueRentals = Rental::whereIn('status', [Rental::STATUS_ACTIVE, Rental::STATUS_LATE_RETURN])
            ->whereDate('end_date', '<', now()->toDateString())
            ->get();

        foreach ($overdueRentals as $rental) {
            // Notify Customer
            if ($rental->customer) {
                $rental->customer->notify(new OverdueAlertNotification($rental));
            }
            // Notify Admin
            Notification::send($admins, new OverdueAlertNotification($rental));

            $this->info("Sent Overdue Alert for {$rental->rental_code}");
        }

        // 4. Maintenance Reminder
        $maintenanceUnitsCount = ProductUnit::where('status', 'maintenance')->count();
        if ($maintenanceUnitsCount > 0) {
            Notification::send($admins, new MaintenanceReminderNotification($maintenanceUnitsCount));
            $this->info("Sent Maintenance Reminder for {$maintenanceUnitsCount} units");
        }
        
        $this->info('Done.');
    }
}
