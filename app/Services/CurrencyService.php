<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Multi-currency helpers. The GL is always kept in BASE currency; a document's
 * `exchange_rate` (units of base per 1 unit of the document currency) converts its
 * amounts to base for posting/reporting.
 */
class CurrencyService
{
    public static function baseCode(): string
    {
        return Setting::get('base_currency', 'IDR') ?: 'IDR';
    }

    /** units of base per 1 unit of $code (base currency returns 1). */
    public static function rate(?string $code): float
    {
        if (! $code || $code === self::baseCode()) {
            return 1.0;
        }

        $rate = Cache::remember("currency.rate.{$code}", 300, function () use ($code) {
            return (float) (Currency::where('code', $code)->value('exchange_rate') ?? 1);
        });

        return $rate > 0 ? $rate : 1.0;
    }

    public static function toBase(float $amount, ?string $code, ?float $explicitRate = null): float
    {
        $rate = $explicitRate ?: self::rate($code);

        return round($amount * $rate, 2);
    }

    public static function options(): array
    {
        return Currency::where('is_active', true)
            ->orderByDesc('is_base')
            ->orderBy('code')
            ->pluck('code', 'code')
            ->toArray();
    }

    public static function symbol(?string $code): string
    {
        if (! $code) {
            return '';
        }

        return (string) (Currency::where('code', $code)->value('symbol') ?: $code.' ');
    }

    public static function format(float $amount, ?string $code = null): string
    {
        $code ??= self::baseCode();

        return trim(self::symbol($code)).' '.number_format($amount, 2, ',', '.');
    }
}
