<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'product_unit_id',
        'start_date',
        'end_date',
        'days',
        'pricing_period',
        'daily_rate',
        'subtotal',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'daily_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public static function calculateDays($startDate, $endDate, string $period = 'day'): int
    {
        return Rental::periodsBetween($startDate, $endDate, $period);
    }

    public function recalculate(): void
    {
        // `days` = number of billing periods; `daily_rate` = rate per period.
        $this->days = self::calculateDays($this->start_date, $this->end_date, $this->pricing_period ?? 'day');
        $this->subtotal = $this->daily_rate * $this->days;
        $this->save();
    }
}