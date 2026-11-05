<?php

namespace App\Console\Commands;

use App\Models\ComputerBooking;
use App\Services\ComputerValidationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessComputerBookingsCommand extends Command
{
    protected $signature = 'computer-bookings:process';

    protected $description = 'Auto-transition computer bookings (no-show / active / completed).';

    public function handle(): int
    {
        $now = Carbon::now();
        $grace = ComputerValidationService::noShowGraceMinutes();
        $today = $now->toDateString();
        $time = $now->format('H:i');

        $noShowCutoff = $now->copy()->subMinutes($grace);
        $noShowCount = $activatedCount = $completedCount = 0;
        $noShow = ComputerBooking::where('status', ComputerBooking::STATUS_CONFIRMED)
            ->whereNull('checked_in_at')
            ->where(function ($q) use ($noShowCutoff, $today) {
                $q->where('booking_date', '<', $noShowCutoff->toDateString())
                    ->orWhere(function ($q2) use ($noShowCutoff, $today) {
                        $q2->where('booking_date', $today)
                            ->where('start_time', '<=', $noShowCutoff->format('H:i'));
                    });
            })
            ->lazyById(200);

        foreach ($noShow as $booking) {
            $booking->status = ComputerBooking::STATUS_NO_SHOW;
            $booking->cancelled_reason = 'Auto-cancelled: tidak hadir dalam '.$grace.' menit.';
            $booking->save();
            $noShowCount++;
        }

        $toActivate = ComputerBooking::where('status', ComputerBooking::STATUS_CONFIRMED)
            ->whereNotNull('checked_in_at')
            ->where(function ($q) use ($today, $time) {
                $q->where('booking_date', '<', $today)
                    ->orWhere(function ($q2) use ($today, $time) {
                        $q2->where('booking_date', $today)->where('start_time', '<=', $time);
                    });
            })
            ->lazyById(200);

        foreach ($toActivate as $booking) {
            $booking->status = ComputerBooking::STATUS_ACTIVE;
            $booking->save();
            $activatedCount++;
        }

        $toComplete = ComputerBooking::where('status', ComputerBooking::STATUS_ACTIVE)
            ->where(function ($q) use ($today, $time) {
                $q->where('booking_date', '<', $today)
                    ->orWhere(function ($q2) use ($today, $time) {
                        $q2->where('booking_date', $today)->where('end_time', '<=', $time);
                    });
            })
            ->lazyById(200);

        foreach ($toComplete as $booking) {
            $booking->status = ComputerBooking::STATUS_COMPLETED;
            $booking->save();
            $completedCount++;
        }

        $this->info("Processed: no_show={$noShowCount}, activated={$activatedCount}, completed={$completedCount}");

        return self::SUCCESS;
    }
}
