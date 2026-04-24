<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitKit extends Model
{
    protected $fillable = [
        'unit_id',
        'linked_unit_id',
        'track_by_serial',
        'name',
        'serial_number',
        'condition',
        'notes',
        'last_checked_at',
        'maintenance_status',
    ];

    protected $casts = [
        'last_checked_at'  => 'datetime',
        'track_by_serial'  => 'boolean',
    ];

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function linkedUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'linked_unit_id');
    }

    public static function getConditionOptions(): array
    {
        return [
            'excellent' => 'Excellent',
            'good'      => 'Good',
            'fair'      => 'Fair',
            'poor'      => 'Poor',
            'broken'    => 'Broken',
            'lost'      => 'Lost',
        ];
    }
}
