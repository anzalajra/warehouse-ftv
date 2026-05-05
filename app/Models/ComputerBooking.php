<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComputerBooking extends Model
{
    use SoftDeletes;

    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const ACTIVE_STATUSES = [self::STATUS_CONFIRMED, self::STATUS_ACTIVE];

    protected $fillable = [
        'booking_code',
        'user_id',
        'computer_id',
        'booking_date',
        'start_time',
        'end_time',
        'purpose',
        'status',
        'admin_notes',
        'tnc_accepted_at',
        'checked_in_at',
        'cancelled_reason',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'tnc_accepted_at' => 'datetime',
        'checked_in_at' => 'datetime',
    ];

    public static function generateBookingCode(?\Carbon\Carbon $date = null): string
    {
        $date = $date ?: now();
        $prefix = 'CB-'.$date->format('Ymd').'-';
        $last = static::where('booking_code', 'like', $prefix.'%')
            ->orderByDesc('booking_code')
            ->value('booking_code');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function computer(): BelongsTo
    {
        return $this->belongsTo(Computer::class);
    }

    public function getStartTimeAttribute($value): ?string
    {
        return $value ? substr($value, 0, 5) : $value;
    }

    public function getEndTimeAttribute($value): ?string
    {
        return $value ? substr($value, 0, 5) : $value;
    }

    public function getDurationHours(): float
    {
        $start = strtotime($this->start_time);
        $end = strtotime($this->end_time);

        return $end > $start ? round(($end - $start) / 3600, 2) : 0;
    }

    public function overlapsWith(string $start, string $end): bool
    {
        return $this->start_time < $end && $this->end_time > $start;
    }

    public function isCancellable(): bool
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return false;
        }

        $startsAt = \Carbon\Carbon::parse($this->booking_date->toDateString().' '.$this->start_time);

        return $startsAt->isFuture();
    }

    public function startsAt(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($this->booking_date->toDateString().' '.$this->start_time);
    }

    public function endsAt(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($this->booking_date->toDateString().' '.$this->end_time);
    }
}
