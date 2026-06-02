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

    /**
     * Move a single physical unit (serial) from a source rental item into a target rental.
     *
     * The unit's *assignment* is transferred, not the row itself:
     *  - Source rental KEEPS its product line; the source item becomes an empty
     *    "ghost" slot (product_unit_id = null). Its quantity is unchanged — only the
     *    assigned-unit count drops by one. The product is never removed from the table.
     *  - Target rental: if it already has a matching empty slot for the same
     *    product/variation, that slot is FILLED (quantity unchanged). Otherwise a new
     *    row is created — a genuine stock addition (quantity +1).
     *
     * Returns the target-side RentalItem now holding the unit.
     */
    public function move(RentalItem $sourceItem, Rental $targetRental): RentalItem
    {
        $sourceRental = $sourceItem->rental;

        $this->assertTransferable($sourceRental, 'sumber');
        $this->assertTransferable($targetRental, 'tujuan');

        if ($sourceRental->id === $targetRental->id) {
            throw new RuntimeException('Rental sumber dan tujuan tidak boleh sama.');
        }

        $unitId = $sourceItem->product_unit_id;
        if (!$unitId) {
            throw new RuntimeException('Item sumber belum punya unit serial untuk dipindahkan.');
        }

        $duplicate = $targetRental->items()
            ->where('product_unit_id', $unitId)
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Rental tujuan sudah memiliki unit ini.');
        }

        // Resolve product/variation for ghost-slot matching (fall back to the unit itself
        // for older rows that may not have product_id populated).
        $productId = $sourceItem->product_id ?? $sourceItem->productUnit?->product_id;
        $variationId = $sourceItem->product_variation_id ?? $sourceItem->productUnit?->product_variation_id;

        $serial = $sourceItem->productUnit?->serial_number ?? '#'.$unitId;
        $sourceCode = $sourceRental->rental_code;
        $targetCode = $targetRental->rental_code;

        return DB::transaction(function () use ($sourceItem, $sourceRental, $targetRental, $unitId, $productId, $variationId, $serial, $sourceCode, $targetCode) {
            // ── Target side: assign the unit ──
            // Prefer filling an existing empty slot so we don't inflate the target's
            // quantity. Only create a new row when there's no slot waiting (real stock add).
            $targetItem = $this->findGhostSlot($targetRental, $productId, $variationId);

            if ($targetItem) {
                $targetItem->parent_item_id = null;
                $targetItem->product_unit_id = $unitId;
                $targetItem->days = $this->calcDays($targetRental);
                $targetItem->save(); // updated hook attaches kits in target
            } else {
                $targetItem = $targetRental->items()->create([
                    'product_unit_id' => $unitId,
                    'product_id' => $productId,
                    'product_variation_id' => $variationId,
                    'daily_rate' => $sourceItem->daily_rate,
                    'days' => $this->calcDays($targetRental),
                    'discount' => $sourceItem->discount,
                ]);
            }

            // ── Source side: keep the row as an empty ghost slot (don't delete the line) ──
            // Nulling product_unit_id fires RentalItem::updated → its kits are detached.
            $sourceItem->parent_item_id = null;
            $sourceItem->product_unit_id = null;
            // Preserve product/variation so the row still renders as the same product.
            $sourceItem->product_id = $productId;
            $sourceItem->product_variation_id = $variationId;
            $sourceItem->save();

            // Both rentals own a touched RentalItem now, so RentalObserver::updated has
            // already fired for each via $touches = ['rental']. No manual total recalc here.
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
                'source_item_id' => $sourceItem->id,
                'target_item_id' => $targetItem->id,
                'product_unit_id' => $unitId,
                'from_rental_id' => $sourceRental->id,
                'to_rental_id' => $targetRental->id,
                'filled_existing_slot' => $targetItem->wasRecentlyCreated === false,
                'user_id' => Auth::id(),
            ]);

            return $targetItem->fresh();
        });
    }

    /**
     * Find an empty slot (ghost RentalItem with no assigned serial) in $rental for the
     * given product/variation, so a moved unit can fill it instead of adding quantity.
     */
    private function findGhostSlot(Rental $rental, ?int $productId, ?int $variationId): ?RentalItem
    {
        if (!$productId) {
            return null;
        }

        return $rental->items()
            ->whereNull('product_unit_id')
            ->whereNull('parent_item_id')
            ->where('product_id', $productId)
            ->when(
                $variationId,
                fn ($q) => $q->where('product_variation_id', $variationId),
                fn ($q) => $q->whereNull('product_variation_id'),
            )
            ->first();
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
