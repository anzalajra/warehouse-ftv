<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComputerBookingSlot extends Model
{
    protected $fillable = [
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDay(Builder $query, int $dayOfWeek): Builder
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    public function getStartTimeAttribute($value): string
    {
        return $value ? substr($value, 0, 5) : $value;
    }

    public function getEndTimeAttribute($value): string
    {
        return $value ? substr($value, 0, 5) : $value;
    }
}
