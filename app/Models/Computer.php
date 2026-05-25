<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'room_id',
        'name',
        'code',
        'checkin_slug',
        'brand',
        'specs',
        'status',
        'image_path',
        'notes',
        'last_seen_at',
        'last_heartbeat_at',
        'last_heartbeat_data',
        'kiosk_token',
        'kiosk_paired_at',
    ];

    protected $casts = [
        'specs' => 'array',
        'last_seen_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'last_heartbeat_data' => 'array',
        'kiosk_paired_at' => 'datetime',
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

            if (empty($computer->checkin_slug)) {
                do {
                    $slug = Str::random(24);
                } while (static::withTrashed()->where('checkin_slug', $slug)->exists());
                $computer->checkin_slug = $slug;
            }
        });
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ComputerRoom::class, 'room_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ComputerBooking::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(ComputerMaintenanceLog::class);
    }

    public function checkinUrl(): string
    {
        $this->ensureCheckinSlug();

        return url('/kiosk/checkin/'.$this->checkin_slug);
    }

    /**
     * Backfill checkin_slug for legacy records. Idempotent.
     */
    public function ensureCheckinSlug(): void
    {
        if (! empty($this->checkin_slug)) {
            return;
        }

        do {
            $slug = Str::random(24);
        } while (static::withTrashed()->where('checkin_slug', $slug)->where('id', '!=', $this->id)->exists());

        $this->checkin_slug = $slug;
        $this->saveQuietly();
    }

    public function pairingCodes(): HasMany
    {
        return $this->hasMany(KioskPairingCode::class);
    }

    public function getIsOnlineAttribute(): bool
    {
        if (! $this->last_seen_at) {
            return false;
        }

        $threshold = (int) (\App\Models\Setting::get('computer_kiosk_offline_threshold_seconds') ?? 60);

        return $this->last_seen_at->gte(now()->subSeconds($threshold));
    }

    public function currentBookingUser(): ?User
    {
        $now = \Carbon\Carbon::now();

        // Primary signal: someone is physically checked in and hasn't logged out.
        // This catches walk-ins, overstayed sessions, and night-shift slots that
        // cross midnight (where booking_date != today).
        $active = $this->bookings()
            ->with('user:id,name')
            ->where('status', ComputerBooking::STATUS_ACTIVE)
            ->whereNotNull('checked_in_at')
            ->whereNull('actual_ended_at')
            ->orderByDesc('checked_in_at')
            ->first();

        if ($active) {
            return $active->user;
        }

        // Fallback: a confirmed booking whose slot covers right now.
        $confirmed = $this->bookings()
            ->with('user:id,name')
            ->where('status', ComputerBooking::STATUS_CONFIRMED)
            ->whereDate('booking_date', $now->toDateString())
            ->get()
            ->first(function (ComputerBooking $b) use ($now) {
                $start = \Carbon\Carbon::parse($b->booking_date->toDateString().' '.$b->start_time);
                $end = \Carbon\Carbon::parse($b->booking_date->toDateString().' '.$b->end_time);

                return $now->between($start, $end);
            });

        return $confirmed?->user;
    }

    public function runningApps(): array
    {
        return $this->last_heartbeat_data['running_apps'] ?? [];
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
