<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\UnitKit;
use Carbon\Carbon;

/** One source for physical-unit occupancy, including confirmed partial returns. */
class RentalOccupancyService
{
    public const BLOCKING_STATUSES = [
        Rental::STATUS_QUOTATION, Rental::STATUS_CONFIRMED,
        Rental::STATUS_LATE_PICKUP, Rental::STATUS_ACTIVE,
        Rental::STATUS_PARTIAL_RETURN, Rental::STATUS_LATE_RETURN,
    ];

    public static function returnedAt(RentalItem $item): ?Carbon
    {
        $item->loadMissing('deliveryItems.delivery');

        $times = $item->deliveryItems
            ->filter(fn ($row) => $row->rental_item_kit_id === null
                && $row->is_checked && $row->checked_at
                && $row->delivery?->type === Delivery::TYPE_IN
                && $row->delivery?->status === Delivery::STATUS_COMPLETED)
            ->pluck('checked_at');

        return $times->sort()->first();
    }

    public static function dueAt(RentalItem $item): Carbon
    {
        $item->loadMissing('rental');

        return $item->effective_due_at ?? $item->rental->end_date;
    }

    /** Null end means the unit is overdue and still physically out. */
    public static function occupiedUntil(RentalItem $item): ?Carbon
    {
        $item->loadMissing('rental');
        if ($returned = self::returnedAt($item)) {
            return $returned;
        }
        $due = self::dueAt($item);

        return in_array($item->rental->status, [Rental::STATUS_ACTIVE, Rental::STATUS_PARTIAL_RETURN, Rental::STATUS_LATE_RETURN], true)
            && $due->isPast() ? null : $due;
    }

    public static function overlaps(RentalItem $item, Carbon $start, Carbon $end): bool
    {
        $item->loadMissing('rental');
        if (! in_array($item->rental->status, self::BLOCKING_STATUSES, true)) {
            return false;
        }

        $until = self::occupiedUntil($item);

        return $item->rental->start_date->lt($end) && ($until === null || $until->gt($start));
    }

    /** Units blocked by these assignments, including parents that share a tracked kit unit. */
    public static function blockedUnitIds(array $assignedUnitIds): array
    {
        if ($assignedUnitIds === []) {
            return [];
        }

        $resources = array_values(array_unique(array_merge(
            $assignedUnitIds,
            UnitKit::whereIn('unit_id', $assignedUnitIds)->whereNotNull('linked_unit_id')->pluck('linked_unit_id')->all(),
        )));

        return array_values(array_unique(array_merge(
            $resources,
            UnitKit::whereIn('linked_unit_id', $resources)->pluck('unit_id')->all(),
        )));
    }

    /** @return array<int, RentalItem> */
    public static function conflictsFor(RentalItem $item, Carbon $start, Carbon $end, bool $includeSameRental = false): array
    {
        if (! $item->product_unit_id) {
            return [];
        }

        $unitIds = self::blockedUnitIds([$item->product_unit_id]);

        return RentalItem::query()
            ->where('id', '!=', $item->id)
            ->when(! $includeSameRental, fn ($q) => $q->where('rental_id', '!=', $item->rental_id))
            ->whereIn('product_unit_id', $unitIds)
            ->whereHas('rental', fn ($q) => $q->whereIn('status', self::BLOCKING_STATUSES)->where('start_date', '<', $end))
            ->with(['rental.customer', 'productUnit.product', 'deliveryItems.delivery'])
            ->get()
            ->filter(fn (RentalItem $other) => self::overlaps($other, $start, $end))
            ->all();
    }

    /** Describe the physical resource shared by two conflicting rental items. */
    public static function conflictCause(RentalItem $item, RentalItem $other): array
    {
        if ($item->product_unit_id === $other->product_unit_id) {
            return [
                'type' => 'unit',
                'serial' => $item->productUnit?->serial_number,
            ];
        }

        $kits = UnitKit::query()
            ->whereIn('unit_id', [$item->product_unit_id, $other->product_unit_id])
            ->whereNotNull('linked_unit_id')
            ->with('linkedUnit:id,serial_number')
            ->orderBy('id')
            ->get();

        $resourcesFor = fn (int $unitId): array => array_merge(
            [$unitId],
            $kits->where('unit_id', $unitId)->pluck('linked_unit_id')->all(),
        );
        $sharedId = array_values(array_intersect(
            $resourcesFor($item->product_unit_id),
            $resourcesFor($other->product_unit_id),
        ))[0] ?? null;
        $kit = $kits->firstWhere('linked_unit_id', $sharedId);

        return $kit ? [
            'type' => 'kit',
            'name' => $kit->name,
            'serial' => $kit->linkedUnit?->serial_number ?? $kit->serial_number,
        ] : ['type' => 'related'];
    }

    /** @return array<int, int> */
    public static function bookedUnitIds(Carbon $start, Carbon $end, ?int $excludeRentalId = null): array
    {
        $assigned = RentalItem::query()
            ->whereNotNull('product_unit_id')
            ->when($excludeRentalId, fn ($q) => $q->where('rental_id', '!=', $excludeRentalId))
            ->whereHas('rental', fn ($q) => $q->whereIn('status', self::BLOCKING_STATUSES)->where('start_date', '<', $end))
            ->with(['rental', 'deliveryItems.delivery'])
            ->get()
            ->filter(fn (RentalItem $item) => self::overlaps($item, $start, $end))
            ->pluck('product_unit_id')->unique()->values()->all();

        return self::blockedUnitIds($assigned);
    }

    /** Calendar dates describe the booking, regardless of physical return or extensions. */
    public static function scheduleBlocks(RentalItem $item): array
    {
        $item->loadMissing('rental');
        $rental = $item->rental;

        return [[
            'start' => $rental->start_date,
            'end' => $rental->end_date,
            'status' => $rental->status,
        ]];
    }

    /** The current borrower of an overdue physical unit, if it has not been checked in. */
    public static function overdueHolder(RentalItem $item): ?Rental
    {
        $item->loadMissing('rental');
        $rental = $item->rental;

        if (! $rental || ! in_array($rental->status, [Rental::STATUS_ACTIVE, Rental::STATUS_PARTIAL_RETURN, Rental::STATUS_LATE_RETURN], true)) {
            return null;
        }

        return self::dueAt($item)->isPast() && self::returnedAt($item) === null ? $rental : null;
    }
}
