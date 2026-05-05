<?php

namespace App\Observers;

use App\Models\ComputerBooking;

class ComputerBookingObserver
{
    public function creating(ComputerBooking $booking): void
    {
        if (empty($booking->booking_code)) {
            $booking->booking_code = ComputerBooking::generateBookingCode(
                $booking->booking_date instanceof \Carbon\Carbon ? $booking->booking_date : null
            );
        }

        if (empty($booking->tnc_accepted_at)) {
            $booking->tnc_accepted_at = now();
        }

        if (empty($booking->status)) {
            $booking->status = ComputerBooking::STATUS_CONFIRMED;
        }
    }

    public function updated(ComputerBooking $booking): void
    {
        // Notification dispatch placeholder for v2 (reminder + reschedule alerts).
    }
}
