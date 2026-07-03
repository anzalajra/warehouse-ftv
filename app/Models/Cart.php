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

    /** Human (Indonesian) label for the cart line's billing period. */
    public function periodLabel(): string
    {
        return [
            'hour' => 'jam',
            'day' => 'hari',
            'week' => 'minggu',
            'month' => 'bulan',
        ][$this->pricing_period ?? 'day'] ?? 'hari';
    }

    public function recalculate(): void
    {
        // `days` = number of billing periods; `daily_rate` = rate per period.
        $this->days = self::calculateDays($this->start_date, $this->end_date, $this->pricing_period ?? 'day');
        $this->subtotal = $this->daily_rate * $this->days;
        $this->save();
    }

    /**
     * Auto multi-tier pricing for a customer's whole cart: pick the cheapest billing
     * tier for the current duration across all lines, then persist each row's period,
     * rate, period-count and subtotal. Replaces the old manual "pricing_period" picker
     * so a 1-day booking is billed daily and a 7-day booking weekly only when that is
     * actually cheaper. Called on add-to-cart, the cart page and checkout.
     *
     * @return array{period:string, periods:int, total:float, totals:array<string,float>, counts:array<string,int>}
     */
    public static function repriceForUser(User $customer): array
    {
        $items = $customer->carts()->with(['productUnit.product', 'productUnit.variation'])->get();

        if ($items->isEmpty()) {
            return ['period' => 'day', 'periods' => 1, 'total' => 0.0, 'totals' => [], 'counts' => []];
        }

        $start = $items->min('start_date');
        $end = $items->max('end_date');

        $lines = $items->map(fn (self $c) => ['rates' => self::rateMapForRow($c), 'quantity' => 1])->all();
        $opt = Rental::optimalPricing($lines, $start, $end);

        foreach ($items as $c) {
            $rate = (float) (self::rateMapForRow($c)[$opt['period']] ?? $c->daily_rate);

            // Only write when something actually changed (this runs on read paths too).
            if ($c->pricing_period !== $opt['period']
                || (int) $c->days !== (int) $opt['periods']
                || (float) $c->daily_rate !== $rate) {
                $c->pricing_period = $opt['period'];
                $c->days = $opt['periods'];
                $c->daily_rate = $rate;
                $c->subtotal = $rate * $opt['periods'];
                $c->save();
            }
        }

        return $opt;
    }

    /** Resolve the per-period rate map for a cart row (variation overrides product). */
    protected static function rateMapForRow(self $c): array
    {
        $variation = $c->productUnit?->variation;
        if ($variation) {
            return $variation->rateMap();
        }

        $product = $c->productUnit?->product;
        if ($product) {
            return $product->rateMap();
        }

        $rate = (float) $c->daily_rate;

        return ['hour' => $rate, 'day' => $rate, 'week' => $rate, 'month' => $rate];
    }
}