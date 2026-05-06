<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KioskPairingCode extends Model
{
    protected $fillable = [
        'code',
        'computer_id',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function computer(): BelongsTo
    {
        return $this->belongsTo(Computer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at?->isFuture();
    }

    public static function generateUnique(int $computerId, int $ttlMinutes = 5): self
    {
        // expire any existing un-used codes for this computer
        static::where('computer_id', $computerId)->whereNull('used_at')->update(['used_at' => now()]);

        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('code', $code)->where('expires_at', '>', now())->exists());

        return static::create([
            'code' => $code,
            'computer_id' => $computerId,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }
}
