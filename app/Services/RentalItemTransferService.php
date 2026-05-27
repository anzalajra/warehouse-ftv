<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\RentalItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RentalItemTransferService
{
    private const TRANSFERABLE_STATUSES = [
        Rental::STATUS_QUOTATION,
        Rental::STATUS_CONFIRMED,
        Rental::STATUS_LATE_PICKUP,
    ];

    public function move(RentalItem $sourceItem, Rental $targetRental): RentalItem
    {
        $sourceRental = $sourceItem->rental;

        $this->assertTransferable($sourceRental, 'sumber');
        $this->assertTransferable($targetRental, 'tujuan');

        if ($sourceRental->id === $targetRental->id) {
            throw new RuntimeException('Rental sumber dan tujuan tidak boleh sama.');
        }

        $unitId = $sourceItem->product_unit_id;

        $duplicate = $targetRental->items()
            ->where('product_unit_id', $unitId)
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Rental tujuan sudah memiliki unit ini.');
        }

        $serial = $sourceItem->productUnit?->serial_number ?? '#'.$unitId;
        $sourceCode = $sourceRental->rental_code;
        $targetCode = $targetRental->rental_code;

        return DB::transaction(function () use ($sourceItem, $sourceRental, $targetRental, $serial, $sourceCode, $targetCode) {
            $sourceItem->parent_item_id = null;
            $sourceItem->rental_id = $targetRental->id;
            $sourceItem->days = $this->calcDays($targetRental);
            $sourceItem->save();

            // Re-link children left behind in source rental: their parent moved away.
            // They will simply be unlinked (parent_item_id was on the moved item).
            // No further action required — RentalItem::saved hook tries to re-link in the NEW rental.

            // RentalItem has $touches = ['rental'], so saving above already triggered
            // RentalObserver::updated on the new (target) rental. The source rental,
            // however, was NOT touched because the item no longer belongs to it — bump
            // its updated_at explicitly so the observer recalculates its totals too.
            $sourceRental->touch();

            $sourceRental->refresh();
            $targetRental->refresh();

            $conflictsTarget = $targetRental->checkAvailability();
            if (!empty($conflictsTarget)) {
                throw new RuntimeException(
                    'Pemindahan menyebabkan konflik baru di rental tujuan. Operasi dibatalkan.'
                );
            }

            $this->appendNote($sourceRental, "MOVE unit {$serial} → {$targetCode}");
            $this->appendNote($targetRental, "MOVE unit {$serial} ← {$sourceCode}");

            Log::info('RentalItem MOVE', [
                'rental_item_id' => $sourceItem->id,
                'product_unit_id' => $sourceItem->product_unit_id,
                'from_rental_id' => $sourceRental->id,
                'to_rental_id' => $targetRental->id,
                'user_id' => Auth::id(),
            ]);

            return $sourceItem->fresh();
        });
    }

    public function swap(RentalItem $itemA, RentalItem $itemB): void
    {
        $rentalA = $itemA->rental;
        $rentalB = $itemB->rental;

        $this->assertTransferable($rentalA, 'A');
        $this->assertTransferable($rentalB, 'B');

        if ($rentalA->id === $rentalB->id) {
            throw new RuntimeException('Swap antar item dalam rental yang sama tidak didukung di sini.');
        }

        if ($itemA->product_unit_id === $itemB->product_unit_id) {
            throw new RuntimeException('Kedua item menunjuk unit yang sama.');
        }

        $unitAId = $itemA->product_unit_id;
        $unitBId = $itemB->product_unit_id;
        $serialA = $itemA->productUnit?->serial_number ?? '#'.$unitAId;
        $serialB = $itemB->productUnit?->serial_number ?? '#'.$unitBId;
        $codeA = $rentalA->rental_code;
        $codeB = $rentalB->rental_code;

        DB::transaction(function () use ($itemA, $itemB, $rentalA, $rentalB, $unitAId, $unitBId, $serialA, $serialB, $codeA, $codeB) {
            // Reset parents so hook can re-link cleanly.
            $itemA->parent_item_id = null;
            $itemB->parent_item_id = null;

            // Two-step swap to avoid hypothetical unique collisions, though no unique
            // constraint exists on (rental_id, product_unit_id) currently.
            $itemA->product_unit_id = null;
            $itemA->saveQuietly();

            $itemB->product_unit_id = $unitAId;
            $itemB->save();

            $itemA->product_unit_id = $unitBId;
            $itemA->save();

            // RentalItem::$touches = ['rental'] sudah men-trigger RentalObserver::updated
            // pada kedua rental saat item disimpan di atas. Tidak perlu recalc manual.
            $rentalA->refresh();
            $rentalB->refresh();

            $conflictsA = $rentalA->checkAvailability();
            $conflictsB = $rentalB->checkAvailability();

            if (!empty($conflictsA) || !empty($conflictsB)) {
                throw new RuntimeException(
                    'Swap menyebabkan konflik baru. Operasi dibatalkan.'
                );
            }

            $this->appendNote($rentalA, "SWAP {$serialA} ↔ {$serialB} dengan {$codeB}");
            $this->appendNote($rentalB, "SWAP {$serialB} ↔ {$serialA} dengan {$codeA}");

            Log::info('RentalItem SWAP', [
                'item_a_id' => $itemA->id,
                'item_b_id' => $itemB->id,
                'rental_a_id' => $rentalA->id,
                'rental_b_id' => $rentalB->id,
                'unit_a_id' => $unitAId,
                'unit_b_id' => $unitBId,
                'user_id' => Auth::id(),
            ]);
        });
    }

    private function assertTransferable(Rental $rental, string $label): void
    {
        if (!in_array($rental->status, self::TRANSFERABLE_STATUSES, true)) {
            throw new RuntimeException(
                "Rental {$label} ({$rental->rental_code}) berstatus '{$rental->status}'. " .
                'Hanya status quotation, confirmed, atau late_pickup yang boleh dipindah/swap.'
            );
        }
    }

    private function calcDays(Rental $rental): int
    {
        if (!$rental->start_date || !$rental->end_date) {
            return 1;
        }
        $days = Carbon::parse($rental->start_date)
            ->startOfDay()
            ->diffInDays(Carbon::parse($rental->end_date)->startOfDay());

        return max(1, (int) $days);
    }

    private function appendNote(Rental $rental, string $message): void
    {
        $stamp = now()->format('Y-m-d H:i');
        $user = Auth::user()?->email ?? 'system';
        $line = "[{$stamp}] {$message} oleh {$user}";

        $newNotes = trim(($rental->notes ? $rental->notes . "\n" : '') . $line);
        // updateQuietly so we don't re-trigger RentalObserver::updated (which already ran
        // when items moved). Notes change shouldn't recalc totals.
        $rental->updateQuietly(['notes' => $newNotes]);
    }
}
