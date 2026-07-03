<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariation extends Model
{
    protected $fillable = [
        'product_id',
        'name',
        'daily_rate',
        'hourly_rate',
        'weekly_rate',
        'monthly_rate',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'weekly_rate' => 'decimal:2',
        'monthly_rate' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * Resolve the rate for a given billing period. Each period-specific column
     * overrides the parent product's rate; when both the variation column AND the
     * parent's are empty, the parent's daily-rate fallback applies (via rateFor).
     */
    public function rateFor(string $period): float
    {
        $column = match ($period) {
            'hour' => $this->hourly_rate,
            'week' => $this->weekly_rate,
            'month' => $this->monthly_rate,
            default => $this->daily_rate,
        };

        if ($column !== null && $column !== '') {
            return (float) $column;
        }

        // Variation override empty → fall back to the parent product's period rate.
        if ($this->product) {
            return $this->product->rateFor($period);
        }

        // No parent loaded and no override → best-effort from the variation daily rate.
        $daily = (float) ($this->daily_rate ?? 0);

        return match ($period) {
            'hour' => $daily / 8,
            'week' => $daily * 7,
            'month' => $daily * 30,
            default => $daily,
        };
    }

    /** Rate map for all periods: ['hour'=>..,'day'=>..,'week'=>..,'month'=>..]. */
    public function rateMap(): array
    {
        $map = [];
        foreach (Product::PERIODS as $p) {
            $map[$p] = $this->rateFor($p);
        }

        return $map;
    }
}
