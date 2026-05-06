<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyDiscount extends Model
{
    protected $fillable = [
        'name',
        'description',
        'min_days',
        'free_days',
        'max_discount_amount',
        'is_active',
        'start_date',
        'end_date',
        'priority',
    ];

    protected $casts = [
        'min_days' => 'integer',
        'free_days' => 'integer',
        'max_discount_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'priority' => 'integer',
    ];

    /**
     * Check if discount is currently valid
     */
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->start_date && $this->start_date->isFuture()) return false;
        if ($this->end_date && $this->end_date->isPast()) return false;
        return true;
    }

    /**
     * Calculate discount for given rental days and daily rate
     */
    public function calculateDiscount(int $days, float $dailyRate): float
    {
        if ($days < $this->min_days) return 0;
        
        $multiplier = floor($days / $this->min_days);
        $totalFreeDays = $multiplier * $this->free_days;
        
        $discount = $totalFreeDays * $dailyRate;
        
        if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
            $discount = $this->max_discount_amount;
        }
        
        return round($discount, 2);
    }

    /**
     * Get applicable daily discount for given rental days
     */
    public static function getApplicableDiscount(int $days): ?self
    {
        return self::where('is_active', true)
            ->where('min_days', '<=', $days)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('min_days', 'desc')
            ->first();
    }
}
