<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class KioskLoginToken extends Model
{
    protected $fillable = [
        'token',
        'computer_id',
        'claimed_by_user_id',
        'claimed_at',
        'expires_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function computer(): BelongsTo
    {
        return $this->belongsTo(Computer::class);
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now())->whereNull('claimed_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? true;
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }

    /**
     * Generate fresh token for a computer. TTL default 60 seconds (rotated by kiosk page).
     */
    public static function generateFor(int $computerId, int $ttlSeconds = 60): self
    {
        return static::create([
            'token' => Str::random(48),
            'computer_id' => $computerId,
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);
    }
}
