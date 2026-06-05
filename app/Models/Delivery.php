<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    protected $fillable = [
        'delivery_number',
        'rental_id',
        'type',
        'date',
        'checked_by',
        'recipient_name',
        'recipient_signature',
        'signed_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'signed_at' => 'datetime',
    ];

    public function isSigned(): bool
    {
        return ! empty($this->recipient_signature);
    }

    public const TYPE_OUT = 'out';
    public const TYPE_IN = 'in';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($delivery) {
            if (empty($delivery->delivery_number)) {
                $delivery->delivery_number = self::generateDeliveryNumber($delivery);
            }
        });

        static::created(function ($delivery) {
            // Notify admins about new delivery
            $admins = User::role(['super_admin', 'admin', 'staff'])->get();
            if ($delivery->type === self::TYPE_OUT) {
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\DeliveryOutNotification($delivery));
            } else {
                \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\DeliveryInNotification($delivery));
            }
        });
    }

    /**
     * Derive the delivery number from the parent rental code so that one rental
     * has a single integrated code family instead of three separate sequences:
     *   Rental            : RNT202606060001
     *   SJ Keluar (out)   : RNT202606060001-K
     *   SJ Masuk (in)     : RNT202606060001-M   (partial returns: -M2, -M3, ...)
     */
    public static function generateDeliveryNumber(Delivery $delivery): string
    {
        $rental = $delivery->relationLoaded('rental') && $delivery->rental
            ? $delivery->rental
            : Rental::find($delivery->rental_id);

        // Defensive fallback: keep the legacy date-based scheme if a delivery is
        // ever created without a rental (should not happen in normal flows).
        if (! $rental) {
            $prefix = $delivery->type === self::TYPE_OUT ? 'SJK' : 'SJM';
            return $prefix . '-' . now()->format('Ymd') . '-' . str_pad(1, 3, '0', STR_PAD_LEFT);
        }

        $base = $rental->rental_code;
        $letter = $delivery->type === self::TYPE_OUT ? 'K' : 'M';

        // Count existing deliveries of the same direction for this rental. The
        // delivery being created is not yet persisted, so the first one gets a
        // bare suffix (-K / -M) and any subsequent one (partial returns) gets a
        // running counter (-M2, -M3, ...).
        $existing = self::where('rental_id', $rental->id)
            ->where('type', $delivery->type)
            ->count();

        return $existing === 0
            ? $base . '-' . $letter
            : $base . '-' . $letter . ($existing + 1);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function allItemsChecked(): bool
    {
        return $this->items->where('is_checked', false)->count() === 0;
    }

    public function complete(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_OUT => 'Keluar (Check-out)',
            self::TYPE_IN => 'Masuk (Check-in)',
        ];
    }

    public static function getTypeColor(string $type): string
    {
        return match ($type) {
            self::TYPE_OUT => 'warning',
            self::TYPE_IN => 'success',
            default => 'gray',
        };
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    public static function getStatusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}