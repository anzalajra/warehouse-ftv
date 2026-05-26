<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected static function booted(): void
    {
        static::saved(function (Setting $setting) {
            Cache::forget("setting.{$setting->key}");
        });
        static::deleted(function (Setting $setting) {
            Cache::forget("setting.{$setting->key}");
        });
    }

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'label',
        'description',
        'sort_order',
    ];

    public static function get(string $key, $default = null)
    {
        return Cache::remember("setting.{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, $value): void
    {
        $setting = self::firstOrNew(['key' => $key]);
        $setting->value = $value;
        if (!$setting->exists) {
            $setting->label = ucwords(str_replace('_', ' ', $key));
        }
        $setting->save();

        Cache::forget("setting.{$key}");
    }

    public static function getGroup(string $group): array
    {
        return self::where('group', $group)
            ->orderBy('sort_order')
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }

    public static function isStorefrontRentalDisabled(): bool
    {
        $enabled = filter_var(self::get('storefront_rental_disabled', false), FILTER_VALIDATE_BOOLEAN);
        if (! $enabled) {
            return false;
        }

        $start = self::get('storefront_rental_disabled_start');
        $end   = self::get('storefront_rental_disabled_end');

        // No window = indefinite while toggle is on.
        if (empty($start) && empty($end)) {
            return true;
        }

        $now = \Carbon\Carbon::now();
        try {
            if (! empty($start) && $now->lt(\Carbon\Carbon::parse($start))) {
                return false;
            }
            if (! empty($end) && $now->gt(\Carbon\Carbon::parse($end))) {
                return false;
            }
        } catch (\Exception $e) {
            return true;
        }

        return true;
    }

    public static function storefrontRentalDisabledMessage(): string
    {
        $msg = self::get('storefront_rental_disabled_message');
        return is_string($msg) && trim($msg) !== ''
            ? $msg
            : 'Mohon maaf, layanan rental sedang tidak tersedia untuk sementara waktu.';
    }

    public static function getAllGrouped(): array
    {
        return self::orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group')
            ->toArray();
    }
}