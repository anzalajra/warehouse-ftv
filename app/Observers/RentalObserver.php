<?php

namespace App\Observers;

use App\Models\Rental;
use App\Models\User;
use App\Services\TaxService;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\NewBookingNotification;
use Illuminate\Support\Facades\Notification;

class RentalObserver
{
    public function created(Rental $rental): void
    {
        // Recalculate after items are saved
        $this->recalculateTotals($rental);

        // Notify Admins
        $admins = User::role(['super_admin', 'admin'])->get();
        Notification::send($admins, new NewBookingNotification($rental));
    }

    public function updated(Rental $rental): void
    {
        $this->recalculateTotals($rental);

        // Notify Customer if status changed to confirmed
        if ($rental->isDirty('status') && $rental->status === 'confirmed') {
            if ($rental->customer) {
                $rental->customer->notify(new BookingConfirmedNotification($rental));
            }
        }
    }

    protected function recalculateTotals(Rental $rental): void
    {
        $subtotal = $rental->items()->sum('subtotal');
        
        $discountAmount = 0;
        if ($rental->discount_type === 'percent') {
            $discountAmount = $subtotal * (($rental->discount ?? 0) / 100);
        } else {
            $discountAmount = $rental->discount ?? 0;
        }

        $taxableAmount = max(0, $subtotal - $discountAmount);

        // Calculate Tax using TaxService
        $taxResult = TaxService::calculateTax(
            $taxableAmount,
            $rental->is_taxable ?? false,
            $rental->price_includes_tax ?? false,
            $rental->customer
        );

        $total = $taxResult['total'];
        $ppnAmount = $taxResult['tax_amount'];
        $taxBase = $taxResult['tax_base'];

        if (
            abs(($rental->subtotal ?? 0) - $subtotal) > 0.01 || 
            abs(($rental->total ?? 0) - $total) > 0.01 ||
            abs(($rental->ppn_amount ?? 0) - $ppnAmount) > 0.01
        ) {
            $rental->updateQuietly([
                'subtotal' => $subtotal,
                'tax_base' => $taxBase,
                'ppn_amount' => $ppnAmount,
                'total' => $total,
                'ppn_rate' => $taxResult['tax_rate'],
                'tax_name' => $taxResult['tax_name'],
            ]);
        }
    }
}