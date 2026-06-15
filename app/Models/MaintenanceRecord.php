<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const TYPE_CORRECTIVE = 'corrective';

    public const TYPE_PREVENTIVE = 'preventive';

    public const TYPE_INSPECTION = 'inspection';

    protected $fillable = [
        'product_unit_id',
        'unit_kit_id',
        'rental_id',
        'technician_id',
        'title',
        'description',
        'cost',
        'date',
        'started_at',
        'completed_at',
        'status',
        'type',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function productUnit()
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function unitKit()
    {
        return $this->belongsTo(UnitKit::class);
    }

    public function rental()
    {
        return $this->belongsTo(Rental::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /** Open = work not yet finished (pending or in progress). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
    }

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
        ];
    }

    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_CORRECTIVE => 'Corrective (Repair)',
            self::TYPE_PREVENTIVE => 'Preventive (Service)',
            self::TYPE_INSPECTION => 'Inspection / QC',
        ];
    }

    /**
     * How many days the unit has been (or was) down for this record.
     * Falls back to now() while still open. Carbon 3 diff is signed, so abs().
     */
    public function getDowntimeDaysAttribute(): int
    {
        if (! $this->started_at) {
            return 0;
        }

        $end = $this->completed_at ?? now();

        return max(0, (int) abs($this->started_at->diffInDays($end)));
    }
}
