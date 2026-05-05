<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Computer extends Model
{
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'name',
        'code',
        'brand',
        'specs',
        'status',
        'image_path',
        'notes',
    ];

    protected $casts = [
        'specs' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Computer $computer) {
            if (empty($computer->code)) {
                $base = Str::slug($computer->name) ?: 'computer';
                $code = $base;
                $i = 1;
                while (static::withTrashed()->where('code', $code)->exists()) {
                    $code = $base.'-'.(++$i);
                }
                $computer->code = $code;
            }
        });
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ComputerBooking::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(ComputerMaintenanceLog::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_AVAILABLE]);
    }

    public function isAvailableOn(Carbon $date, string $start, string $end, ?int $excludeBookingId = null): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }

        return ! $this->bookings()
            ->whereIn('status', [ComputerBooking::STATUS_CONFIRMED, ComputerBooking::STATUS_ACTIVE])
            ->whereDate('booking_date', $date->toDateString())
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->exists();
    }
}
