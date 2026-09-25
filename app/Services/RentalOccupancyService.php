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
    public static function conflictsFor(RentalItem $item, Carbon $start, Carbon $end): array
    {
        if (! $item->product_unit_id) {
            return [];
        }

        $unitIds = self::blockedUnitIds([$item->product_unit_id]);

        return RentalItem::query()
            ->where('id', '!=', $item->id)
            ->whereIn('product_unit_id', $unitIds)
            ->whereHas('rental', fn ($q) => $q->whereIn('status', self::BLOCKING_STATUSES)->where('start_date', '<', $end))
            ->with(['rental.customer', 'productUnit.product', 'deliveryItems.delivery'])
            ->get()
            ->filter(fn (RentalItem $other) => self::overlaps($other, $start, $end))
            ->all();
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

    /** Original block followed by an Over-Time block for the remaining item. */
    public static function scheduleBlocks(RentalItem $item, ?Carbon $visibleEnd = null): array
    {
        $item->loadMissing('rental');
        $start = $item->rental->start_date;
        $end = self::occupiedUntil($item) ?? ($visibleEnd ?? now());
        $due = self::dueAt($item);
        $overtime = $item->overtime_started_at;
        $normalStatus = $item->rental->status === Rental::STATUS_LATE_RETURN
            ? Rental::STATUS_ACTIVE : $item->rental->status;
        $blocks = [];
        $append = function (Carbon $from, Carbon $to, string $status) use (&$blocks): void {
            if ($to->gt($from)) {
                $blocks[] = ['start' => $from, 'end' => $to, 'status' => $status];
            }
        };

        if ($overtime && $overtime->gt($start)) {
            $append($start, $overtime->min($end), $normalStatus);
            $append($overtime, $due->min($end), 'over_time');
        } else {
            $append($start, $due->min($end), $normalStatus);
        }

        $lateStart = $overtime && $overtime->gt($due) ? $overtime : $due;
        $append($lateStart, $end, Rental::STATUS_LATE_RETURN);

        return $blocks;
    }
}
