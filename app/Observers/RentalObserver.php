<?php

namespace App\Observers;

use App\Models\Rental;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\NewBookingNotification;
use App\Notifications\RentalCompletedNotification;
use App\Services\TaxService;
use Illuminate\Support\Facades\Notification;

class RentalObserver
{
    public function created(Rental $rental): void
    {
        // Recalculate after items are saved
        $this->recalculateTotals($rental);

        // Notify Admins
        $admins = User::role(['super_admin', 'admin', 'staff'])->get();
        Notification::send($admins, new NewBookingNotification($rental));
    }

    public function updated(Rental $rental): void
    {
        $this->recalculateTotals($rental);

        if ($rental->isDirty('status')) {
            // Record the status transition in the activity log (separate from notes).
            $from = $rental->getOriginal('status');
            if ($from && $from !== $rental->status) {
                $rental->logActivity(
                    'Status: '.Rental::getStatusLabel($from).' → '.Rental::getStatusLabel($rental->status),
                    'status'
                );
            }

            // Notify Customer if status changed to confirmed
            if ($rental->status === Rental::STATUS_CONFIRMED && $rental->customer) {
                $rental->customer->notify(new BookingConfirmedNotification($rental));
            }

            // Notify admins + customer when rental is completed
            if ($rental->status === Rental::STATUS_COMPLETED) {
                $admins = User::role(['super_admin', 'admin', 'staff'])->get();
                Notification::send($admins, new RentalCompletedNotification($rental));

                if ($rental->user) {
                    $rental->user->notify(new RentalCompletedNotification($rental));
                }
            }
        }
    }

    public function saved(Rental $rental): void
    {
        $rental->refreshUnitStatuses();
    }

    public function deleting(Rental $rental): void
    {
        $units = $rental->items->map(fn ($item) => $item->productUnit)->filter();

        Rental::deleted(function () use ($units) {
            foreach ($units as $unit) {
                $unit->refreshStatus();
            }
        });
    }

    protected function recalculateTotals(Rental $rental): void
    {
        // Don't override subtotal and total to 0 when it's newly created
        // and doesn't have items yet, but already has a valid subtotal
        $subtotal = $rental->items()->sum('subtotal');

        if ($subtotal == 0 && $rental->subtotal > 0 && ! $rental->exists) {
            // Keep the assigned subtotal
            $subtotal = $rental->subtotal;
        } elseif ($subtotal == 0 && $rental->subtotal > 0 && $rental->items()->count() == 0) {
            // In case created event fires and items count is 0
            $subtotal = $rental->subtotal;
        }

        $discountAmount = 0;
        if ($rental->discount_type === 'percent') {
            $discountAmount = $subtotal * (($rental->discount ?? 0) / 100);
        } else {
            $discountAmount = $rental->discount ?? 0;
        }

        $dailyDiscountAmount = $rental->daily_discount_amount ?? 0;
        $datePromotionAmount = $rental->date_promotion_amount ?? 0;
        $categoryDiscountAmount = $rental->category_discount_amount ?? 0;

        $totalDiscount = $discountAmount + $dailyDiscountAmount + $datePromotionAmount + $categoryDiscountAmount;

        $taxableAmount = max(0, $subtotal - $totalDiscount);

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
