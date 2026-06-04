<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    protected $fillable = [
        'delivery_id',
        'rental_item_id',
        'rental_item_kit_id',
        'is_checked',
        'condition',
        'photos',
        'notes',
    ];

    protected $casts = [
        'is_checked' => 'boolean',
        'photos' => 'array',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class);
    }

    public function rentalItemKit(): BelongsTo
    {
        return $this->belongsTo(RentalItemKit::class);
    }

    public static function getConditionOptions(): array
    {
        return [
            'excellent' => 'Excellent',
            'good' => 'Good',
            'fair' => 'Fair',
            'poor' => 'Poor',
        ];
    }

    public static function getConditionInOptions(): array
    {
        return [
            'excellent' => 'Excellent',
            'good' => 'Good',
            'fair' => 'Fair',
            'poor' => 'Poor',
            'lost' => 'Lost',
            'broken' => 'Broken',
        ];
    }

    public static function getConditionColor(string $condition): string
    {
        return match ($condition) {
            'excellent' => 'success',
            'good' => 'info',
            'fair' => 'warning',
            'poor' => 'danger',
            'lost' => 'danger',
            'broken' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Visual meta for the operation console condition control.
     * tone drives both the badge color and the segmented-button accent.
     */
    public static function getConditionMeta(): array
    {
        return [
            'excellent' => ['label' => 'Excellent', 'tone' => 'good', 'icon' => 'check'],
            'good' => ['label' => 'Good', 'tone' => 'good', 'icon' => 'check'],
            'fair' => ['label' => 'Fair', 'tone' => 'minor', 'icon' => 'alert'],
            'poor' => ['label' => 'Poor', 'tone' => 'broken', 'icon' => 'broken'],
            'broken' => ['label' => 'Broken', 'tone' => 'broken', 'icon' => 'broken'],
            'lost' => ['label' => 'Lost', 'tone' => 'lost', 'icon' => 'lost'],
        ];
    }

    /**
     * Conditions that flag an item as damaged / needing attention (Issues filter + maintenance).
     */
    public static function getIssueConditions(): array
    {
        return ['fair', 'poor', 'broken', 'lost'];
    }

    /**
     * Conditions that send a unit to maintenance on check-in/out.
     */
    public static function getMaintenanceConditions(): array
    {
        return ['broken', 'lost'];
    }
}
