<?php

namespace App\Services;

use App\Models\DailyDiscount;
use App\Models\DatePromotion;
use App\Models\Discount;
use Carbon\Carbon;

class PromotionService
{
    /**
     * Calculate all applicable promotions for a rental
     * 
     * @param float $subtotal Base rental amount
     * @param int $days Number of rental days
     * @param float $dailyRate Average daily rate (for daily discount calculation)
     * @param Carbon|null $startDate Rental start date (for date promotions)
     * @param string|null $discountCode Manual discount code
     * @return array
     */
    public static function calculatePromotions(
        float $subtotal,
        int $days,
        float $dailyRate,
        ?Carbon $startDate = null,
        ?string $discountCode = null
    ): array {
        $result = [
            'daily_discount' => null,
            'daily_discount_amount' => 0,
            'date_promotion' => null,
            'date_promotion_amount' => 0,
            'code_discount' => null,
            'code_discount_amount' => 0,
            'total_discount' => 0,
            'final_total' => $subtotal,
        ];

        // 1. Apply Daily Discount (e.g., rent 3 days pay 2)
        $dailyDiscount = DailyDiscount::getApplicableDiscount($days);
        if ($dailyDiscount) {
            $dailyDiscountAmount = $dailyDiscount->calculateDiscount($days, $dailyRate);
            $result['daily_discount'] = $dailyDiscount;
            $result['daily_discount_amount'] = $dailyDiscountAmount;
        }

        // 2. Apply Date Promotion (special date discounts)
        $checkDate = $startDate ?? now();
        $datePromotion = DatePromotion::getApplicablePromotion($checkDate);
        if ($datePromotion) {
            // Calculate on subtotal after daily discount
            $amountAfterDaily = $subtotal - $result['daily_discount_amount'];
            $datePromotionAmount = $datePromotion->calculateDiscount($amountAfterDaily);
            $result['date_promotion'] = $datePromotion;
            $result['date_promotion_amount'] = $datePromotionAmount;
        }

        // 3. Apply Discount Code (manual promo code)
        if ($discountCode) {
            $discount = Discount::findByCode($discountCode);
            if ($discount && $discount->isValid()) {
                // Check minimum amount (use original subtotal)
                if (!$discount->min_rental_amount || $subtotal >= $discount->min_rental_amount) {
                    // Check usage limit
                    if (!$discount->usage_limit || $discount->usage_count < $discount->usage_limit) {
                        // Calculate on amount after other discounts
                        $amountAfterOthers = $subtotal - $result['daily_discount_amount'] - $result['date_promotion_amount'];
                        $codeDiscountAmount = $discount->calculateDiscount($amountAfterOthers);
                        $result['code_discount'] = $discount;
                        $result['code_discount_amount'] = $codeDiscountAmount;
                    }
                }
            }
        }

        // Calculate totals
        $result['total_discount'] = $result['daily_discount_amount'] 
            + $result['date_promotion_amount'] 
            + $result['code_discount_amount'];

        // Ensure discount doesn't exceed subtotal
        if ($result['total_discount'] > $subtotal) {
            $result['total_discount'] = $subtotal;
        }

        $result['final_total'] = $subtotal - $result['total_discount'];

        return $result;
    }

    /**
     * Get summary of active promotions for display
     */
    public static function getActivePromotionsSummary(): array
    {
        $promos = [];
        
        $dailyDiscounts = DailyDiscount::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->orderBy('min_days')
            ->get();

        foreach ($dailyDiscounts as $d) {
            $promos[] = "{$d->name}: Sewa {$d->min_days} hari, gratis {$d->free_days} hari";
        }

        $datePromotions = DatePromotion::where('is_active', true)
            ->get()
            ->filter(function ($promo) {
                if ($promo->recurring_yearly) {
                    return true; // Always show recurring
                }
                return $promo->promo_date->isFuture() || $promo->promo_date->isToday();
            });

        foreach ($datePromotions as $d) {
            $discount = $d->type === 'percentage' 
                ? $d->value . '%' 
                : 'Rp ' . number_format($d->value, 0, ',', '.');
            $promos[] = "{$d->name}: {$discount} pada " . $d->promo_date->format('d M');
        }

        return $promos;
    }
}
